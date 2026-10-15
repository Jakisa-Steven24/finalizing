# MIU SIMS — System Documentation

## 1. Purpose and scope

MIU SIMS is a small, maintainable Student Information Management System written in **procedural PHP** with MySQL. The implementation focuses on patterns expected in modern student-record systems: role separation, authoritative student linking, academic performance capture, finance tracking, search/filtering, auditability and data exports.

It is designed for an institution where staff create the authoritative academic record and students can view—but never change—only the record linked to their account.

## 2. Research-informed design decisions

The design applies established guidance commonly used when evaluating student-record portals and administrative systems:

| Need observed in mature SIMS products | Implementation decision |
|---|---|
| Least privilege and separation of duties | Three roles; only superadmins can create privileged staff accounts. Admins cannot self-promote or create another admin. |
| Student privacy | A student account has one `student_id`; every student page adds that ID to every query. Student navigation has no staff links or global reports. |
| Data integrity at point of entry | HTML constraints improve usability; PHP repeats validation using allow-lists, regular expressions, real-date validation, range checks and foreign-key checks. The database adds unique keys and foreign keys. |
| Accurate assessment capture | Marks use academic year, semester, assessment type/name, score and a server-calculated grade. The selected unit must belong to the selected student's course. |
| Discoverable data at scale | Management lists provide search, entity-specific filters, controlled sorts and pagination. Dashboards/reports have scoped filters. |
| Portable reporting | Filtered reports print cleanly and download as CSV. CSV values beginning with spreadsheet formula characters are neutralized. |
| Accountability and supportability | Create/update/delete, sign-in, sign-out and exports create audit events; safe application logging is available when debugging is enabled. |

Security choices align with the principles in the OWASP **SQL Injection Prevention Cheat Sheet**, **Authentication Cheat Sheet**, **Cross-Site Request Forgery Prevention Cheat Sheet**, and **Session Management Cheat Sheet**. Accessibility-oriented markup uses real labels, explicit select prompts, keyboard-native HTML controls and responsive layouts.

### Design references

- OWASP, [SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)
- OWASP, [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- OWASP, [Cross-Site Request Forgery Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- OWASP, [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- W3C, [Web Content Accessibility Guidelines (WCAG) 2.2](https://www.w3.org/TR/WCAG22/)

## 3. Roles and authorization matrix

| Capability | Student | Admin | Superadmin |
|---|:---:|:---:|:---:|
| Sign in / sign out | ✓ | ✓ | ✓ |
| Self-register a student account | ✓ (verified existing record only) | — | — |
| View own personal profile, marks, payments | ✓ | — | — |
| Export own record | ✓ | — | — |
| CRUD academic and financial records | — | ✓ | ✓ |
| Add/edit/delete marks | — | ✓ | ✓ |
| Run institution reports / CSV exports | — | ✓ | ✓ |
| Create/manage student accounts | — | ✓ | ✓ |
| Create/manage admins or superadmins | — | — | ✓ |
| View immutable audit activity | — | — | ✓ |

### Account lifecycle

1. A staff member creates the student record.
2. The student can use **Student sign up** only when registration number, university email and date of birth exactly match that record.
3. The form hard-codes the role to `student`; it does not accept a role input.
4. A superadmin uses **User accounts** to create admins/superadmins. An admin's account-management allow-list contains `student` only.
5. Password reset is an edit action by authorized staff. Deactivating an account immediately prevents use on the next request. The last active superadmin cannot be deleted, demoted or deactivated.

## 4. Functional modules

Every management page (`records.php?entity=...`) offers the same operations: **Add**, a **View** page with all fields plus created/updated times, **Edit**, and **Delete** (POST + CSRF, with a confirmation prompt). Lists support search, entity-specific filters, sorting by any column and pagination; search also matches related records, so a student's registration number finds their payments, marks, enrollments and registrations. After viewing, editing or deleting from a filtered list, the user returns to the same filtered page.

### Academic master data

- Departments
- Lecturers
- Courses
- Course units
- Students
- Enrollments and registrations

### Assessments

The **Marks** module captures the student, course unit, academic year, semester, assessment type, assessment name, score and optional remarks. The system calculates the grade as follows:

| Score | Grade |
|---:|:---|
| 80–100 | A |
| 75–79.99 | B+ |
| 70–74.99 | B |
| 65–69.99 | C+ |
| 60–64.99 | C |
| 55–59.99 | D+ |
| 50–54.99 | D |
| Below 50 | F |

### Finance

- Fee types / standard amounts
- Registrations and recorded amount paid
- Payments with status, method, date and optional reference

### Reports and exports

Staff reports share the filter set below. Department/course apply to all relevant reports; academic year/semester apply to marks; fee type/payment dates apply to financial rows.

- Course enrolment summary
- Grade distribution
- Academic performance by course unit, including below-pass indicator
- Detailed marks
- Financial transaction report
- CSV downloads for each report and browser print / Save as PDF

Students can download a CSV containing only their own profile, filtered marks and filtered payments.

## 5. Input validation specification

Validation is deliberately duplicated between browser and server. Forms are submitted with `novalidate` so the server can list every problem at once and re-fill the form with what was typed; the same patterns are emitted as HTML attributes and highlight a field while its value does not match. **Only server-side validation is trusted.** Patterns are written to work both in PHP (PCRE) and in browsers, which compile `pattern` with the `v` flag, so `/`, `(`, `)` and `-` are escaped inside character classes.

| Input family | Server rule |
|---|---|
| Registration number | `YYL/LLL/NNN/LLL`, optionally followed by `/D` (for example, `24B/BCS/098/UMC` or `24B/BCS/098/UMC/D`) |
| Staff/course/unit codes | Anchored regex allowing only approved letters, digits, slash/hyphen; configured length limit |
| Human names | Anchored Unicode-letter pattern with spaces, apostrophes, full stops and hyphens |
| Titles and locations | Unicode letters, digits, spaces and `. , & ' / ( ) -` (office locations also allow `#`), with a length range |
| Address | Unicode letters and digits, spaces, line breaks and `. , # & ' / ( ) -`, 5–150 characters |
| Text length | Counted in characters, not bytes, so accented names are measured correctly |
| Email | `filter_var(..., FILTER_VALIDATE_EMAIL)` plus a maximum length |
| Phone | Anchored `[0-9+() .-]` regex and 7–20 length limit |
| Whole numbers | Digits only plus configured min/max |
| Currency/scores | Non-negative decimal regex, at most two decimal places, configured ranges (mark score 0–100) |
| Dates | Strict `Y-m-d` parse/reformat test; a date of birth cannot be in the future and the student must be 10–120 years old |
| Academic year | Anchored `YYYY/YYYY` regex plus the second year must equal first + 1 |
| Selects | Submitted value must be an allow-listed option |
| Foreign keys | Positive integer plus lookup of the target row |
| Uniqueness | Codes, emails, phone numbers, reference numbers, enrollments and assessments are checked before saving and reported by field name |
| Password | 8–128 characters, lower case, upper case, digit, symbol, no whitespace |

## 6. Security architecture

### SQL injection protection

- Values are always supplied through `mysqli` prepared statements.
- Dynamic table/column names exist only in the reviewed PHP entity map. `sql_identifier()` rejects anything that is not a normal identifier before it is quoted.
- Report sort choices are allow-listed field names; order direction is reduced to `ASC` or `DESC`.

### Browser and request protection

- State-changing requests use POST + a 256-bit CSRF token checked with `hash_equals`.
- Output is encoded with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- Headers set `X-Content-Type-Options`, `X-Frame-Options`, restrictive CSP, `Referrer-Policy` and `Permissions-Policy`.
- Session cookies are HTTP-only, SameSite=Lax, strict-mode, HTTPS-aware and have a 30-minute inactivity limit.
- Successful login regenerates the session ID and refreshes the CSRF token.

### Authentication and auditability

- Password hashes are generated with PHP's current `PASSWORD_DEFAULT`; a successful login rehashes when PHP recommends it.
- Account-specific failure count locks an account for 15 minutes after five failed attempts.
- Protected pages refresh the active user record every request, so role/status changes take effect without waiting for a new login.
- Audit entries are inserted for important events. Application faults are written to `logs/application.log` if the directory is writable; raw SQL exceptions are not exposed to users unless local `MIU_APP_DEBUG=1` is deliberately enabled.

## 7. Data model

See [ERD.md](ERD.md) for the Mermaid entity-relationship diagram and relationship notes.

Important integrity rules:

- Department → lecturer/course → course unit/student establishes the academic hierarchy.
- `app_users.student_id` is unique, so one student has at most one application login.
- User role is constrained to `superadmin`, `admin` or `student`.
- `mark` is unique per student, course unit, year, semester, assessment type and assessment name.
- Finance and academic records use restrictive foreign keys to avoid accidental orphaning.
- `recorded_by` on marks and `user_id` in audit events preserve actor relationships where possible.

## 8. Installation and first-use procedure

1. Back up any existing data because `database.sql` rebuilds tables.
2. Import `database.sql`.
3. Set `MIU_DB_*` environment variables or update local values in `config.php`.
4. Confirm PHP has `mysqli` enabled and that `logs/` is writable by the web process if file logging is wanted.
5. Use HTTPS outside local development.
6. Visit `setup_admin.php`, create the initial superadmin, then delete or block this file.
7. Log in and create master data in dependency order.
8. Create students before inviting them to use Student sign up.

## 9. Production deployment checklist

- [ ] Serve only over HTTPS and configure HSTS at the web server after testing.
- [ ] Store database credentials in environment variables/secrets, not a committed config file.
- [ ] Use a MySQL account limited to the `miu` database; prohibit schema changes for the runtime account if operationally possible.
- [ ] Delete/block `setup_admin.php` immediately after initial bootstrap.
- [ ] Set `MIU_APP_DEBUG=0`.
- [ ] Restrict web-server access to configuration, documentation, logs and backups; ideally move them outside the document root.
- [ ] Ensure `logs/application.log` has restrictive permissions and log rotation.
- [ ] Back up the database, encrypt backups, and periodically test restoration.
- [ ] Review superadmin membership regularly and deactivate departed staff promptly.
- [ ] Test with representative roles: unauthenticated, student, admin and superadmin.
- [ ] Conduct a qualified security review before handling real personal data.

## 10. Debugging guide

| Symptom | First safe check |
|---|---|
| “Database connection failed” | Confirm database import, `MIU_DB_*` values and `mysqli` extension. Do not display or share passwords. |
| Save rejected | The form lists each problem by field, including duplicates and student/course mismatches. If only a generic message appears, review restricted application logs as an authorized operator. |
| Student cannot sign up | Confirm the student already exists and exact registration number, university email and date of birth match. |
| Student sees no record | Check the linked `student_id` in User accounts; the student must not be given staff access. |
| Cannot delete a record | The message names the dependent records (for example "1 lecturer and 2 courses"). Remove or reassign them first; foreign keys intentionally protect relationships. |
| Delete happens without a prompt | Confirm `assets/app.js` loads (browser console / network tab). Inline `onsubmit` handlers are blocked by the CSP, so confirmations use `data-confirm`. |
| Account cannot sign in | Check active status, correct credentials and temporary failed-login lock. |

## 11. Coding conventions

- **One statement per line.** No `if (...) statement;`, no `{ a; b; }` blocks on one line, and at most one `<?php ... ?>` block per template line. Long SQL is split one clause per line and long arrays one item per line, so a PHP error line number always identifies a single statement.
- **Controller first, template second.** Pages validate input and run queries before including `includes/header.php`; templates only display prepared data.
- **Included templates share the page's variables.** `includes/header.php` therefore prefixes its own variables with `$nav` and unsets them afterwards. (Its sidebar loop once reused `$entityKey`, which made every management page's Add/Edit/Delete/filter controls point at the last sidebar entry.)
- **No inline JavaScript.** The CSP allows scripts from the site only; behaviour is attached in `assets/app.js` through `data-` attributes such as `data-confirm` and `data-print-page`.
- **New fields or entities** are added in `includes/entities.php` (label, type, validation, `unique` and `dependents` rules); `records.php` builds lists, forms, validation and SQL from that allow-list.
- CSS uses one declaration per line, and every declaration ends with a semicolon.

## 12. Maintenance boundaries

This application is a secure coursework/small-institution foundation, not a substitute for a full institutional governance program. Before handling sensitive production records, establish data-retention, consent, privacy, incident-response, accessibility and backup policies appropriate to the institution and applicable law.
