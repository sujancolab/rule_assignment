<?php
class Group
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create($name)
    {
        $stmt = $this->db->prepare("INSERT INTO rule_groups (name) VALUES (?)");
        $stmt->execute([$name]);

        return $this->db->lastInsertId();
    }

    public function all()
    {
        $stmt = $this->db->query("SELECT * FROM rule_groups ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM rule_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
