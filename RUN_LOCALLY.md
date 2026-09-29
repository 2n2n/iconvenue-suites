# Running iconvenue-suites Locally

Repo: https://github.com/2n2n/iconvenue-suites

Laravel 12 + PHP 8.2+ app (Tailwind CSS + Vite frontend, jQuery). Two ways to get it running locally — pick one.

## Prerequisites (both options)

- PHP 8.2+ with Composer
- Node.js + npm
- Git

Checked on this machine: **Node/npm are installed. PHP and Composer are NOT installed yet.** Install those first:

### Step 0 — Install PHP + Composer (Windows)

1. Install **XAMPP** (gives you PHP + MariaDB + Apache in one installer): https://www.apachefriends.org/download.html
   - Download the Windows installer, run it, accept the defaults, install to `C:\xampp`.
2. Add PHP to your PATH so you can type `php` in any terminal:
   - Open **Start → "Edit the system environment variables"** → **Environment Variables**.
   - Under "System variables", select `Path` → **Edit** → **New** → add `C:\xampp\php`.
   - Click OK on all dialogs, then **close and reopen your terminal/VS Code** (PATH changes only apply to new terminal windows).
3. Install **Composer**: https://getcomposer.org/Composer-Setup.exe — run it, it should auto-detect your `C:\xampp\php\php.exe`.
4. Verify both work — open a **new** terminal and run:
   ```powershell
   php -v
   composer -V
   ```
   Both should print a version number, not an error.

Once that's done, come back here and continue with Option A or B below.

## Option A — Quick start with SQLite (recommended for local dev)

This matches the project's `.env.example` defaults, so it's the least amount of setup. The repo is already cloned into this folder (`C:\Users\Marc Delicana\Desktop\Laravel Project`), so you can skip the `git clone` step and just run these from a terminal opened in this folder:

```powershell
composer install
npm install

copy .env.example .env

php artisan key:generate

# create the sqlite file the .env already points to
New-Item database\database.sqlite

php artisan migrate --seed
php artisan storage:link
```

Run everything (web server + queue worker + log tailer + Vite) with one command:

```bash
composer run dev
```

Or run the pieces separately:

```bash
php artisan serve       # http://localhost:8000
npm run dev             # Vite dev server (asset hot-reload)
```

## Option B — Match the README exactly (MariaDB/MySQL via XAMPP)

Use this if you want parity with what the README documents, or you want the sample data in the included `icon (3).sql` dump.

1. Start XAMPP → Apache + MySQL/MariaDB.
2. Create a database (via phpMyAdmin or CLI), e.g. `venue_booking`.
3. Edit `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=venue_booking
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Either:
   - **Fresh via migrations:** `php artisan migrate --seed`, or
   - **Import the existing sample data:** import `icon (3).sql` into `venue_booking` via phpMyAdmin (Import tab) or:
     ```bash
     mysql -u root venue_booking < "icon (3).sql"
     ```
5. Then:
   ```bash
   composer install
   npm install
   php artisan key:generate
   php artisan storage:link
   npm run build     # or `npm run dev` while actively developing
   php artisan serve
   ```

There's also `setup.bat` (Windows) which automates composer install → migrate → seed → storage:link → serve, but it assumes `.env` is already configured for your DB.

## Access the app

- Public site: http://localhost:8000
- Login: http://localhost:8000/login

### Default seeded credentials

| Role  | Email                | Password  |
|-------|-----------------------|-----------|
| Admin | admin@iconvenue.com   | admin123  |
| Staff | staff@iconvenue.com   | staff123  |

These come from `database/seeders/DatabaseSeeder.php` — change them immediately if this ever touches a real environment.

## Common commands

```bash
php artisan migrate:fresh --seed   # wipe & rebuild the DB
php artisan cache:clear
php artisan config:clear
php artisan storage:link           # re-link if uploaded images 404
php artisan test                   # run the test suite
```

## Troubleshooting

- **Database connection error** → confirm MariaDB/MySQL is running (Option B) or that `database/database.sqlite` exists (Option A), and double-check `.env` values.
- **Images not showing** → run `php artisan storage:link`.
- **Blank/unstyled page** → run `npm install && npm run dev` (or `npm run build`) so Vite compiles Tailwind assets.
- **Permission errors (Mac/Linux)** → `chmod -R 775 storage bootstrap/cache`.
