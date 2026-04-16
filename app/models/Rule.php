<?php
class Rule
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function all()
    {
        $stmt = $this->db->query("SELECT * FROM rules ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM rules WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($name, $type)
    {
        $name = trim($name);
        $type = strtoupper(trim($type));

        if ($name === '' || !in_array($type, ['CONDITION', 'DECISION'])) {
            throw new Exception('Invalid rule data');
        }

        $stmt = $this->db->prepare("INSERT INTO rules (name, type) VALUES (?, ?)");
        $stmt->execute([$name, $type]);

        return $this->db->lastInsertId();
    }
}
