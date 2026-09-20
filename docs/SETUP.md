# Setup and Deployment

Written for: whoever installs and runs this system — a developer setting it up locally, or
café staff following it once at deployment.

---

## Requirements

| | Minimum | Verified against |
|---|---|---|
| PHP | 8.1 | 8.3.33 |
| MySQL or MariaDB | MySQL 8.0 / MariaDB 10.4 | MariaDB 10.11.9 |
| Web server | Apache with `mod_rewrite` | Apache (XAMPP), PHP built-in server |

**PHP extensions:** `pdo_mysql`, `mbstring`, `json`, `session`, `fileinfo`.
All are enabled by default in XAMPP.

No Composer. No npm. No CDN. The app runs offline once installed.

---

## Option A — XAMPP (recommended for the defense)

This is what Chapter II specifies and what most panels expect.

### 1. Install XAMPP

Download from <https://www.apachefriends.org/> and install with **Apache** and **MySQL**
ticked.

> **Note on this machine:** the existing `C:\xampp` installation is incomplete — `C:\xampp\php`
> contains only an `ext` folder and there is no `C:\xampp\mysql\bin`, only a
> `mysql_backup_20260311_132953` folder. Reinstall XAMPP before using this option, or use
> Option B below.

### 2. Place the project

Copy the whole project folder into `C:\xampp\htdocs\`, for example:

```
C:\xampp\htdocs\pickleball\
```

### 3. Start Apache and MySQL

Open the XAMPP Control Panel and press **Start** on both.

### 4. Configure

```bat
cd C:\xampp\htdocs\pickleball
copy config\config.example.php config\config.php
```

Open `config/config.php`. The defaults match a stock XAMPP install
(`127.0.0.1`, user `root`, blank password), so usually nothing needs changing.

### 5. Create the database

```bat
C:\xampp\php\php.exe database\install.php
C:\xampp\php\php.exe database\seed.php
```

`install.php` creates the `pickleball_mdc` database and all 23 tables.
`seed.php` loads the demo data listed in the README.

Prefer phpMyAdmin? Import `database/schema.sql` at <http://localhost/phpmyadmin>, then still
run `seed.php` from the command line — it hashes the demo passwords at run time, which a
plain `.sql` file cannot do.

### 6. Open it

<http://localhost/pickleball/public/>

---

## Option B — portable PHP + MariaDB (no installer, no admin rights)

Useful when XAMPP is broken or you cannot install software. This is how the build was
verified.

### 1. PHP

Download the **NTS x64** zip from <https://downloads.php.net/~windows/releases/>
(for example `php-8.3.33-nts-Win32-vs16-x64.zip`) and extract to `C:\devtools\php`.

```bat
cd C:\devtools\php
copy php.ini-development php.ini
```

Edit `php.ini` and uncomment (remove the leading `;`):

```ini
extension_dir = "ext"
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
date.timezone = Asia/Manila
```

Verify:

```bat
C:\devtools\php\php.exe -v
C:\devtools\php\php.exe -m
```

### 2. MariaDB

Download the **winx64 zip** from <https://archive.mariadb.org/> (for example
`mariadb-10.11.9-winx64.zip`) and extract to `C:\devtools\mariadb`.

Initialise the data directory once:

```bat
C:\devtools\mariadb\bin\mysql_install_db.exe --datadir=C:\devtools\mariadb-data --password=""
```

Start the server (leave this window open):

```bat
C:\devtools\mariadb\bin\mysqld.exe --datadir=C:\devtools\mariadb-data --port=3306 --console
```

### 3. Install and run

```bat
cd <project folder>
copy config\config.example.php config\config.php
C:\devtools\php\php.exe database\install.php
C:\devtools\php\php.exe database\seed.php

cd public
C:\devtools\php\php.exe -S 127.0.0.1:8000
```

Open <http://127.0.0.1:8000>.

---

## Configuration reference

All settings live in `config/config.php`.

| Key | Default | Meaning |
|-----|---------|---------|
| `db.host` / `db.port` | `127.0.0.1` / `3306` | Database server |
| `db.database` | `pickleball_mdc` | Database name |
| `db.username` / `db.password` | `root` / *(blank)* | XAMPP defaults |
| `app.base_url` | `'auto'` | Sub-directory the app is served from. `'auto'` detects it. Set explicitly (e.g. `/pickleball/public`) if detection fails behind a proxy. |
| `app.timezone` | `Asia/Manila` | Used for every date and time |
| `app.debug` | `true` | **Set to `false` before deployment** — shows full stack traces when on |
| `booking.slot_minutes` | `60` | Length of a generated slot |
| `booking.max_advance_days` | `30` | How far ahead customers may book |
| `booking.min_advance_hours` | `2` | Slots closer than this cannot be booked online |
| `booking.cancel_cutoff_hours` | `12` | Customers cannot cancel online inside this window |
| `booking.max_open_reservations` | `5` | Cap on simultaneous open requests per customer |
| `features.enhancements` | `true` | Master switch for the out-of-scope extras — see [ENHANCEMENTS.md](ENHANCEMENTS.md) |

There is no `.env` file and no environment variables; everything is in this one PHP file.

---

## First tasks after installing on a real machine

If you start with an empty database rather than the demo data:

1. Log in as `admin`.
2. **Settings** → set the café name, address, contact number and operating hours.
   Operating hours decide which slots the generator creates, so get these right first.
3. **Courts** → add each court with its hourly rate.
4. **Function halls** → add each hall with its rate, capacity and minimum booking.
5. **Facility schedules** → pick each facility and generate slots for the next 30 days.
   *Nothing can be booked until this step is done.*
6. **User accounts** → create a `staff` account per counter employee. Do not share the
   administrator login.
7. Change the `admin` password.

Re-run step 5 monthly, or whenever you change the operating hours.

---

## Testing

The checks run during the build, for reference in Chapter III's testing section:

| Check | How |
|-------|-----|
| Syntax, all files | `for /r %f in (*.php) do php -l "%f"` |
| Page load, every route | Log in as each role and request each page; all returned HTTP 200 |
| Booking and double-booking | Two customers submit the same slot; the second is rejected and the database holds exactly one claim |
| Approval workflow | Admin approves a pending request; status changes and the customer is notified |
| Walk-in registration | Creates a `players` row with `is_walk_in = 1` and `user_id NULL` |
| Match generation | Locks a pool, draws matches, assigns players to teams |
| Payment separation of duties | Staff records a payment; staff receives 403 on verify; admin verifies successfully |
| Role enforcement | Staff receive 403 on `/admin/users`, `/admin/settings`, `/admin/logs`, `/admin/courts`; customers receive 403 on `/admin`; guests are redirected to login |
| CSRF | A `POST` with an invalid token is rejected |

Module coverage matches the Testing section of Chapter III: Registration, Matching,
Reservation, Function Hall, Payment and Reporting were each exercised in isolation and then
end to end.

---

## Troubleshooting

**"Missing config/config.php"**
Copy `config/config.example.php` to `config/config.php`.

**"Database connection failed"**
MySQL is not running, or the credentials are wrong. Start MySQL in the XAMPP Control Panel
and check `db.username` / `db.password`.

**CSS and links are broken, or every link 404s**
`base_url` detection failed. Set it explicitly in `config/config.php`, e.g.
`'base_url' => '/pickleball/public'`.

**Every URL except the home page returns 404 (Apache)**
`mod_rewrite` is off. In `C:\xampp\apache\conf\httpd.conf` uncomment
`LoadModule rewrite_module modules/mod_rewrite.so`, ensure `AllowOverride All` is set for
`htdocs`, and restart Apache.

**"No slots for this date"**
Schedules have not been generated for that facility. Go to **Facility schedules**, choose the
facility, and generate a range.

**A slot cannot be blocked**
It is already reserved. Cancel the reservation first — that notifies the customer, which
silently blocking would not.

**Uploads fail**
`public/uploads/` must be writable by the web server, and the file must be a real JPG, PNG
or WEBP under 2 MB (3 MB for event images). The check reads the image header, so renaming a
file's extension will not get past it.

---

## Before deploying publicly

1. Set `'debug' => false` in `config/config.php`. With it on, stack traces are shown to
   visitors.
2. Change the `admin` and `frontdesk` passwords, and delete the demo customer accounts.
3. Give MySQL's `root` user a password, or create a dedicated user with rights to
   `pickleball_mdc` only.
4. Serve over HTTPS. Session cookies set the `secure` flag automatically once HTTPS is on.
5. Point the web server's document root at `public/`, never at the project root — `config/`,
   `database/` and `storage/` must not be reachable over the web.
6. Back up the database on a schedule. `mysqldump pickleball_mdc > backup.sql`.
