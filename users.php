<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_staff();

function account_allowed_roles(): array
{
    if (is_superadmin()) {
        return [
            'student' => 'Student',
            'admin' => 'Administrator',
            'superadmin' => 'Superadmin',
        ];
    }
    return [
        'student' => 'Student',
    ];
}

function account_can_manage(array $account): bool
{
    return is_superadmin() || ($account['role'] ?? '') === 'student';
}

function account_is_signed_in_user(array $account): bool
{
    return (int)($account['user_id'] ?? 0) === (int)$_SESSION['user']['user_id'];
}

function account_is_last_active_superadmin(mysqli $conn, array $account): bool
{
    if (($account['role'] ?? '') !== 'superadmin' || (int)($account['is_active'] ?? 0) !== 1) {
        return false;
    }
    $row = db_one($conn, "SELECT COUNT(*) AS total FROM app_users WHERE role = 'superadmin' AND is_active = 1");
    return (int)($row['total'] ?? 0) <= 1;
}

function account_load(mysqli $conn, int $userId): ?array
{
    $sql = 'SELECT u.user_id, u.full_name, u.username, u.email, u.role, u.student_id, u.is_active,'
        . ' u.failed_login_attempts, u.locked_until, u.last_login, u.created_at, u.updated_at,'
        . ' s.reg_no, s.first_name, s.last_name'
        . ' FROM app_users u'
        . ' LEFT JOIN student s ON s.student_id = u.student_id'
        . ' WHERE u.user_id = ?'
        . ' LIMIT 1';
    return db_one($conn, $sql, 'i', [$userId]);
}

/**
 * Raw submitted values, used to re-display the form exactly as typed after a failed save.
 */
function account_submitted_values(): array
{
    return [
        'full_name' => post_string('full_name'),
        'username' => post_string('username'),
        'email' => post_string('email'),
        'role' => post_string('role'),
        'student_id' => post_string('student_id'),
        'is_active' => post_string('is_active'),
    ];
}

function account_validate(mysqli $conn, array $existing, bool $isCreate, array &$errors): array
{
    $data = account_submitted_values();
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) {
        $password = '';
    }
    $excludeId = $isCreate ? 0 : (int)$existing['user_id'];

    if (!preg_match("/^[\\p{L} .'-]{2,100}$/u", $data['full_name'])) {
        $errors[] = 'Enter a valid full name.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{4,60}$/', $data['username'])) {
        $errors[] = 'Username must be 4–60 characters using letters, numbers, dots, underscores or hyphens.';
    } else {
        $usernameOwner = db_one(
            $conn,
            'SELECT user_id FROM app_users WHERE username = ? AND user_id <> ? LIMIT 1',
            'si',
            [$data['username'], $excludeId]
        );
        if ($usernameOwner) {
            $errors[] = 'That username is already taken. Choose a different username.';
        }
    }
    if (!valid_email($data['email'])) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $emailOwner = db_one(
            $conn,
            'SELECT user_id FROM app_users WHERE email = ? AND user_id <> ? LIMIT 1',
            'si',
            [$data['email'], $excludeId]
        );
        if ($emailOwner) {
            $errors[] = 'That email address is already used by another account.';
        }
    }
    if (!array_key_exists($data['role'], account_allowed_roles())) {
        $errors[] = 'You are not permitted to assign that role.';
    }
    if (!in_array($data['is_active'], ['0', '1'], true)) {
        $errors[] = 'Choose a valid account status.';
    }

    $studentId = null;
    if ($data['role'] === 'student') {
        $isId = preg_match('/^[1-9][0-9]*$/', $data['student_id']) === 1;
        if (!$isId || !foreign_key_exists($conn, 'student', 'student_id', (int)$data['student_id'])) {
            $errors[] = 'A student account must be linked to an existing student record.';
        } else {
            $studentId = (int)$data['student_id'];
            $linked = db_one(
                $conn,
                'SELECT user_id FROM app_users WHERE student_id = ? AND user_id <> ? LIMIT 1',
                'ii',
                [$studentId, $excludeId]
            );
            if ($linked) {
                $errors[] = 'That student record is already linked to another login account.';
            }
        }
    }

    $passwordSupplied = $isCreate || $password !== '';
    if ($passwordSupplied && !valid_password($password)) {
        $errors[] = password_rules_message();
    }

    if (!$isCreate && account_is_signed_in_user($existing)) {
        if ($data['role'] !== $existing['role'] || $data['is_active'] !== '1') {
            $errors[] = 'You cannot change your own role or deactivate your own account.';
        }
    }
    $removesSuperadmin = $data['role'] !== 'superadmin' || $data['is_active'] !== '1';
    if (!$isCreate && $removesSuperadmin && account_is_last_active_superadmin($conn, $existing)) {
        $errors[] = 'At least one active superadmin must remain.';
    }

    $data['student_id'] = $studentId;
    $data['is_active'] = (int)$data['is_active'];
    $data['password'] = $password;
    return $data;
}

$action = get_string('action') ?: 'list';
if (!in_array($action, ['list', 'view', 'create', 'edit', 'delete'], true)) {
    $action = 'list';
}
$id = get_positive_int('id');
$errors = [];
$editUser = [];

if (in_array($action, ['view', 'edit', 'delete'], true)) {
    if ($id < 1) {
        flash('Choose a valid user account.', 'error');
        redirect_to('users.php');
    }
    $editUser = account_load($conn, $id) ?? [];
    if (!$editUser) {
        flash('User account not found. It may have been deleted.', 'error');
        redirect_to('users.php');
    }
    if (!account_can_manage($editUser)) {
        http_response_code(403);
        exit('You do not have permission to manage this account.');
    }
}

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Invalid delete request.');
    }
    verify_csrf();
    if (account_is_signed_in_user($editUser)) {
        flash('You cannot delete the account currently signed in.', 'error');
    } elseif (account_is_last_active_superadmin($conn, $editUser)) {
        flash('The last active superadmin cannot be deleted.', 'error');
    } else {
        try {
            $statement = $conn->prepare('DELETE FROM app_users WHERE user_id = ?');
            db_execute($statement, 'i', [(int)$editUser['user_id']]);
            audit_log($conn, 'DELETE', 'app_users', (int)$editUser['user_id'], 'User account deleted');
            flash('User account deleted.');
        } catch (Throwable $exception) {
            app_log('Account delete failed', [
                'id' => $id,
                'message' => $exception->getMessage(),
            ]);
            flash('This account could not be deleted. Please try again.', 'error');
        }
    }
    redirect_to('users.php');
}

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = account_validate($conn, $editUser, $action === 'create', $errors);
    if (!$errors) {
        try {
            if ($action === 'create') {
                $statement = $conn->prepare(
                    'INSERT INTO app_users (full_name, username, email, password_hash, role, student_id, is_active)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                db_execute($statement, 'sssssii', [
                    $data['full_name'],
                    $data['username'],
                    $data['email'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['role'],
                    $data['student_id'],
                    $data['is_active'],
                ]);
                $newId = (int)$conn->insert_id;
                audit_log($conn, 'CREATE', 'app_users', $newId, 'User account created with role ' . $data['role']);
                flash('User account created successfully.');
                redirect_to('users.php');
            }
            $sql = 'UPDATE app_users SET full_name = ?, username = ?, email = ?, role = ?, student_id = ?, is_active = ?';
            $types = 'ssssii';
            $params = [
                $data['full_name'],
                $data['username'],
                $data['email'],
                $data['role'],
                $data['student_id'],
                $data['is_active'],
            ];
            if ($data['password'] !== '') {
                // A password reset by staff also clears any sign-in lock.
                $sql .= ', password_hash = ?, failed_login_attempts = 0, locked_until = NULL';
                $types .= 's';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE user_id = ?';
            $types .= 'i';
            $params[] = (int)$editUser['user_id'];
            $statement = $conn->prepare($sql);
            db_execute($statement, $types, $params);
            audit_log($conn, 'UPDATE', 'app_users', (int)$editUser['user_id'], 'User account updated');
            flash('User account updated successfully.');
            redirect_to('users.php');
        } catch (Throwable $exception) {
            app_log('Account save failed', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
            $errors[] = 'Could not save the account. Username, email or student record may already be in use.';
        }
    }
    $editUser = array_merge($editUser, account_submitted_values());
}

if ($action === 'view') {
    $linkedStudent = '—';
    if (!empty($editUser['reg_no'])) {
        $linkedStudent = $editUser['reg_no'] . ' — ' . $editUser['first_name'] . ' ' . $editUser['last_name'];
    }
    $signInLock = 'Not locked';
    if (!empty($editUser['locked_until']) && strtotime($editUser['locked_until']) > time()) {
        $signInLock = 'Locked until ' . $editUser['locked_until'];
    }
    $accountDetails = [
        'Full name' => $editUser['full_name'],
        'Username' => $editUser['username'],
        'Email' => $editUser['email'],
        'Role' => ucfirst($editUser['role']),
        'Linked student' => $linkedStudent,
        'Status' => (int)$editUser['is_active'] === 1 ? 'Active' : 'Inactive',
        'Sign-in lock' => $signInLock,
        'Failed sign-in attempts' => (string)(int)$editUser['failed_login_attempts'],
        'Last login' => $editUser['last_login'] ?: 'Never',
        'Created' => $editUser['created_at'],
        'Last updated' => $editUser['updated_at'],
    ];
}

if (in_array($action, ['create', 'edit'], true)) {
    $students = $conn->query('SELECT student_id, reg_no, first_name, last_name FROM student ORDER BY reg_no ASC');
}

if ($action === 'list') {
    $q = get_string('q');
    if (text_length($q) > 100) {
        $q = '';
    }
    $roleFilter = get_string('role');
    if ($roleFilter !== '' && !array_key_exists($roleFilter, account_allowed_roles())) {
        $roleFilter = '';
    }
    $activeFilter = get_string('active');
    if (!in_array($activeFilter, ['', '0', '1'], true)) {
        $activeFilter = '';
    }
    $page = min(get_positive_int('page', 1), 10000);
    $perPage = 25;
    $where = [];
    $types = '';
    $params = [];
    if (!is_superadmin()) {
        $where[] = "u.role = 'student'";
    }
    if ($q !== '') {
        $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR s.reg_no LIKE ?)';
        $types .= 'ssss';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($roleFilter !== '') {
        $where[] = 'u.role = ?';
        $types .= 's';
        $params[] = $roleFilter;
    }
    if ($activeFilter !== '') {
        $where[] = 'u.is_active = ?';
        $types .= 'i';
        $params[] = (int)$activeFilter;
    }
    $fromSql = ' FROM app_users u LEFT JOIN student s ON s.student_id = u.student_id';
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $totalRow = db_one($conn, 'SELECT COUNT(*) AS total' . $fromSql . $whereSql, $types, $params);
    $total = (int)($totalRow['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $users = db_result(
        $conn,
        'SELECT u.user_id, u.full_name, u.username, u.email, u.role, u.student_id, u.is_active, u.created_at, u.last_login, s.reg_no'
        . $fromSql
        . $whereSql
        . ' ORDER BY u.created_at DESC, u.user_id DESC'
        . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $types,
        $params
    );
    $baseQuery = [
        'q' => $q,
        'role' => $roleFilter,
        'active' => $activeFilter,
    ];
}

$pageTitle = 'User accounts';
require 'includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>User accounts</h1>
        <p class="subheading">
            <?php if (is_superadmin()): ?>
                Superadmins manage all account roles. Only this role can create administrators or other superadmins.
            <?php else: ?>
                Administrators can create and manage student logins only.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn secondary" href="index.php">← Dashboard</a>
</div>

<?php if ($action === 'view'): ?>
    <section class="card">
        <div class="toolbar">
            <div>
                <h2>Account details</h2>
                <p class="hint">User account #<?= (int)$editUser['user_id'] ?></p>
            </div>
            <div class="actions">
                <a class="btn" href="users.php?<?= e(query_string(['action' => 'edit', 'id' => (int)$editUser['user_id']])) ?>">Edit</a>
                <?php if (!account_is_signed_in_user($editUser)): ?>
                    <form class="inline-form" method="post" action="users.php?<?= e(query_string(['action' => 'delete', 'id' => (int)$editUser['user_id']])) ?>" data-confirm="Delete this account? This cannot be undone.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn danger" type="submit">Delete</button>
                    </form>
                <?php endif; ?>
                <a class="btn secondary" href="users.php">Back to accounts</a>
            </div>
        </div>
        <div class="profile-grid">
            <?php foreach ($accountDetails as $detailLabel => $detailValue): ?>
                <div class="field">
                    <span class="field-label"><?= e($detailLabel) ?></span>
                    <div class="readonly-value"><?= e($detailValue) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php elseif (in_array($action, ['create', 'edit'], true)): ?>
    <section class="card">
        <h2><?= $action === 'create' ? 'Create user account' : 'Edit user account' ?></h2>
        <p class="hint">A student login must be linked to one student record. Staff roles have no student link. Password is required for new accounts and optional when editing.</p>
        <?php if ($errors): ?>
            <div class="alert error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="users.php?<?= e(query_string(['action' => $action, 'id' => $action === 'edit' ? $id : null])) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="full_name">Full name *</label>
                    <input id="full_name" name="full_name" required maxlength="100" value="<?= e($editUser['full_name'] ?? '') ?>" placeholder="Enter full name">
                </div>
                <div class="field">
                    <label for="username">Username *</label>
                    <input id="username" name="username" required maxlength="60" pattern="[A-Za-z0-9_.\-]{4,60}" value="<?= e($editUser['username'] ?? '') ?>" placeholder="Choose a username">
                </div>
                <div class="field">
                    <label for="email">Email *</label>
                    <input id="email" type="email" name="email" required maxlength="120" value="<?= e($editUser['email'] ?? '') ?>" placeholder="name@university.edu">
                </div>
                <div class="field">
                    <label for="password"><?= $action === 'create' ? 'Password *' : 'New password (optional)' ?></label>
                    <input id="password" type="password" name="password"<?= $action === 'create' ? ' required' : '' ?> minlength="8" maxlength="128" autocomplete="new-password" placeholder="<?= $action === 'create' ? 'Create a strong password' : 'Leave blank to keep current password' ?>">
                </div>
                <div class="field">
                    <label for="role">Role *</label>
                    <select id="role" name="role">
                        <?php foreach (account_allowed_roles() as $roleValue => $roleLabel): ?>
                            <option value="<?= e($roleValue) ?>"<?= ($editUser['role'] ?? 'student') === $roleValue ? ' selected' : '' ?>><?= e($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="student_id">Linked student record</label>
                    <select id="student_id" name="student_id">
                        <option value="">Choose student for a student role</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int)$student['student_id'] ?>"<?= (string)($editUser['student_id'] ?? '') === (string)$student['student_id'] ? ' selected' : '' ?>><?= e($student['reg_no'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="is_active">Account status *</label>
                    <select id="is_active" name="is_active">
                        <option value="1"<?= (string)($editUser['is_active'] ?? 1) === '1' ? ' selected' : '' ?>>Active</option>
                        <option value="0"<?= (string)($editUser['is_active'] ?? 1) === '0' ? ' selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <p class="hint"><?= e(password_rules_message()) ?> Setting a new password also clears any sign-in lock.</p>
            <button class="btn" type="submit">Save account</button>
            <a class="btn secondary" href="users.php">Cancel</a>
        </form>
    </section>
<?php else: ?>
    <section class="card filter-card">
        <div class="toolbar">
            <div>
                <h2>Find user accounts</h2>
                <p class="hint">Filters apply to accounts you are authorized to manage.</p>
            </div>
            <a class="btn" href="users.php?action=create">＋ Create account</a>
        </div>
        <form class="filter-form" method="get" action="users.php">
            <div class="field">
                <label for="q">Search</label>
                <input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search name, username, email or reg. no.">
            </div>
            <div class="field">
                <label for="role_filter">Role</label>
                <select id="role_filter" name="role">
                    <option value="">All permitted roles</option>
                    <?php foreach (account_allowed_roles() as $roleValue => $roleLabel): ?>
                        <option value="<?= e($roleValue) ?>"<?= $roleFilter === $roleValue ? ' selected' : '' ?>><?= e($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="active_filter">Status</label>
                <select id="active_filter" name="active">
                    <option value="">All statuses</option>
                    <option value="1"<?= $activeFilter === '1' ? ' selected' : '' ?>>Active</option>
                    <option value="0"<?= $activeFilter === '0' ? ' selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn" type="submit">Apply filters</button>
                <a class="btn secondary" href="users.php">Clear filters</a>
            </div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar">
            <h2><?= $total ?> account<?= $total === 1 ? '' : 's' ?></h2>
            <span class="hint">Page <?= $page ?> of <?= $pages ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Linked student</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $userId = (int)$user['user_id']; ?>
                        <tr>
                            <td><?= e($user['full_name']) ?></td>
                            <td><?= e($user['username']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><span class="badge"><?= e(ucfirst($user['role'])) ?></span></td>
                            <td><?= e($user['reg_no'] ?: '—') ?></td>
                            <td><span class="badge<?= (int)$user['is_active'] ? '' : ' badge-muted' ?>"><?= (int)$user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= e($user['last_login'] ?: 'Never') ?></td>
                            <td>
                                <?php if (account_can_manage($user)): ?>
                                    <div class="actions">
                                        <a class="btn secondary small" href="users.php?<?= e(query_string(['action' => 'view', 'id' => $userId])) ?>">View</a>
                                        <a class="btn secondary small" href="users.php?<?= e(query_string(['action' => 'edit', 'id' => $userId])) ?>">Edit</a>
                                        <?php if (!account_is_signed_in_user($user)): ?>
                                            <form class="inline-form" method="post" action="users.php?<?= e(query_string(['action' => 'delete', 'id' => $userId])) ?>" data-confirm="Delete this account? This cannot be undone.">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <button class="btn danger small" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="hint">Restricted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($total === 0): ?>
                        <tr>
                            <td colspan="8" class="empty">No accounts match these filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Account pages">
                <?php if ($page > 1): ?>
                    <a class="btn secondary small" href="users.php?<?= e(query_string(array_merge($baseQuery, ['page' => $page - 1]))) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="btn secondary small" href="users.php?<?= e(query_string(array_merge($baseQuery, ['page' => $page + 1]))) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
