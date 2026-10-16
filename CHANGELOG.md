# Changelog

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
