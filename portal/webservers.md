# Web server settings for production

What to configure in Apache, nginx, Caddy or IIS so the portal runs safely.
PHP leaves these to the web server: see "HTTPS" in the README for why.

The examples use `portal.example.org`, with `dwight/` inside the document root
(so the portal is at `https://portal.example.org/dwight/`). Change both to yours.

## What every setup needs

| | Why |
|---|---|
| **HTTPS, and HTTP redirected to HTTPS** | Passwords and session cookies must not travel unencrypted. Only the web server can redirect `login.html` and the other static files. |
| **HSTS header** (`Strict-Transport-Security: max-age=86400`) | Browsers then use HTTPS even if someone types `http://`. Start at 1 day; raise to `31536000` (1 year) once HTTPS is there to stay. |
| **`/dwight/api/incls/` blocked** | It holds the DB password. The `.htaccess` there only works on Apache: every other server needs its own rule. Keep `setup/` out of the document root entirely. |
| **`Authorization` header passed to PHP** | The bot will authenticate with `Authorization: Bearer …`. Apache needs `api/.htaccess` (or the same line in the config); the others pass it by default. |
| **The real client IP in PHP** | Only when PHP is behind a proxy. PHP reads `REMOTE_ADDR` for the per-IP login limit; behind a proxy that's the proxy's IP, so **every user would share one limit**: 20 wrong passwords from anyone in 15 minutes would lock everybody out. The web server running PHP must restore the client IP from `X-Forwarded-For` (see "Behind a proxy"). |
| **`$HTTPS_MODE = TRUE;`** in `api/incls/incls.dbh.php` | Users are on HTTPS, so the session cookie is HTTPS-only. Also behind a proxy, even though PHP itself sees HTTP. |
| **`$DEBUG_MODE = FALSE;`** | Debug mode shows PHP error details and turns the CSRF check off. |

Recommended as well, on the HTTPS responses:

```
X-Content-Type-Options: nosniff     # browsers don't guess file types
X-Frame-Options: DENY               # the login page can't be shown inside another site's frame (clickjacking)
```

## Two kinds of setup

- **Single server:** the web server handles HTTPS and runs PHP. Use the
  "Serving PHP directly" example for your server.
- **Proxy in front:** a proxy (often nginx, Caddy, IIS/ARR, or a service like
  Cloudflare) handles HTTPS and forwards to the web server running PHP over plain
  HTTP. Configure HTTPS, the redirect, HSTS and the other headers **on the
  proxy only**, then the backend as in "Behind a proxy". Bind the backend to
  `127.0.0.1` or a private address so it can't be reached around the proxy.

---

## Apache

Modules: `ssl`, `headers`, `setenvif`, and `remoteip` when behind a proxy
(`a2enmod ssl headers setenvif remoteip` on Debian/Ubuntu; on XAMPP, uncomment
them in `httpd.conf`).

### Serving PHP directly

```apache
<VirtualHost *:80>
    ServerName portal.example.org
    Redirect permanent / https://portal.example.org/
</VirtualHost>

<VirtualHost *:443>
    ServerName portal.example.org
    DocumentRoot /var/www/html

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/portal.example.org.crt
    SSLCertificateKeyFile /etc/ssl/private/portal.example.org.key

    Header always set Strict-Transport-Security "max-age=86400"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"

    # The .htaccess files in api/ and api/incls/ need these to work
    <Directory /var/www/html/dwight>
        AllowOverride AuthConfig FileInfo
    </Directory>
</VirtualHost>
```

### Behind a proxy

Listen only where the proxy can reach it, and trust its `X-Forwarded-For`:

```apache
Listen 127.0.0.1:8080

<VirtualHost 127.0.0.1:8080>
    DocumentRoot /var/www/html

    # Client IP from the proxy (put the proxy's address here; RemoteIPTrustedProxy for a public one)
    RemoteIPHeader X-Forwarded-For
    RemoteIPInternalProxy 127.0.0.1

    <Directory /var/www/html/dwight>
        AllowOverride AuthConfig FileInfo
    </Directory>
</VirtualHost>
```

### As the proxy

Modules: `proxy`, `proxy_http`, plus `ssl` and `headers`. Apache adds
`X-Forwarded-For` by itself.

```apache
<VirtualHost *:443>
    ServerName portal.example.org
    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/portal.example.org.crt
    SSLCertificateKeyFile /etc/ssl/private/portal.example.org.key

    Header always set Strict-Transport-Security "max-age=86400"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
    ProxyPass        / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
</VirtualHost>
```

Plus the port-80 redirect from "Serving PHP directly".

---

## nginx

### Serving PHP directly (with PHP-FPM)

```nginx
server {
    listen 80;
    server_name portal.example.org;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name portal.example.org;
    ssl_certificate     /etc/ssl/certs/portal.example.org.crt;
    ssl_certificate_key /etc/ssl/private/portal.example.org.key;

    root  /var/www/html;
    index index.html index.php;

    add_header Strict-Transport-Security "max-age=86400" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;

    # Never serve the DB settings (^~ so the .php rule below can't win)
    location ^~ /dwight/api/incls/ {
        deny all;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # your PHP-FPM socket
    }
}
```

nginx passes the `Authorization` header to PHP-FPM by itself.

A note on `add_header`: a `location` that has its own `add_header` loses the
ones from `server`. If you add one in a location, repeat the three lines there.

### Behind a proxy

Same as above but `listen 127.0.0.1:8080;` without TLS, no redirect server, and
**none of the `add_header` lines**: the proxy sends them, and the browser would
get each one twice. Keep the `incls` rule and the PHP location, and restore the
client IP:

```nginx
    set_real_ip_from 127.0.0.1;          # the proxy's address
    real_ip_header   X-Forwarded-For;
```

### As the proxy

```nginx
server {
    listen 443 ssl;
    server_name portal.example.org;
    ssl_certificate     /etc/ssl/certs/portal.example.org.crt;
    ssl_certificate_key /etc/ssl/private/portal.example.org.key;

    add_header Strict-Transport-Security "max-age=86400" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host              $host;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Plus the port-80 redirect server from "Serving PHP directly".

---

## Caddy

Caddy gets certificates and redirects HTTP to HTTPS by itself (with a `308`).
It does **not** add HSTS: set it with `header`.

### Serving PHP directly (with PHP-FPM)

```caddy
portal.example.org {
    root * /var/www/html

    header Strict-Transport-Security "max-age=86400"
    header X-Content-Type-Options "nosniff"
    header X-Frame-Options "DENY"

    # Never serve the DB settings
    @incls path /dwight/api/incls/*
    respond @incls 403

    php_fastcgi unix//run/php/php8.3-fpm.sock    # your PHP-FPM socket
    file_server
}
```

### As the proxy

Caddy sets `X-Forwarded-For` and `X-Forwarded-Proto` by itself:

```caddy
portal.example.org {
    header Strict-Transport-Security "max-age=86400"
    header X-Content-Type-Options "nosniff"
    header X-Frame-Options "DENY"

    reverse_proxy 127.0.0.1:8080
}
```

Behind a Caddy proxy, run PHP on Apache or nginx configured as in their
"Behind a proxy" sections.

---

## IIS

**Not tested** (IIS doesn't run on Linux, where the other examples were tested;
these settings come from the IIS documentation). Needs the
**URL Rewrite** module, and **Application Request Routing (ARR)** to be a proxy.
Add the HTTPS binding and certificate in IIS Manager → Site → Bindings.

### Serving PHP directly

`web.config` in the site root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="HTTP to HTTPS" stopProcessing="true">
          <match url="(.*)" />
          <conditions>
            <add input="{HTTPS}" pattern="off" />
          </conditions>
          <action type="Redirect" url="https://{HTTP_HOST}/{R:1}" redirectType="Permanent" />
        </rule>
      </rules>
    </rewrite>
    <httpProtocol>
      <customHeaders>
        <add name="Strict-Transport-Security" value="max-age=86400" />
        <add name="X-Content-Type-Options" value="nosniff" />
        <add name="X-Frame-Options" value="DENY" />
      </customHeaders>
    </httpProtocol>
    <security>
      <requestFiltering>
        <hiddenSegments>
          <!-- Never serve the DB settings -->
          <add segment="incls" />
        </hiddenSegments>
      </requestFiltering>
    </security>
    <defaultDocument>
      <files>
        <add value="index.php" />
      </files>
    </defaultDocument>
  </system.webServer>
</configuration>
```

Check that the `Authorization` header reaches PHP (see "Checking the setup"):
with Windows or Basic authentication enabled on the site, IIS uses the header
itself.

### As the proxy (ARR)

Enable the proxy in IIS Manager → server → Application Request Routing Cache →
Server Proxy Settings → **Enable proxy** (ARR adds `X-Forwarded-For` there).
Then in the proxy site's `web.config`, inside `<rewrite><rules>` after the
HTTPS redirect rule:

```xml
        <rule name="Proxy to backend" stopProcessing="true">
          <match url="(.*)" />
          <serverVariables>
            <set name="HTTP_X_FORWARDED_PROTO" value="https" />
          </serverVariables>
          <action type="Rewrite" url="http://127.0.0.1:8080/{R:1}" />
        </rule>
```

`HTTP_X_FORWARDED_PROTO` must be allowed first: URL Rewrite → View Server
Variables → Add. Keep the `customHeaders` from above for HSTS.

Behind an IIS proxy, run PHP on Apache or nginx configured as in their
"Behind a proxy" sections.

---

## Checking the setup

Replace `portal.example.org` and run from a machine that isn't the server:

```bash
# 1. HTTP redirects to HTTPS: expect 301 (308 on Caddy) and a https:// Location
curl -sI http://portal.example.org/dwight/login.html | grep -iE "^HTTP|^location"

# 2. Headers on HTTPS: expect the three headers, each exactly once
curl -sI https://portal.example.org/dwight/login.html | grep -iE "strict-transport|x-content-type|x-frame"

# 3. DB settings not served: expect 403 or 404, never PHP code or 200
curl -s -o /dev/null -w "%{http_code}\n" https://portal.example.org/dwight/api/incls/incls.dbh.php

# 4. Authorization header reaches PHP: expect "Not Authorized - Invalid API token"
#    (just "Not Authorized" means the header never reached PHP)
curl -s -H "Authorization: Bearer test" "https://portal.example.org/dwight/api/?action=whoami"

# 5. Session cookie: expect __Secure-DWIGHTSESSID with "secure"
#    (a GET, not curl -I: the API refuses HEAD requests before creating a session)
curl -s -D - -o /dev/null "https://portal.example.org/dwight/api/?action=session" | grep -i set-cookie
```

6. **Client IP**, behind a proxy: try a wrong password once from your machine,
   then look at `dwight-login-attempts` in phpMyAdmin. The `IP` column must be
   your machine's address, not the proxy's (`127.0.0.1` or similar).
