CREATE DATABASE IF NOT EXISTS rule_assignments;
USE rule_assignments;

-- Rules master table
CREATE TABLE rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('CONDITION','DECISION') NOT NULL
);

-- Groups table

CREATE TABLE rule_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Hierarchy assignment table
CREATE TABLE group_rule_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    rule_id INT NOT NULL,
    parent_id INT NULL,
    tier TINYINT NOT NULL,
    idempotency_key VARCHAR(64) UNIQUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES rule_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES rules(id),
    FOREIGN KEY (parent_id) REFERENCES group_rule_assignments(id) ON DELETE CASCADE
);

-- Idempotency store
CREATE TABLE idempotency_keys (
    id VARCHAR(64) PRIMARY KEY,
    response TEXT
);

-- Index for performance
CREATE INDEX idx_group_parent ON group_rule_assignments(group_id, parent_id);