<?php
class Assignment
{

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create($groupId, $ruleId, $parentId, $key)
    {

        // Check idempotency (avoid duplicate inserts on retry)
        $stmt = $this->db->prepare("SELECT response FROM idempotency_keys WHERE id=?");
        $stmt->execute([$key]);

        if ($row = $stmt->fetch()) {
            return $row['response']; // return same response safely
        }

        // Calculate hierarchy level
        $tier = $this->getTier($parentId);

        // Enforce max depth rule tree
        if ($tier > 3) {
            throw new Exception("Max 3 tiers allowed");
        }

        // Prevent duplicate under same parent
        if ($this->exists($groupId, $parentId, $ruleId)) {
            throw new Exception("Rule already exists under this parent");
        }

        // Validate parent rule type
        if ($parentId) {
            $parent = $this->getParentRule($parentId);

            if ($parent['type'] === 'DECISION') {
                throw new Exception("Decision rule cannot have children");
            }
        }

        // Insert assignment
        $stmt = $this->db->prepare("
            INSERT INTO group_rule_assignments
            (group_id, rule_id, parent_id, tier, idempotency_key)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$groupId, $ruleId, $parentId, $tier, $key]);

        // Prepare response
        $response = json_encode(['status' => 'success']);

        // Store idempotent response
        $this->db->prepare("
            INSERT INTO idempotency_keys (id, response)
            VALUES (?, ?)
        ")->execute([$key, $response]);

        return $response;
    }

    public function tree($groupId, $parentId = null)
    {

        // Fetch nodes for current level
        $sql = "SELECT * FROM group_rule_assignments
                WHERE group_id = ? AND parent_id ";

        $sql .= $parentId ? "= ?" : "IS NULL";

        // Ensure nodes are returned in assignment order
        $sql .= " ORDER BY created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parentId ? [$groupId, $parentId] : [$groupId]);

        $rows = $stmt->fetchAll();

        // Build recursive tree
        foreach ($rows as &$row) {
            $row['children'] = $this->tree($groupId, $row['id']);
        }

        return $rows;
    }

    // ---------------- PRIVATE HELPERS ----------------

    private function getTier($parentId)
    {
        if (!$parentId) return 1;

        $stmt = $this->db->prepare("SELECT tier FROM group_rule_assignments WHERE id=?");
        $stmt->execute([$parentId]);

        return $stmt->fetchColumn() + 1;
    }

    private function getParentRule($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.* FROM rules r
            JOIN group_rule_assignments a ON r.id = a.rule_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function updateParent($id, $newParentId)
    {
        // Fetch current node
        $stmt = $this->db->prepare("SELECT * FROM group_rule_assignments WHERE id = ?");
        $stmt->execute([$id]);
        $node = $stmt->fetch();

        if (!$node) throw new Exception("Assignment not found");

        $groupId = $node['group_id'];
        $ruleId = $node['rule_id'];
        $oldTier = $node['tier'];

        // Prevent setting parent to itself
        if ($newParentId && $newParentId == $id) {
            throw new Exception("Cannot set parent to self");
        }

        // Prevent cycles: new parent cannot be a descendant of node
        if ($newParentId && $this->isInSubtree($id, $newParentId)) {
            throw new Exception("Cannot move under a descendant (would create a cycle)");
        }

        // If new parent exists, validate type and calculate new tier
        $newTier = $this->getTier($newParentId);

        // Compute subtree depth (0 means node only)
        $subDepth = $this->getSubtreeDepth($id);

        if ($newTier + $subDepth > 3) {
            throw new Exception("Move would exceed max tiers (3)");
        }

        // Prevent duplicate under new parent
        if ($this->exists($groupId, $newParentId, $ruleId)) {
            throw new Exception("Rule already exists under this parent");
        }

        // If parent is a DECISION rule, it cannot have children
        if ($newParentId) {
            $parent = $this->getParentRule($newParentId);
            if ($parent && $parent['type'] === 'DECISION') {
                throw new Exception("Decision rule cannot have children");
            }
        }

        // Update this node tier and parent
        $offset = $newTier - $oldTier;

        $this->db->prepare("UPDATE group_rule_assignments SET parent_id = ?, tier = ? WHERE id = ?")
            ->execute([$newParentId, $newTier, $id]);

        // Update descendants tiers recursively
        $this->shiftDescendantsTier($id, $offset);

        return json_encode(['status' => 'success']);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM group_rule_assignments WHERE id = ?");
        $stmt->execute([$id]);

        return json_encode(['status' => 'deleted']);
    }

    private function isInSubtree($rootId, $targetId)
    {
        $children = $this->getChildrenIds($rootId);
        if (in_array($targetId, $children)) return true;

        foreach ($children as $c) {
            if ($this->isInSubtree($c, $targetId)) return true;
        }

        return false;
    }

    private function getChildrenIds($parentId)
    {
        $stmt = $this->db->prepare("SELECT id FROM group_rule_assignments WHERE parent_id = ?");
        $stmt->execute([$parentId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => $r['id'], $rows);
    }

    private function getSubtreeDepth($id)
    {
        $max = 0;
        $children = $this->getChildrenIds($id);
        foreach ($children as $c) {
            $d = 1 + $this->getSubtreeDepth($c);
            if ($d > $max) $max = $d;
        }
        return $max;
    }

    private function shiftDescendantsTier($rootId, $offset)
    {
        $children = $this->getChildrenIds($rootId);
        foreach ($children as $c) {
            $this->db->prepare("UPDATE group_rule_assignments SET tier = tier + ? WHERE id = ?")
                ->execute([$offset, $c]);
            $this->shiftDescendantsTier($c, $offset);
        }
    }

    private function exists($g, $p, $r)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM group_rule_assignments
            WHERE group_id=? AND parent_id <=> ? AND rule_id=?
        ");
        $stmt->execute([$g, $p, $r]);

        return $stmt->fetchColumn() > 0;
    }
}
