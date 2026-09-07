# Student Planner (PHP + MySQL)

A full student planner web app — Tasks/To-Do, Class Schedule, Subjects,
Notes, Calendar and Analytics — built on the same dark "space" glass-UI
theme as the original login screen.

## 1. Requirements
- PHP 7.4+ (with `mysqli` extension)
- MySQL / MariaDB
- Apache/Nginx or PHP's built-in server

## 2. Create the database — MySQL command

Import the whole schema + sample data in one shot:

```bash
mysql -u root -p < student_planner.sql
```

(Enter your MySQL root password when prompted.) This creates the
`student_planner` database and all 6 tables: `login_accounts`,
`subjects`, `tasks`, `schedule`, `notes`, `events`.

Or, from inside the MySQL shell:

```sql
SOURCE /full/path/to/student_planner.sql;
```

Or via phpMyAdmin: create/select a database → **Import** tab → choose
`student_planner.sql` → Go.

## 3. Configure the connection

Edit `db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_planner');
```

## 4. Run it

```bash
php -S localhost:8000
```

then open `http://localhost:8000/login.php` — or drop the folder into
your `htdocs` / `www` folder for XAMPP/WAMP/Laragon.

## 5. Log in

Demo accounts (from the sample data):

| Username | Password  | Role  |
|----------|-----------|-------|
| admin    | admin123  | admin |
| juan     | juan123   | student |

Or click **Create Account** — the very first account ever registered
is auto-approved as admin; every account after that needs admin
approval via **Manage Accounts**.

## Pages
- `index.php` — Dashboard (stats, today's classes, upcoming tasks, quick add)
- `tasks.php` — Tasks / To-Do (add, edit, delete, priority, status, filters)
- `schedule.php` — Weekly class timetable
- `subjects.php` — Manage subjects/courses
- `notes.php` — Pinned notes per subject
- `calendar.php` — Month calendar of tasks & events
- `analytics.php` — Completion rate & productivity charts
- `accounts.php` — Admin: approve/reject/manage users
