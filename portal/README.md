# Dwight moderation portal: login

Login for the Dwight moderation portal, against the `dwight` database
(`dwight-admins`).

```
dwight/                  → goes in htdocs (e.g. C:\xampp\htdocs\dwight\)
├── login.html
├── index.html           protected page (placeholder for the portal)
├── favicon.ico
├── api/index.php        the actions (switch on `action`) and the JSON responses
├── api/incls/rateandsecurity.php   checked before every action: POST only,
│                                   CSRF, login rate limits
├── api/incls/auth.php   sessions and login: Login(), CheckAuth(), Logout()…
├── api/incls/incls.dbh.php   DB settings (XAMPP defaults: root, no password)
├── css/main.css  js/main.js  img/
setup/                   → keep OUTSIDE htdocs (e.g. C:\xampp\setup\)
├── dwight-schema.sql    the database structure
├── auth-tables.sql      the `dwight-login-attempts` table
├── migrations/          changes for databases created with an older version
└── create-admin.php     creates admins from the command line
```

## Setup (XAMPP)

1. In phpMyAdmin, create the `dwight` database, select it, and run
   `setup/dwight-schema.sql` (skip it if the tables already exist), then
   `setup/auth-tables.sql`. If you already had `dwight-login-attempts`, run the
   files in `setup/migrations/` instead of `auth-tables.sql`.
2. Copy `dwight/` into `htdocs` (`C:\xampp\htdocs\dwight\`) and `setup/` into
   the XAMPP folder, next to `htdocs` (`C:\xampp\setup\`), so it isn't served.
   `create-admin.php` finds the DB config in either this layout or the
   repository's; for another layout, add the path at its top.
3. From `C:\xampp\setup\`, create your admin and the bot account:
   ```
   php create-admin.php yourname --telegram=123456789
   php create-admin.php Dwight --bot
   ```
4. Open `http://localhost/dwight/login.html`.

In production: put the webapp's own DB user in `incls.dbh.php`, and use HTTPS
(the session cookie is only marked `secure` on HTTPS).

## Security notes

- `Error()`, `Notice()` and `SendJSON()` end with `exit` and build the JSON
  with `json_encode`.
- `incls.dbh.php` opens the database connection once per request and reuses it.
- POST only.
- Session cookie: `httponly`, `SameSite=Strict`, `secure` on HTTPS.
- CSRF token: checked by the router for every action, attached by `CallApi()`.
- Failed logins are limited (5 per username, 20 per IP in 15 minutes), stored
  in the database. The checks use indexes, and old attempts are cleaned up on
  about 1 failed login in 100, so a flood of wrong logins stays cheap for the
  database. Real DDoS protection (Cloudflare, `mod_evasive`, fail2ban) belongs
  in front of the server.
- Sessions expire after 2 h idle or 12 h total (`$SESSION_IDLE_TIMEOUT`,
  `$SESSION_MAX_AGE` at the top of `api/incls/auth.php`).
- Login errors are shown to the user as toasts.
- An account with an empty `Hash` (the bot) can never log in.

## How requests are checked

Before the `switch`, `CheckRequest()` (in `api/incls/rateandsecurity.php`)
decides who is calling:

- **Browser (session cookie):** the CSRF token is checked automatically for
  every action except the ones in `$NO_CSRF_ACTIONS` (`session`, `verify`, which
  change nothing). In `main.js`, every request goes through `CallApi()`, which
  attaches the token. Neither side relies on remembering to do it.
- **Program (`Authorization: Bearer <token>`), e.g. the C# bot:** no session
  and no CSRF (no cookies, so CSRF can't happen). Actions in
  `$SESSION_ONLY_ACTIONS` (login, logout…) aren't available this way.
  **Not implemented yet:** `CheckApiToken()` refuses every token. Its comment
  says what to add; `IsAuthorized()`, `GetUID()` and `GetUsername()` already
  handle both cases. `api/.htaccess` makes sure Apache passes the
  `Authorization` header to PHP.

## Adding actions

Add a `case` to the switch in `api/index.php`. CSRF is already checked, so
only check that the caller is logged in, with `IsAuthorized()` (works for both
browser sessions and, later, API tokens):

```php
case "get-bans":
    if(IsAuthorized()){
        require "./incls/incls.dbh.php";
        $statement = $conn->prepare("SELECT * FROM `dwight-bans` WHERE `State`='A';");
        ...
        SendJSON($json);
    }else{
        Error(3);
    }
    break;
```

In `main.js`, build the `formData` as usual and call `CallApi(formData)`
instead of `fetch`. New protected pages: copy `index.html`
(`onload="Loader();Verify(true);"`).

`whoami` is an example action that works both ways: it returns who you are and
whether you came through a session or a token.

OTP (`UsesOTP`/`OTPCode`) isn't used yet: it goes in the `login` case, after
`password_verify`.
