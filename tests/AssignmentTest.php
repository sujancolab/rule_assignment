<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class AssignmentTest extends TestCase
{
    private PDO $db;
    private array $createdGroups = [];
    private array $createdRules = [];
    private array $createdAssignments = [];

    protected function setUp(): void
    {
        $this->db = get_test_db();
        // track created entities so we can clean up after tests
        $this->createdGroups = [];
        $this->createdRules = [];
        $this->createdAssignments = [];
    }

    protected function tearDown(): void
    {
        // Delete created assignments first (reverse order to be safe)
        if (!empty($this->createdAssignments)) {
            foreach (array_reverse($this->createdAssignments) as $aid) {
                $stmt = $this->db->prepare('DELETE FROM group_rule_assignments WHERE id = ?');
                $stmt->execute([$aid]);
            }
        }

        // Delete created groups
        if (!empty($this->createdGroups)) {
            foreach ($this->createdGroups as $gid) {
                $stmt = $this->db->prepare('DELETE FROM rule_groups WHERE id = ?');
                $stmt->execute([$gid]);
            }
        }

        // Delete created rules
        if (!empty($this->createdRules)) {
            foreach ($this->createdRules as $rid) {
                $stmt = $this->db->prepare('DELETE FROM rules WHERE id = ?');
                $stmt->execute([$rid]);
            }
        }
    }

    private function createGroup(string $name = 'TstGrp')
    {
        $g = new Group();
        $id = $g->create($name . uniqid());
        $id = (int)$id;
        $this->createdGroups[] = $id;
        return $id;
    }

    private function createRule(string $name = 'TstRule', string $type = 'CONDITION')
    {
        $r = new Rule();
        $id = $r->create($name . uniqid(), $type);
        $id = (int)$id;
        $this->createdRules[] = $id;
        return $id;
    }

    public function testReuseDifferentParentsSucceeds()
    {
        $groupId = $this->createGroup('ReuseGroup');
        $ruleId = $this->createRule('ReuseRule', 'CONDITION');

        $assign = new Assignment();

        // first assignment at root
        $res1 = $assign->create($groupId, $ruleId, null, 'k1-' . uniqid());
        $this->assertStringContainsString('success', $res1);

        // fetch the first assignment id and record it for cleanup
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND parent_id IS NULL AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $ruleId]);
        $row = $stmt->fetch();
        $this->assertNotEmpty($row, 'first assignment not found');
        $parentId = (int)$row['id'];
        $this->createdAssignments[] = $parentId;

        // second assignment of same rule under the first assignment - should succeed (different parent)
        $res2 = $assign->create($groupId, $ruleId, $parentId, 'k2-' . uniqid());
        $this->assertStringContainsString('success', $res2);
        // record second assignment id
        $stmt2 = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND parent_id = ? AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt2->execute([$groupId, $parentId, $ruleId]);
        $r2 = $stmt2->fetch();
        if ($r2) $this->createdAssignments[] = (int)$r2['id'];
    }

    public function testDuplicateUnderSameParentFails()
    {
        $groupId = $this->createGroup('DupGroup');
        $ruleId = $this->createRule('DupRule', 'CONDITION');

        $assign = new Assignment();

        // first assign at root
        $assign->create($groupId, $ruleId, null, 'd1-' . uniqid());
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND parent_id IS NULL AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $ruleId]);
        $f = $stmt->fetch();
        if ($f) $this->createdAssignments[] = (int)$f['id'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rule already exists under this parent');

        // attempt duplicate at root should throw
        $assign->create($groupId, $ruleId, null, 'd2-' . uniqid());
    }

    public function testSameRuleDifferentGroupSucceeds()
    {
        $groupA = $this->createGroup('G1');
        $groupB = $this->createGroup('G2');
        $ruleId = $this->createRule('SharedRule', 'CONDITION');

        $assign = new Assignment();

        $r1 = $assign->create($groupA, $ruleId, null, 'g1-' . uniqid());
        $this->assertStringContainsString('success', $r1);
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND parent_id IS NULL AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupA, $ruleId]);
        $a1 = $stmt->fetch();
        if ($a1) $this->createdAssignments[] = (int)$a1['id'];

        $r2 = $assign->create($groupB, $ruleId, null, 'g2-' . uniqid());
        $this->assertStringContainsString('success', $r2);
        $stmt2 = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND parent_id IS NULL AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt2->execute([$groupB, $ruleId]);
        $a2 = $stmt2->fetch();
        if ($a2) $this->createdAssignments[] = (int)$a2['id'];
    }
}
