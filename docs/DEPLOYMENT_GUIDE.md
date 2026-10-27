# Deployment Guide — getting a live link

Two options. Option 1 is the fastest way to show the project to someone remote; Option 2 is a real, always-on site.

## Before either option (security checklist)
1. Change the organizer password: `php tools/set_admin_password.php organizer`.
2. Delete the demo vendor and demo visitors (phpMyAdmin → SQL):
   ```sql
   UPDATE vendors SET username = NULL, password_hash = NULL WHERE username = 'ranjit';
   DELETE FROM visitors WHERE pass_id IN ('FEST-DEMO0001','FEST-DEMO0002');
   ```
3. Use **HTTPS** only. The session cookie automatically gets the `Secure` flag when HTTPS is detected.
4. Never upload `tests/` if you do not want attackers reading your test data — the `.htaccess` files already deny web access to `config`, `database`, `includes`, `tools`, `tests` and `docs` on Apache hosts.

## Option 1 — Temporary public link from your own laptop (demo/interview)
Keep XAMPP running and use a tunnel that gives you an HTTPS address:
```
# Cloudflare Tunnel (free)                      # or ngrok (free account)
cloudflared tunnel --url http://localhost:80    ngrok http 80
```
Open `https://<random-name>.trycloudflare.com/food-festival-fiesta/index.php`.
The app detects the tunnel: it uses the real visitor IP for rate limiting, marks cookies `Secure`, and hides PHP error details from outsiders.
Stop the tunnel when you finish — your laptop is serving the site.

## Option 2 — Free shared hosting (example: InfinityFree)
1. Create an account and a hosting account; note the **MySQL host, database name, username, password** shown in the panel (names are prefixed, e.g. `if0_123456_ffiesta`).
2. Open **phpMyAdmin** from the panel, select your database, **Import** `database/schema_hosting.sql`
   (this version has no `CREATE DATABASE` / `USE` lines, which shared hosts forbid).
3. Edit `config/db.php` — put the four values from step 1 into `$DB_HOST`, `$DB_USER`, `$DB_PASS`, `$DB_NAME`.
4. Upload everything inside the project folder to the site's `htdocs` folder (File Manager or FTP such as FileZilla).
5. Because shared hosts have no command line, set the organizer password like this:
   - On your own PC run: `php -r "echo password_hash('YourStrongPassword!', PASSWORD_DEFAULT);"`
   - In the host's phpMyAdmin run: `UPDATE admins SET password_hash = '<paste hash>' WHERE username = 'organizer';`
6. Visit your site's URL, sign in as organizer, and run through the walkthrough in `SETUP_GUIDE.md` step 6.

## After it is live
- Check the **Recent activity** panel on the organizer dashboard — it records every sign-in, failed attempt, approval, check-in and export with the IP address.
- Back up the database regularly (phpMyAdmin → Export).
