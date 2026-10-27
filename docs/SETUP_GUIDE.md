# Setup Guide — running Food Festival Fiesta on XAMPP (Windows)

Every step says what to do and why.

## 0. Which path are you on?
- **A. First time / starting clean** → follow steps 1–6 and import `database/schema.sql`.
- **B. You already ran version 1 and want to keep your data** → follow steps 1–3, then in step 4 import
  `database/upgrade_v2.sql` instead (run it **once only**).

## 1. Put the project in `htdocs`
Extract the zip and move the folder `food-festival-fiesta` to `C:\xampp\htdocs\`.
If you are upgrading, replace the old folder (keep a copy of the old one first).
**Why:** Apache only serves files from `htdocs`. Anywhere else, the browser cannot reach your PHP files.

## 2. Start Apache and MySQL
XAMPP Control Panel → **Start** on both. If MySQL turns red with "Port 3306 in use", another MySQL is running;
stop it (Task Manager → Details → `mysqld.exe`) or change XAMPP's MySQL port.
**Why:** Apache runs the PHP code; MySQL stores the data. Both must be on.

## 3. Check the connection file
Open `config/db.php`. XAMPP defaults (`root`, empty password, `localhost`) need no change.
**Why:** this is the only place PHP learns how to reach the database.

## 4. Create / upgrade the database
Open `http://localhost/phpmyadmin` → **Import** → choose a file → **Go**:
- Path A: `database/schema.sql` — **warning: it drops and recreates every table.**
- Path B: `database/upgrade_v2.sql` — keeps your stalls, vendors and reviews, adds the new features.

**Why:** MySQL starts empty. The script builds the 8 tables and loads demo data.

## 5. Open the site
`http://localhost/food-festival-fiesta/index.php`
**Why this URL:** it goes through Apache, which executes the PHP. Double-clicking a `.php` file opens it as plain text.

## 6. Quick walkthrough (5 minutes)
1. **Vendor Portal → Register a new stall.** Pick a username and a password with letters and numbers. You are signed in automatically and see "Awaiting organizer approval".
2. Open **Stalls**: your stall is **not** visible yet (pending).
3. **Organizer** → sign in (`organizer` / `organizer2026`) → **Applications & stalls** → **Approve**.
4. **Stalls** now shows your stall with its new number.
5. **Get Pass** → register → a pass with a QR code appears. Copy the pass ID.
6. **Organizer → Gate check-in** → paste the pass ID → **Check in**. Try it again: it is refused (one use only).
7. **Rate a Stall** → paste the pass ID → review a stall. The stall's rating updates. Reviewing the same stall again with that pass is refused.
8. **Organizer** dashboard: stats, rating by cuisine, recent activity, **Download report (CSV)**.

## 7. Change the default organizer password (do this before showing it to anyone)
In the XAMPP Shell (button on the control panel), from the project folder:
```
php tools/set_admin_password.php organizer
```
Type a new password (10+ characters). **Why:** default demo credentials are public knowledge.

## 8. Run the automated tests (optional but impressive)
With a freshly imported `schema.sql`:
```
php tests/integration_test.php http://localhost/food-festival-fiesta
```
You should see `TOTAL 74   PASSED 74   FAILED 0`.

## Troubleshooting
| You see | Meaning |
|---|---|
| "The service is temporarily unavailable…" | MySQL is off, or the database was not imported, or `config/db.php` credentials are wrong |
| Blank page / PHP error text | Folder nested one level too deep (should be `htdocs\food-festival-fiesta\index.php`) |
| "Your session expired or the form was invalid" | The page was open too long or cookies are blocked; reload the page and try again |
| "Too many failed attempts" | 5 wrong sign-ins locked that account for 15 minutes; wait, or in phpMyAdmin run `DELETE FROM login_attempts;` |
| Upgrade error "Duplicate column name" | `upgrade_v2.sql` was already run once; do not run it again |
