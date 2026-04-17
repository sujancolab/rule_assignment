<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class AssignmentConstraintsTest extends TestCase
{
    private PDO $db;
    private array $createdGroups = [];
    private array $createdRules = [];
    private array $createdAssignments = [];

    protected function setUp(): void
    {
        $this->db = get_test_db();
        $this->createdGroups = [];
        $this->createdRules = [];
        $this->createdAssignments = [];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdAssignments) as $aid) {
            $stmt = $this->db->prepare('DELETE FROM group_rule_assignments WHERE id = ?');
            $stmt->execute([$aid]);
        }
        foreach ($this->createdGroups as $gid) {
            $stmt = $this->db->prepare('DELETE FROM rule_groups WHERE id = ?');
            $stmt->execute([$gid]);
        }
        foreach ($this->createdRules as $rid) {
            $stmt = $this->db->prepare('DELETE FROM rules WHERE id = ?');
            $stmt->execute([$rid]);
        }
    }

    private function createGroup(string $name = 'TstGrp')
    {
        $g = new Group();
        $id = (int)$g->create($name . uniqid());
        $this->createdGroups[] = $id;
        return $id;
    }

    private function createRule(string $name = 'TstRule', string $type = 'CONDITION')
    {
        $r = new Rule();
        $id = (int)$r->create($name . uniqid(), $type);
        $this->createdRules[] = $id;
        return $id;
    }

    public function testDecisionCannotHaveChildren()
    {
        $groupId = $this->createGroup('DGroup');
        $decisionRule = $this->createRule('DecRule', 'DECISION');
        $childRule = $this->createRule('ChildRule', 'CONDITION');

        $assign = new Assignment();

        // assign decision at root
        $assign->create($groupId, $decisionRule, null, 'k-' . uniqid());
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $decisionRule]);
        $row = $stmt->fetch();
        $this->assertNotEmpty($row);
        $decAssignId = (int)$row['id'];
        $this->createdAssignments[] = $decAssignId;

        // attempting to assign a child under a DECISION parent should fail
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Decision rule cannot have children');
        $assign->create($groupId, $childRule, $decAssignId, 'k2-' . uniqid());
    }

    public function testMaxThreeTiersEnforced()
    {
        $groupId = $this->createGroup('MaxTier');
        $r1 = $this->createRule('R1');
        $r2 = $this->createRule('R2');
        $r3 = $this->createRule('R3');
        $r4 = $this->createRule('R4');

        $assign = new Assignment();
        // tier1
        $assign->create($groupId, $r1, null, 't1-' . uniqid());
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $r1]);
        $a1 = $stmt->fetch();
        $this->createdAssignments[] = (int)$a1['id'];

        // tier2
        $assign->create($groupId, $r2, $a1['id'], 't2-' . uniqid());
        $stmt->execute([$groupId, $r2]);
        $a2 = $stmt->fetch();
        $this->createdAssignments[] = (int)$a2['id'];

        // tier3
        $assign->create($groupId, $r3, $a2['id'], 't3-' . uniqid());
        $stmt->execute([$groupId, $r3]);
        $a3 = $stmt->fetch();
        $this->createdAssignments[] = (int)$a3['id'];

        // attempting tier4 should fail
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Max 3 tiers allowed');
        $assign->create($groupId, $r4, $a3['id'], 't4-' . uniqid());
    }

    public function testMoveCannotCreateCycle()
    {
        $groupId = $this->createGroup('CycleGroup');
        $ra = $this->createRule('A');
        $rb = $this->createRule('B');
        $rc = $this->createRule('C');

        $assign = new Assignment();
        $assign->create($groupId, $ra, null, 'a-' . uniqid());
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $ra]);
        $a = $stmt->fetch();
        $this->createdAssignments[] = (int)$a['id'];

        $assign->create($groupId, $rb, $a['id'], 'b-' . uniqid());
        $stmt->execute([$groupId, $rb]);
        $b = $stmt->fetch();
        $this->createdAssignments[] = (int)$b['id'];

        $assign->create($groupId, $rc, $b['id'], 'c-' . uniqid());
        $stmt->execute([$groupId, $rc]);
        $c = $stmt->fetch();
        $this->createdAssignments[] = (int)$c['id'];

        // attempt to move A under C (descendant) should fail
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot move under a descendant');
        $assign->updateParent((int)$a['id'], (int)$c['id']);
    }

    public function testMoveCannotExceedMaxTiers()
    {
        $groupId = $this->createGroup('MoveTier');
        $r1 = $this->createRule('M1');
        $r2 = $this->createRule('M2');
        $r3 = $this->createRule('M3');
        $r4 = $this->createRule('M4');

        $assign = new Assignment();
        // create deep chain A->B->C (tiers 1..3)
        $assign->create($groupId, $r1, null, 'm1-' . uniqid());
        $stmt = $this->db->prepare('SELECT id FROM group_rule_assignments WHERE group_id=? AND rule_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$groupId, $r1]);
        $a = $stmt->fetch();
        $this->createdAssignments[] = (int)$a['id'];

        $assign->create($groupId, $r2, $a['id'], 'm2-' . uniqid());
        $stmt->execute([$groupId, $r2]);
        $b = $stmt->fetch();
        $this->createdAssignments[] = (int)$b['id'];

        $assign->create($groupId, $r3, $b['id'], 'm3-' . uniqid());
        $stmt->execute([$groupId, $r3]);
        $c = $stmt->fetch();
        $this->createdAssignments[] = (int)$c['id'];

        // create node D at root
        $assign->create($groupId, $r4, null, 'm4-' . uniqid());
        $stmt->execute([$groupId, $r4]);
        $d = $stmt->fetch();
        $this->createdAssignments[] = (int)$d['id'];

        // moving D under C would make it tier4 -> should fail
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Move would exceed max tiers');
        $assign->updateParent((int)$d['id'], (int)$c['id']);
    }

    public function testTreeOrderPreserved()
    {
        $groupId = $this->createGroup('OrderGroup');
        $r1 = $this->createRule('O1');
        $r2 = $this->createRule('O2');
        $r3 = $this->createRule('O3');

        $assign = new Assignment();
        $assign->create($groupId, $r1, null, 'o1-' . uniqid());
        usleep(200000); // 200ms to try to give distinct timestamps
        $assign->create($groupId, $r2, null, 'o2-' . uniqid());
        usleep(200000);
        $assign->create($groupId, $r3, null, 'o3-' . uniqid());

        // fetch tree and assert order matches insertion (created_at asc)
        $tree = $assign->tree($groupId);
        $this->assertCount(3, $tree);
        $ids = array_map(fn($n) => $n['rule_id'], $tree);
        $this->assertEquals([$r1, $r2, $r3], $ids);
    }
}
