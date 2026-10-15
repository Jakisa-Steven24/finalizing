<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user'])) {
    redirect_to('index.php');
}

$errors = [];
$values = ['reg_no' => '', 'email' => '', 'date_of_birth' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $unused) {
        $values[$key] = post_string($key);
    }
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $password = is_string($password) ? $password : '';
    $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

    if (!preg_match('/^[A-Za-z0-9/-]{3,20}$/', $values['reg_no'])) {
        $errors[] = 'Enter a valid registration number.';
    }
    if (!valid_email($values['email'])) {
        $errors[] = 'Enter a valid university email address.';
    }
    if (!valid_date_value($values['date_of_birth'])) {
        $errors[] = 'Enter a valid date of birth.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{4,60}$/', $values['username'])) {
        $errors[] = 'Username must be 4–60 characters using letters, numbers, dots, underscores or hyphens.';
    }
    if (!valid_password($password)) {
        $errors[] = password_rules_message();
    }
    if (!hash_equals($password, $confirmPassword)) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $student = db_one(
            $conn,
            'SELECT student_id, first_name, last_name, email FROM student WHERE reg_no = ? AND email = ? AND date_of_birth = ? LIMIT 1',
            'sss',
            [$values['reg_no'], $values['email'], $values['date_of_birth']]
        );
        if (!$student) {
            $errors[] = 'We could not verify that student record. Check your registration number, university email and date of birth.';
        } else {
            $taken = db_one($conn, 'SELECT user_id FROM app_users WHERE username = ? OR email = ? OR student_id = ? LIMIT 1', 'ssi', [$values['username'], $values['email'], (int)$student['student_id']]);
            if ($taken) {
                $errors[] = 'An account already exists for those sign-up details. Please sign in or contact an administrator.';
            } else {
                try {
                    $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'student';
                    $stmt = $conn->prepare('INSERT INTO app_users (full_name, username, email, password_hash, role, student_id) VALUES (?, ?, ?, ?, ?, ?)');
                    db_execute($stmt, 'sssssi', [$fullName, $values['username'], $student['email'], $hash, $role, (int)$student['student_id']]);
                    audit_log($conn, 'SIGNUP', 'app_users', (int)$conn->insert_id, 'Student account created through verified sign-up');
                    flash('Your student account is ready. Please sign in.');
                    redirect_to('login.php');
                } catch (Throwable $exception) {
                    app_log('Student sign-up failed', ['message' => $exception->getMessage()]);
                    $errors[] = 'We could not create the account. The username, email or student record may already be in use.';
                }
            }
        }
    }
}

$pageTitle = 'Student sign up';
require 'includes/header.php';
?>
<div class="login-page">
    <div class="card login-card wide-login-card">
        <div class="brand-mark" style="display:inline-block;margin-bottom:18px">MIU</div>
        <h1>Create student login</h1>
        <p class="subheading">This form creates a <strong>student</strong> account only. Your details must match a record already created by the university.</p>
        <?php if ($errors): ?>
            <div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="reg_no">Registration number *</label>
                    <input id="reg_no" name="reg_no" required maxlength="20" pattern="[A-Za-z0-9/-]{3,20}" value="<?= e($values['reg_no']) ?>" placeholder="Example: 2026/CS/001">
                </div>
                <div class="field">
                    <label for="email">University email *</label>
                    <input id="email" name="email" type="email" required maxlength="120" value="<?= e($values['email']) ?>" placeholder="name@university.edu">
                </div>
                <div class="field">
                    <label for="date_of_birth">Date of birth *</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" required value="<?= e($values['date_of_birth']) ?>" placeholder="YYYY-MM-DD">
                </div>
                <div class="field">
                    <label for="new_username">Username *</label>
                    <input id="new_username" name="username" required maxlength="60" pattern="[A-Za-z0-9_.-]{4,60}" autocomplete="username" value="<?= e($values['username']) ?>" placeholder="Choose a username">
                </div>
                <div class="field">
                    <label for="new_password">Password *</label>
                    <input id="new_password" type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password" placeholder="Create a strong password">
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm password *</label>
                    <input id="confirm_password" type="password" name="confirm_password" required minlength="12" maxlength="128" autocomplete="new-password" placeholder="Re-enter your password">
                </div>
            </div>
            <p class="hint"><?= e(password_rules_message()) ?></p>
            <button class="btn" type="submit">Create student account</button>
            <a class="btn secondary" href="login.php">Back to sign in</a>
        </form>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
