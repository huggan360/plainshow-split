# PlainShow Split

Share costs in groups and settle up with Swish. A PlainShow extension at
https://split.plainshow.se, signed in through login.plainshow.se.

- Groups, members, expenses (split equally, by amount or by percent), payments and an activity log.
- Amounts are whole öre in the database, so shares always add up exactly.
- The fewest payments that settle a group, with Swish links to the payee's number from their PlainShow account.
- People are PlainShow accounts. Names, pictures and phone numbers are read from the `plainshow` database.

## Setup

```sh
composer install
cp config/*.example.php config/  # then fill in database.php and plainshow.php
php app/console.php migrate
php app/console.php import-legacy   # once: copies the blog's split_* tables
php -S localhost:8110 -t public app/dev-router.php
```

`config/notify.php` (optional) points at the blog's internal notice API so
group members get notices and pushes.

## API

`/api/v1` with the session and an `X-CSRF-Token` header for changes:
`GET /groups`, `POST /groups`, `GET|POST|DELETE /groups/{id}`,
`POST /groups/{id}/members`, `DELETE /groups/{id}/members/{user}`,
`POST /groups/{id}/expenses[/{expense}]`, `DELETE /groups/{id}/expenses/{expense}`,
`POST /groups/{id}/payments`, `DELETE /groups/{id}/payments/{payment}`, `GET /people`, `GET /health`.
