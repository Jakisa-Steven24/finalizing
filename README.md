# MIU Student Information Management System

A **procedural PHP + MySQL** Student Information Management System (SIMS) for Metropolitan International University. It provides staff-managed academic data, marks, finance, reports and exports while giving students strictly read-only access to their own linked record.

> **Fresh schema notice:** `database.sql` is a clean-install script and drops the application tables it rebuilds. Back up any existing data before importing it.

## What is implemented

- **Three roles:** `superadmin`, `admin`, and `student`.
  - **Students** can only view and export their own profile, marks, and payments. Their dashboard never queries institution-wide counts.
  - **Admins** manage student information, departments, lecturers, courses, units, enrollments, registrations, fees, payments, marks, reports and student accounts.
  - **Superadmins** have all admin capabilities and are the **only** role allowed to create/manage administrator or superadmin accounts and view the audit log.
- A one-time `setup_admin.php` bootstrap that creates the first **superadmin**. It cannot create a second one.
- Student sign-up directly from the login page. Sign-up only creates a `student` role after registration number, existing university email, and date of birth match a pre-created student record. It **cannot** create an admin account.
- Full staff CRUD on every management page: **create, view a single record, search/filter/sort/paginate, edit and delete** (with a confirmation prompt) for all ten academic and financial entities, including **Marks**, plus role-aware user-account management.
- Filterable, searchable, sortable and paginated lists on every management page; scoped dashboard, report, audit-log and personal-record filters.
- Authoritative server-side validation that lists every problem at once, with clear duplicate messages (codes, emails, phone and reference numbers) and a re-filled form. Matching HTML patterns highlight a mistyped field while typing. All visible inputs have placeholders or an explicit select prompt.
- Filterable staff reports with printable layout and CSV download. Students can export only their own data.
- Superadmin audit trail for create/update/delete/account actions, authentication and exports.
- Detailed technical documentation and a Mermaid ERD: [`docs/SYSTEM_DOCUMENTATION.md`](docs/SYSTEM_DOCUMENTATION.md) and [`docs/ERD.md`](docs/ERD.md).

## Requirements

- PHP **8.1+** with `mysqli` and the usual `mysqlnd` driver enabled
- MySQL **8.0+** or a compatible current MariaDB release
- Apache, Nginx + PHP-FPM, XAMPP, WAMP, Laragon, or equivalent

## Installation

1. Place the project in the web server's PHP directory.
2. Create a dedicated, least-privilege MySQL account for the application. Do **not** use the `root` account in production.
3. Import `database.sql` with phpMyAdmin or MySQL CLI. It creates/uses database `miu`.
4. Configure database values using environment variables (`MIU_DB_HOST`, `MIU_DB_NAME`, `MIU_DB_USER`, `MIU_DB_PASS`) or update `config.php` for local development.
5. In local development only, set `MIU_APP_DEBUG=1` to show safe diagnostic details. Keep it disabled in production.
6. Visit `setup_admin.php` once and create the initial superadmin.
7. **Immediately delete or web-server-block `setup_admin.php`.**
8. Sign in through `login.php`.
9. Create records in dependency order: **department → lecturer → course → course unit → student → student login/marks/finance**.

## Security controls

- Prepared statements for all values originating from users; entity/table/column names are a reviewed allow-list in `includes/entities.php`.
- CSRF tokens on every state-changing POST request.
- Escaped output (`htmlspecialchars`) to mitigate XSS.
- Passwords use PHP `password_hash()` / `password_verify()`; passwords must have 8–128 characters, upper/lower case, number, symbol and no whitespace.
- Session ID regeneration after login, strict cookie mode, HTTP-only cookies, `SameSite=Lax`, HTTPS-aware `Secure` cookies, inactivity timeout and security headers.
- Failed account sign-ins are locked for 15 minutes after five unsuccessful attempts. Inactive/deleted-role sessions are re-checked on each protected request.
- Role checks are enforced on the server, not only hidden in navigation.
- CSV formula-injection mitigation prefixes formula-like exported cell values.
- Audit records support support/debug investigation without displaying raw database exceptions to end users.

See the full deployment checklist in [`docs/SYSTEM_DOCUMENTATION.md`](docs/SYSTEM_DOCUMENTATION.md).

## Main files

| File | Purpose |
|---|---|
| `database.sql` | Fresh database schema, constraints, marks, account and audit tables |
| `config.php` | Database, debug, timezone and session settings |
| `records.php` | Allow-listed staff CRUD, validation, search/filter/sort/pagination |
| `users.php` | Role-aware account CRUD; superadmin controls privileged roles |
| `signup.php` | Verified student-only self-registration |
| `reports.php`, `export.php` | Filtered staff reports, print and CSV exports |
| `my_record.php`, `my_export.php` | Student-scoped, read-only records and personal export |
| `audit.php` | Superadmin troubleshooting/audit history |
| `includes/` | Procedural security, DB, entity and reporting helpers |
| `assets/app.js` | Print button and delete confirmations (no inline scripts, as required by the CSP) |

## Operational notes

- The system deliberately does **not** ship with a default username/password. The first superadmin is installed locally through the one-time bootstrap page.
- An account with role `student` must be linked to one existing student record. One student record can have only one login.
- A mark is accepted only when its course unit belongs to that student's registered course. Grade is calculated server-side from score and cannot be typed by an admin.
- Database referential constraints protect records that are in use. When deletion is blocked, the message names what is still using the record (for example "1 lecturer and 2 courses"); update/remove those records first rather than disabling foreign keys.
- Code style: one statement per line (no one-line `if` or block bodies), long SQL split one clause per line, and one CSS declaration per line, so error messages and debuggers always point at a single statement.
- For production, force HTTPS at the web server, restrict database permissions, move secrets out of the repository, back up/test restores, rotate logs, protect the bootstrap file, and have a qualified reviewer test the deployment.
