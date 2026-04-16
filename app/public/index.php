<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../models/Assignment.php';
require __DIR__ . '/../models/Group.php';
require __DIR__ . '/../models/Rule.php';
require __DIR__ . '/../controllers/GroupController.php';


// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? '';
$controller = new GroupController();

switch ($action) {

    case 'store':
        $controller->store();
        break;

    case 'tree':
        $controller->tree();
        break;

    case 'create_group':
        $controller->createGroup();
        break;

    case 'list_groups':
        $controller->listGroups();
        break;

    case 'list_rules':
        $controller->listRules();
        break;

    case 'create_rule':
        $controller->createRule();
        break;

    case 'update_assignment':
        $controller->updateAssignment();
        break;

    case 'delete_assignment':
        $controller->deleteAssignment();
        break;

    case 'view':
        require "../views/group.php";
        break;
}
