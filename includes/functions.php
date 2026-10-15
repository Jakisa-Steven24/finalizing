<?php
/**
 * Shared procedural helpers. All database values are passed via prepared statements;
 * table/column identifiers come only from reviewed application configuration.
 */

function app_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function app_log(string $message, array $context = []): void
{
    $line = '[' . date('c') . '] ' . $message;
    if ($context) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;

    $directory = dirname(APP_LOG_FILE);
    if (is_dir($directory) || @mkdir($directory, 0750, true)) {
        @error_log($line, 3, APP_LOG_FILE);
    } else {
        error_log(trim($line));
    }
}

function app_exception_handler(Throwable $exception): void
{
    app_log('Uncaught application exception', [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if (APP_DEBUG) {
        echo '<h1>Application error</h1><pre>' . e($exception->__toString()) . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1><p>The event has been recorded. Please try again or contact the system administrator.</p>';
    }
    exit;
}

function app_error_handler(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }
    app_log('PHP warning/notice', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
    return false; // Keep normal PHP behaviour in development; errors are never silently swallowed.
}

set_exception_handler('app_exception_handler');
set_error_handler('app_error_handler');

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('miu_sims_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store, max-age=0, must-revalidate');
    header('Pragma: no-cache');
}

start_secure_session();
send_security_headers();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_string(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_positive_int(string $key, int $default = 0): int
{
    $value = get_string($key);
    if ($value === '' || !preg_match('/^[1-9][0-9]*$/', $value)) {
        return $default;
    }
    return (int)$value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        app_log('CSRF token validation failed', ['path' => $_SERVER['REQUEST_URI'] ?? 'unknown']);
        http_response_code(403);
        exit('Invalid security token. Reload the form and try again.');
    }
}

function redirect_to(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], (bool)$parameters['secure'], (bool)$parameters['httponly']);
    }
    session_destroy();
}

function refresh_session_user(): bool
{
    global $conn;
    if (empty($_SESSION['user']['user_id']) || !isset($conn)) {
        return false;
    }

    $userId = (int)$_SESSION['user']['user_id'];
    $stmt = $conn->prepare('SELECT user_id, full_name, username, email, role, student_id, is_active FROM app_users WHERE user_id = ? LIMIT 1');
    db_execute($stmt, 'i', [$userId]);
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || (int)$user['is_active'] !== 1) {
        destroy_session();
        return false;
    }

    $_SESSION['user'] = $user;
    return true;
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        redirect_to('login.php');
    }

    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        destroy_session();
        redirect_to('login.php?reason=timeout');
    }

    if (!refresh_session_user()) {
        redirect_to('login.php?reason=inactive');
    }
    $_SESSION['last_activity'] = time();
}

function current_role(): string
{
    return (string)($_SESSION['user']['role'] ?? '');
}

function is_staff(): bool
{
    return in_array(current_role(), ['superadmin', 'admin'], true);
}

function is_superadmin(): bool
{
    return current_role() === 'superadmin';
}

function require_staff(): void
{
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        exit('Access denied. Staff permission is required.');
    }
}

function require_admin(): void
{
    require_staff(); // Backwards-compatible name for staff-only academic operations.
}

function require_superadmin(): void
{
    require_login();
    if (!is_superadmin()) {
        http_response_code(403);
        exit('Access denied. Superadmin permission is required.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type === 'error' ? 'error' : 'success'];
}

function show_flash(): void
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="alert ' . e($flash['type'] ?? 'success') . '" role="status">' . e($flash['message'] ?? '') . '</div>';
}

function valid_email(string $value): bool
{
    return strlen($value) <= 120 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $value): bool
{
    return (bool)preg_match('/^[0-9+() .-]{7,20}$/', $value);
}

function valid_date_value(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function valid_academic_year(string $value): bool
{
    if (!preg_match('/^(19|20)[0-9]{2}\/(19|20)[0-9]{2}$/', $value)) {
        return false;
    }
    $parts = explode('/', $value);
    return ((int)$parts[1] === ((int)$parts[0] + 1));
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8
        && strlen($password) <= 128
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9\s]/', $password)
        && !preg_match('/\s/', $password);
}

function password_rules_message(): string
{
    return 'Password must be 8–128 characters with upper- and lower-case letters, a number, a symbol, and no spaces.';
}

function grade_from_score(float $score): string
{
    if ($score >= 80) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'D+';
    if ($score >= 50) return 'D';
    return 'F';
}

function sql_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        throw new RuntimeException('Unsafe database identifier requested.');
    }
    return '`' . $identifier . '`';
}

function db_execute(mysqli_stmt $statement, string $types = '', array $params = []): void
{
    if ($types !== '') {
        if (strlen($types) !== count($params)) {
            throw new InvalidArgumentException('The prepared statement parameter count does not match its type string.');
        }
        $values = array_values($params);
        $bind = [$types];
        foreach ($values as $index => $value) {
            $bind[] = &$values[$index];
        }
        call_user_func_array([$statement, 'bind_param'], $bind);
    }
    $statement->execute();
}

function db_result(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result
{
    $statement = $conn->prepare($sql);
    db_execute($statement, $types, $params);
    return $statement->get_result();
}

function db_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $row = db_result($conn, $sql, $types, $params)->fetch_assoc();
    return $row ?: null;
}

function foreign_key_exists(mysqli $conn, string $table, string $column, int $id): bool
{
    $sql = 'SELECT 1 FROM ' . sql_identifier($table) . ' WHERE ' . sql_identifier($column) . ' = ? LIMIT 1';
    return db_one($conn, $sql, 'i', [$id]) !== null;
}

function foreign_options(mysqli $conn, array $field): mysqli_result
{
    $table = sql_identifier($field['ref']);
    $pk = sql_identifier($field['refpk']);
    $display = sql_identifier($field['display']);
    return $conn->query('SELECT ' . $pk . ' AS id, ' . $display . ' AS label FROM ' . $table . ' ORDER BY label ASC, id ASC');
}

function audit_log(mysqli $conn, string $action, string $entity, int $recordId, string $description): void
{
    try {
        $userId = !empty($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt = $conn->prepare('INSERT INTO audit_log (user_id, action, entity, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        db_execute($stmt, 'ississ', [$userId, substr($action, 0, 30), substr($entity, 0, 60), $recordId ?: null, substr($description, 0, 255), $ip ?: null]);
    } catch (Throwable $exception) {
        app_log('Audit write failed', ['message' => $exception->getMessage(), 'entity' => $entity]);
    }
}

function csv_safe($value): string
{
    $value = (string)$value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

function query_string(array $values): string
{
    $clean = [];
    foreach ($values as $key => $value) {
        if ($value !== '' && $value !== null && $value !== 0) {
            $clean[$key] = $value;
        }
    }
    return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
}
