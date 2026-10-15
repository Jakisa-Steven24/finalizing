<?php
/**
 * One-time bootstrap page. Remove or block this file after creating the first
 * superadmin. It can never create a second superadmin.
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';

$superadminCount = db_one($conn, "SELECT COUNT(*) AS total FROM app_users WHERE role = 'superadmin'");
$exists = (int)($superadminCount['total'] ?? 0) > 0;
$errors = [];
$values = [
    'full_name' => '',
    'username' => '',
    'email' => '',
];
$created = false;

if (!$exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $unused) {
        $values[$key] = post_string($key);
    }
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (!preg_match("/^[\\p{L} .'-]{2,100}$/u", $values['full_name'])) {
        $errors[] = 'Enter a valid full name.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{4,60}$/', $values['username'])) {
        $errors[] = 'Username must be 4–60 characters using letters, numbers, dots, underscores or hyphens.';
    }
    if (!valid_email($values['email'])) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_password($password)) {
        $errors[] = password_rules_message();
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'superadmin';
            $stmt = $conn->prepare(
                'INSERT INTO app_users (full_name, username, email, password_hash, role)'
                . ' VALUES (?, ?, ?, ?, ?)'
            );
            db_execute($stmt, 'sssss', [
                $values['full_name'],
                $values['username'],
                $values['email'],
                $hash,
                $role,
            ]);
            audit_log($conn, 'BOOTSTRAP', 'app_users', (int)$conn->insert_id, 'Initial superadmin created');
            $created = true;
        } catch (Throwable $exception) {
            app_log('Superadmin bootstrap failed', ['message' => $exception->getMessage()]);
            $errors[] = 'Could not create the superadmin. Username or email may already be in use.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Initial superadmin setup | MIU SIMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-page">
    <div class="card login-card wide-login-card">
        <div class="brand-mark" style="display:inline-block;margin-bottom:18px">MIU</div>
        <h1>Initial superadmin setup</h1>
        <?php if ($exists): ?>
            <div class="alert error">A superadmin already exists. This bootstrap page is locked. Remove or block <code>setup_admin.php</code> now.</div>
            <a class="btn" href="login.php">Go to sign in</a>
        <?php elseif ($created): ?>
            <div class="alert">Initial superadmin created. Delete or block <code>setup_admin.php</code> before using the system.</div>
            <a class="btn" href="login.php">Sign in</a>
        <?php else: ?>
            <p class="subheading">Create the one account permitted to add or manage administrators. This page is for first installation only.</p>
            <?php if ($errors): ?>
                <div class="alert error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="setup_admin.php" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="full_name">Full name *</label>
                        <input id="full_name" name="full_name" required maxlength="100" value="<?= e($values['full_name']) ?>" placeholder="Enter superadmin full name">
                    </div>
                    <div class="field">
                        <label for="username">Username *</label>
                        <input id="username" name="username" required maxlength="60" pattern="[A-Za-z0-9_.\-]{4,60}" value="<?= e($values['username']) ?>" placeholder="Choose a username">
                    </div>
                    <div class="field">
                        <label for="email">Email *</label>
                        <input id="email" type="email" name="email" required maxlength="120" value="<?= e($values['email']) ?>" placeholder="admin@university.edu">
                    </div>
                    <div class="field">
                        <label for="password">Password *</label>
                        <input id="password" type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password" placeholder="Create a strong password">
                    </div>
                </div>
                <p class="hint"><?= e(password_rules_message()) ?></p>
                <button class="btn" type="submit">Create initial superadmin</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
