<?php
// PHPUnit bootstrap
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ensure errors are visible during test runs
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load app classes 
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Rule.php';
require_once __DIR__ . '/../app/models/Group.php';
require_once __DIR__ . '/../app/models/Assignment.php';

// Create a convenience function to get PDO for cleanup in tests
function get_test_db(): PDO
{
    return Database::connect();
}
