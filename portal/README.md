# Dwight moderation portal: login

Login for the Dwight moderation portal, against the `dwight` database
(`dwight-admins`).

```
dwight/                  → goes in htdocs (e.g. C:\xampp\htdocs\dwight\)
├── login.html
├── index.html           protected page (placeholder for the portal)
├── favicon.ico
├── api/index.php        the actions (switch on `action`) and the JSON responses
├── api/incls/rateandsecurity.php   checked before every action: methods,
│                                   CSRF, login rate limits
├── api/incls/auth.php   sessions and login: Login(), CheckAuth(), Logout()…
├── api/incls/incls.dbh.php   settings: DB (XAMPP defaults), HTTPS mode, debug mode
├── css/main.css  js/main.js  img/
setup/                   → keep OUTSIDE htdocs (e.g. C:\xampp\setup\)
├── dwight-schema.sql    the database structure
├── auth-tables.sql      the `dwight-login-attempts` table
└── migrations/          changes for databases created with an older version
```

## Setup (XAMPP)

1. In phpMyAdmin, create the `dwight` database, select it, and run
   `setup/dwight-schema.sql` (skip it if the tables already exist), then
   `setup/auth-tables.sql`. If you already had `dwight-login-attempts`, run the
   files in `setup/migrations/` instead of `auth-tables.sql`.
2. Copy `dwight/` into `htdocs` (`C:\xampp\htdocs\dwight\`). Keep `setup/`
   out of `htdocs`, so the SQL files aren't served.
   XAMPP serves plain HTTP, so in `api/incls/incls.dbh.php` set
   `$HTTPS_MODE = FALSE;` (otherwise the browser won't send the session
   cookie, and you'll be logged out right after logging in).
   While developing, `$DEBUG_MODE = TRUE;` shows the real PHP error instead of
   just "Server Error", and turns the CSRF check off so you can call the API
   with curl or Postman without a token.
3. There's no user creation yet. To test, make a hash from the command line
   (`C:\xampp\php\php.exe` if `php` isn't on your PATH):
   ```
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
   ```
   and insert the admin in phpMyAdmin, pasting the hash:
   ```sql
   INSERT INTO `dwight-admins` (`UUID`,`Username`,`Hash`,`Privileges`,`IsActive`)
   VALUES (UUID(), 'yourname', '$2y$10$...', 'A', 1);
   ```
   An account with `Hash` = `''` (like the Dwight bot account) can never log in.
4. Open `http://localhost/dwight/login.html`.

In production: put the webapp's own DB user in `incls.dbh.php`, keep
`$DEBUG_MODE = FALSE;`, and set `$HTTPS_MODE = TRUE;` if users reach the
portal over HTTPS (see below).

## HTTPS

PHP only decides one thing: `$HTTPS_MODE` says whether **users** are on HTTPS,
directly or through a proxy. PHP may see plain HTTP behind a proxy, so it can't
tell by itself.

- `TRUE`: the session cookie is `__Secure-DWIGHTSESSID`, sent only over HTTPS.
- `FALSE`: the cookie is `DWIGHTSESSID`, for XAMPP or a private LAN/VPN on
  plain HTTP (passwords and cookies then travel unencrypted on that network).

The cookie name changes with the mode on purpose: browsers don't let a plain-HTTP
page replace an HTTPS-only cookie with the same name. Everyone simply logs in
again after a switch.

**Redirecting HTTP to HTTPS and HSTS are the web server's job**, configured
where HTTPS is handled (Apache, or the proxy in front of it). For example:

Apache, in the HTTPS `<VirtualHost>` (needs `mod_headers` and `mod_rewrite`):
```apache
Header always set Strict-Transport-Security "max-age=86400"
```
and in the HTTP `<VirtualHost>`:
```apache
RewriteEngine On
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

nginx:
```nginx
server { listen 80;  return 301 https://$host$request_uri; }
server { listen 443 ssl;  add_header Strict-Transport-Security "max-age=86400" always;  ... }
```

Keep HSTS short (`max-age=86400`, 1 day) while the setup may still change, and
raise it (e.g. `31536000`, 1 year) once HTTPS is there to stay. Browsers that saw
it keep forcing HTTPS until it expires.

**Switching from HTTPS to HTTP** (new test environment, moving to a VPN/LAN):
1. While HTTPS still works, send `Strict-Transport-Security: max-age=0`
   instead and remove the redirect. Browsers that visit get the rule erased.
   They only accept the header over HTTPS, so leave HTTPS up for at least as
   long as the old `max-age`, or until everyone has visited once.
2. Then set `$HTTPS_MODE = FALSE;` and turn HTTPS off.
3. A browser still stuck on HTTPS: clear the site in `chrome://net-internals/#hsts`
   (Chrome/Edge), or wait for the rule to expire.

## Security notes

- `Error()`, `Notice()` and `SendJSON()` end with `exit` and build the JSON
  with `json_encode`.
- `incls.dbh.php` opens the database connection once per request and reuses it.
- GET only for read-only actions (`$READ_ONLY_ACTIONS`: `session`, `verify`,
  `whoami`); everything else is POST only. API answers are never cached
  (`Cache-Control: no-store`).
- Session cookie: HTTPS-only in HTTPS mode (see "HTTPS" above).
- Session cookie: `httponly`, `SameSite=Strict`.
- Server errors only say "Server Error" (details go to the PHP error log),
  unless `$DEBUG_MODE` is on. Debug mode also turns the CSRF check off (responses to
  browsers then carry an `X-Dwight-Debug` header as a reminder): never leave it
  on where others can reach the portal.
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
  every action except the ones in `$READ_ONLY_ACTIONS` (`session`, `verify`,
  `whoami`, which change nothing). In `main.js`, every request goes through `CallApi()`, which
  attaches the token. Neither side relies on remembering to do it.
- **Program (`Authorization: Bearer <token>`), e.g. the C# bot:** no session
  and no CSRF (no cookies, so CSRF can't happen). Actions in
  `$SESSION_ONLY_ACTIONS` (login, logout…) aren't available this way.
  **Not implemented yet:** `CheckApiToken()` refuses every token. Its comment
  says what to add; `IsAuthorized()`, `GetUID()` and `GetUsername()` already
  handle both cases. `api/.htaccess` makes sure Apache passes the
  `Authorization` header to PHP.

## Adding actions

Add a `case` to the switch in `api/index.php`. It's POST only and CSRF is
already checked. If the action only reads data and should work with GET too,
add it to `$READ_ONLY_ACTIONS` in `rateandsecurity.php` and read its
parameters from `$_GET`. Either way, the action
only checks that the caller is logged in, with `IsAuthorized()` (works for both
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
