<?php
class Database
{

    public static function connect()
    {

        // Central DB connection using PDO for security
        return new PDO(
            "mysql:host=localhost;dbname=rule_assignments;charset=utf8mb4",
            "root",
            "Sujan1997",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
}
