# Dwight

Moderation portal for a set of Telegram groups: one ban list shared across
all of them.

| Folder | What |
|---|---|
| `portal/` | PHP moderation portal (XAMPP / MariaDB). See `portal/README.md`. |

**Branches:** `main` holds stable releases, and development happens on `dev`.

**Status:**
- **Login:** works.
- **Not implemented yet:** API-token access (every token is refused) and OTP.

`portal/setup/dwight-schema.sql` is the current database structure.
