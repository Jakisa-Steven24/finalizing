<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user']['user_id'])) {
    audit_log($conn, 'LOGOUT', 'app_users', (int)$_SESSION['user']['user_id'], 'User signed out');
}
destroy_session();
redirect_to('login.php');
