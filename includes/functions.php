<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { if ($_SERVER['REQUEST_METHOD'] === 'POST') { $sent = $_POST['csrf_token'] ?? ''; if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) { http_response_code(403); exit('Invalid security token. Go back and reload the form.'); } } }
function require_login(): void { if (empty($_SESSION['user'])) { header('Location: login.php'); exit; } }
function require_admin(): void { require_login(); if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('Access denied. Administrator permission is required.'); } }
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = ['message'=>$message,'type'=>$type]; }
function show_flash(): void { if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; echo '<div class="alert '.e($f['type']).'">'.e($f['message']).'</div>'; unset($_SESSION['flash']); } }
function valid_email($value): bool { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
function valid_phone($value): bool { return (bool)preg_match('/^[0-9+() .-]{7,15}$/', (string)$value); }
