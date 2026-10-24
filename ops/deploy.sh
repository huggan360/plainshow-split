#!/usr/bin/env bash
# Atomic release of PlainShow Split.
#
#   ops/deploy.sh [ref]          build ref (default: HEAD's tag or commit) and switch to it
#   ops/deploy.sh --rollback     switch back to the previous release
#
# Releases live in /srv/plainshow/split/releases/<name>; config/ and storage/
# come from shared/. PHP runs in the plainshow-split FPM pool as its own user;
# Apache may read the code to serve static files but never config or storage.
set -euo pipefail

BASE=/srv/plainshow/split
SOURCE=$(cd "$(dirname "$0")/.." && pwd)
KEEP=3
say() { printf '\033[1m%s\033[0m\n' "$*"; }

# Facts about this server from the platform file (/etc/plainshow/platform.json).
platform() { php -r '$d = json_decode((string) @file_get_contents("/etc/plainshow/platform.json"), true); echo $d["system"][$argv[1]] ?? "";' "$1"; }
PHP_FPM=$(platform php_fpm_service); PHP_FPM=${PHP_FPM:-php8.5-fpm}
WEB_USER=$(platform web_user); WEB_USER=${WEB_USER:-www-data}

if [ "${1:-}" = "--rollback" ]; then
    current=$(readlink "$BASE/current")
    previous=$(ls -1dt "$BASE"/releases/* | grep -vx "$current" | head -1)
    [ -n "$previous" ] || { echo "No earlier release to roll back to." >&2; exit 1; }
    ln -sfn "$previous" "$BASE/current.new" && mv -Tf "$BASE/current.new" "$BASE/current"
    sudo -n systemctl reload "$PHP_FPM"
    say "Rolled back to $(basename "$previous")"
    exit 0
fi

cd "$SOURCE"
if [ "${1:-}" != --worktree ]; then
    [ -z "$(git status --porcelain --untracked-files=no)" ] || { echo "Commit changes in $SOURCE first." >&2; exit 1; }
fi
REF=${1:-$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)}
NAME="$(date +%Y%m%d%H%M%S)-${REF//\//-}"
RELEASE="$BASE/releases/$NAME"

say "Building split $REF into $RELEASE"
mkdir -p "$RELEASE"
git archive --format=tar "${REF/--worktree/HEAD}" | tar -x -C "$RELEASE"
if [ "$REF" = --worktree ]; then
    git diff --binary HEAD | (cd "$RELEASE" && git apply)
    git ls-files --others --exclude-standard -z | tar --null -T - -cf - | tar -x -C "$RELEASE"
fi
rm -rf "$RELEASE/config" "$RELEASE/storage"
ln -s "$BASE/shared/config" "$RELEASE/config"
ln -s "$BASE/shared/storage" "$RELEASE/storage"

cd "$RELEASE"
GIT_TERMINAL_PROMPT=0 COMPOSER_NO_INTERACTION=1 composer install --no-dev --optimize-autoloader --no-progress --quiet
if [ "$REF" = --worktree ] && [ -d "$SOURCE/public/ui" ]; then
    rm -rf "$RELEASE/public/ui"
    cp -a "$SOURCE/public/ui" "$RELEASE/public/ui"
fi
php app/console.php migrate

ME=$(id -un)
sudo -n chown -R "$ME:plainshow-split" "$RELEASE"
sudo -n chmod -R g+rX,o-rwx "$RELEASE"
sudo -n setfacl -R -m "u:$WEB_USER:rX" "$RELEASE"
sudo -n setfacl -m u:plainshow-split:x /srv/plainshow
sudo -n setfacl -m "u:$WEB_USER:x" "$BASE" "$BASE/releases"
sudo -n find "$BASE/shared/storage" -type d -exec chmod 2770 {} +
sudo -n find "$BASE/shared/storage" -type f -exec chmod 0660 {} +
sudo -n chmod 0640 "$BASE"/shared/config/*.php

ln -sfn "$RELEASE" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"
sudo -n systemctl reload "$PHP_FPM" || echo "Reload $PHP_FPM to finish switching." >&2
say "split is now $NAME"

ls -1dt "$BASE"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf
