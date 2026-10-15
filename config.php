<?php
/**
 * Application configuration.
 * Copy this project outside a public web root where possible and set these values
 * for the target MySQL account. Do not commit production credentials.
 */
define('DB_HOST', getenv('MIU_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('MIU_DB_NAME') ?: 'miu');
define('DB_USER', getenv('MIU_DB_USER') ?: 'root');
define('DB_PASS', getenv('MIU_DB_PASS') ?: '');

define('APP_NAME', 'MIU Student Information Management System');
define('APP_DEBUG', filter_var(getenv('MIU_APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('APP_LOG_FILE', __DIR__ . '/logs/application.log');
define('SESSION_IDLE_TIMEOUT', 1800); // 30 minutes

// Safe by default: turn diagnostics on only for a controlled local environment.
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(getenv('MIU_TIMEZONE') ?: 'Africa/Kampala');
