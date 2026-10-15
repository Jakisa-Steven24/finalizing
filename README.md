# MIU Student Management System

A procedural PHP + MySQL web application based on the supplied MIU SQL schema. It adds a responsive dashboard, administrator/student login roles, CRUD, searching, server/client-side validation, regular-expression input patterns, CSRF protection, password hashing, reports, and setup documentation.

## Features
- Dashboard counts for students, departments, lecturers and courses.
- Admin-only CRUD pages for students, departments, lecturers, courses, course units, enrollments, registrations, fee types and payments.
- Search across fields on each record page (up to 200 rows shown per query).
- Role-based access: administrators manage data; student accounts can view their own linked record.
- Prepared SQL statements for user-provided values, escaped HTML output, CSRF tokens for POST operations, secure password hashing and session ID regeneration at login.
- Validation for required fields, email, phone numbers, numeric values, allowed options, and regex patterns.
- Summary reports, students-by-course and payments-by-fee-type reports; print report action.
- Helpful placeholders and responsive layout with footer on all authenticated pages.

## Requirements
- PHP 8.1+ with `mysqli` enabled
- MySQL 8.0+ or a compatible MariaDB version
- XAMPP/WAMP/Laragon or a similar local PHP/MySQL environment

## Installation (XAMPP on Windows)
1. Copy the `miu_project` folder into `C:\xampp\htdocs\` and rename it `miu_project` if needed.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database.sql`. The script creates/uses database `miu` and creates the `app_users` table in addition to the original tables.
   - The original SQL dump has empty data tables; no sample students/courses are included.
5. Open `config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` for your MySQL setup. Default XAMPP credentials are `root` with an empty password.
6. Visit `http://localhost/miu_project/setup_admin.php`, create the first administrator, then **delete `setup_admin.php`** from the project folder immediately.
7. Visit `http://localhost/miu_project/login.php` and sign in.
8. Add a department first, then a lecturer, then a course, course units, students, fee types and related records. This order respects the database foreign keys.

## Notes about the supplied schema
- The database is named `miu` in the SQL file.
- The supplied `lecturer.lecturer_id` and `enrollment.enrollment_id` are not auto-increment columns. The forms therefore ask an administrator to enter these numeric IDs.
- `student` has a required `course_id`, so create a course before creating a student.
- `registration` and `enrollment` are separate tables in the supplied database and are both included in the interface.
- `app_users` is a new application-authentication table. It does not replace the academic tables.

## Security and deployment
This project is intended for local coursework/demo use. Before public hosting, enable HTTPS, use a least-privilege MySQL account, set a strong database password, disable public access to installation files, configure secure cookie settings, add backups, and perform a security review. Never leave `setup_admin.php` on a live server.

## Main files
- `database.sql` — database schema/import file
- `config.php` — database connection settings
- `login.php`, `logout.php` — authentication
- `index.php` — dashboard
- `records.php` — configured CRUD pages
- `reports.php` — reports
- `users.php` — admin account management
- `my_record.php` — student self-view
- `includes/` — database, session/security helpers, layout and entity definitions
- `assets/style.css` — responsive styling
