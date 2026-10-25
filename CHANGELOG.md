# Changelog

## v1.8.1

- The backdrop's light is as gentle in every theme colour as in orange (kit 1.13.1).

## v1.8.0

- Split follows the theme colour chosen in the dashboard (kit 1.13), with Bree Serif titles and dark grey text in the light theme.

## v1.7.0

- Every address comes from the platform file (kit 1.12), so Split runs on any domain; notices go to the blog only when it is installed.
- The notify secret is no longer in the repository (`config/notify.example.php` shows the format); deploy uses this server's PHP-FPM and web user.

## v1.6.4

- Page headings are a real frame with its inner panel; the main page's heading has the orange ring around the panel, as on widgets (kit 1.11.1).

## v1.6.3

- Page headings sit in a frame of their own; the group list's has the orange ring (kit 1.11).

## v1.6.2

- Background: large soft orange blobs that fade in, drift and fade out, lighting only the glyphs (kit 1.10).

## v1.6.1

- New background: embers from orange to yellow that move away from the pointer, so you can chase them (kit 1.9).

## v1.6.0

- Svenska, English och Nederlands: webbläsarens språk som standard, eller valt i kontomenyn.
- The account menu is the same in every app: Account, the app's own items, Language, Theme, Sign out.

## 1.4.0

- The PlainShow sidebar: your groups in it, and you at its foot with the account menu; a drawer on phones. The glyph backdrop behind the page. Kit 1.1.

## 1.3.1

- plainshow-kit 1.0.

## 1.3.0

- The Split API is reachable at plainshow.se/api/split/v1 with a personal API token, through core's gateway.
- `/api/v1/openapi.json` describes the API.

## 1.2.1

- Outfit for all text and the serif for titles (kit 0.5).

## 1.2.0

- Dashboard widget `split.balances`: what you owe and are owed per group, served to Core over a signed request.
- Served at split.plainshow.se with a Let's Encrypt certificate; the vhost lives in `ops/plainshow-split.conf`.

## 1.0.0

- Split moves out of the blog into its own PlainShow extension, with its own database.
- Amounts in whole öre, equal, amount and percent splits, the fewest settling payments with Swish links, payment history and an activity log.
- Only group members see a group. The creator renames, removes people and deletes; expenses are changed by whoever added or paid them.
- Nobody can leave or be removed while they owe or are owed money.
- CSRF on every change, prepared statements throughout, sessions on Split's own cookie.
