<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user'])) {
    redirect_to('index.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = post_string('username');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if ($username === '' || strlen($username) > 120 || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, full_name, username, email, password_hash, role, student_id, is_active, failed_login_attempts, locked_until FROM app_users WHERE username = ? OR email = ? LIMIT 1');
        db_execute($stmt, 'ss', [$username, $username]);
        $user = $stmt->get_result()->fetch_assoc();
        $now = time();
        $locked = $user && !empty($user['locked_until']) && strtotime($user['locked_until']) > $now;

        if (!$user || $locked || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            if ($user && !$locked) {
                // Start a fresh failure window after a previously expired lock.
                $priorLockExpired = !empty($user['locked_until']) && strtotime($user['locked_until']) <= $now;
                $attempts = ($priorLockExpired ? 0 : (int)$user['failed_login_attempts']) + 1;
                $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;
                $update = $conn->prepare('UPDATE app_users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?');
                db_execute($update, 'isi', [$attempts, $lockedUntil, (int)$user['user_id']]);
            }
            app_log('Unsuccessful login attempt', ['login' => substr($username, 0, 120), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            $error = 'Invalid sign-in details, or this account is temporarily unavailable.';
        } else {
            $rehash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT);
            if ($rehash) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $rehashStatement = $conn->prepare('UPDATE app_users SET password_hash = ? WHERE user_id = ?');
                db_execute($rehashStatement, 'si', [$newHash, (int)$user['user_id']]);
            }
            $update = $conn->prepare('UPDATE app_users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?');
            db_execute($update, 'i', [(int)$user['user_id']]);

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'user_id' => (int)$user['user_id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'student_id' => $user['student_id'],
                'is_active' => 1
            ];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            audit_log($conn, 'LOGIN', 'app_users', (int)$user['user_id'], 'Successful sign-in');
            redirect_to('index.php');
        }
    }
}

$pageTitle = 'Sign in';
require 'includes/header.php';
?>
<div class="login-page">
    <div class="card login-card">
        <div class="brand-mark" style="display:inline-block;margin-bottom:18px">MIU</div>
        <h1>Welcome back</h1>
        <p class="subheading">Sign in to the secure student information portal.</p>
        <?php if (get_string('reason') === 'timeout'): ?><div class="alert error">Your session expired after inactivity. Please sign in again.</div><?php endif; ?>
        <?php if (get_string('reason') === 'inactive'): ?><div class="alert error">Your session is no longer active. Contact an administrator if you need help.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field">
                <label for="username">Username or email</label>
                <input id="username" name="username" required maxlength="120" autocomplete="username" value="<?= e($username) ?>" placeholder="Enter username or university email">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            </div>
            <button class="btn" type="submit" style="width:100%">Sign in securely</button>
        </form>
        <div class="login-links">
            <p class="hint">New student? Verify your existing student record and create a student-only login.</p>
            <a class="btn secondary" href="signup.php">Student sign up</a>
        </div>
        <p class="hint" style="margin-top:18px">The first institution owner creates the initial superadmin only through <code>setup_admin.php</code>. Staff accounts cannot be self-registered.</p>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
