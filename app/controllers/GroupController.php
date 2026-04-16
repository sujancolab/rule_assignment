<?php
class GroupController
{

    public function store()
    {

        // Read JSON input from Vue
        $data = json_decode(file_get_contents("php://input"), true);

        // CSRF validation for security
        $this->validateCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $groupId  = (int)$data['group_id'];
        $ruleId   = (int)$data['rule_id'];
        $parentId = $data['parent_id'] ?? null;

        // Idempotency key prevents duplicate submissions
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? bin2hex(random_bytes(16));

        try {
            $model = new Assignment();
            echo $model->create($groupId, $ruleId, $parentId, $key);
        } catch (Exception $e) {

            // Safe error response (no system leakage)
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function tree()
    {

        $groupId = (int)$_GET['group_id'];

        $model = new Assignment();

        header('Content-Type: application/json');
        echo json_encode($model->tree($groupId));
    }

    public function createGroup()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $this->validateCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $name = trim($data['name'] ?? '');

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name required']);
            return;
        }

        try {
            $model = new Group();
            $id = $model->create($name);

            header('Content-Type: application/json');
            echo json_encode(['id' => $id, 'name' => $name]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function listGroups()
    {
        $model = new Group();
        header('Content-Type: application/json');
        echo json_encode($model->all());
    }

    public function listRules()
    {
        $model = new Rule();
        header('Content-Type: application/json');
        echo json_encode($model->all());
    }

    public function createRule()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $this->validateCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $name = trim($data['name'] ?? '');
        $type = strtoupper(trim($data['type'] ?? ''));

        if ($name === '' || !in_array($type, ['CONDITION', 'DECISION'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid name or type']);
            return;
        }

        try {
            $model = new Rule();
            $id = $model->create($name, $type);
            header('Content-Type: application/json');
            echo json_encode(['id' => $id, 'name' => $name, 'type' => $type]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function updateAssignment()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $this->validateCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $id = (int)($data['id'] ?? 0);
        $parentId = $data['parent_id'] ?? null;

        try {
            $model = new Assignment();
            echo $model->updateParent($id, $parentId);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteAssignment()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $this->validateCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $id = (int)($data['id'] ?? 0);

        try {
            $model = new Assignment();
            echo $model->delete($id);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function validateCSRF($token)
    {

        // Simple CSRF protection using session token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if ($token !== $_SESSION['csrf_token']) {
            http_response_code(403);
            exit(json_encode(['error' => 'CSRF validation failed']));
        }
    }
}
