<?php
// migrate_v2.php
require_once __DIR__ . '/config/db.php';

try {
    // 1. Add note to tasks
    $pdo->exec("
        ALTER TABLE tasks
        ADD COLUMN note DECIMAL(5,2) DEFAULT NULL
    ");
    echo "tasks.note column added successfully.\n";
} catch (PDOException $e) {
    echo "tasks.note column already exists or error: " . $e->getMessage() . "\n";
}

try {
    // 2. Add github_url to projects
    $pdo->exec("
        ALTER TABLE projects
        ADD COLUMN github_url VARCHAR(500) DEFAULT NULL
    ");
    echo "projects.github_url column added successfully.\n";
} catch (PDOException $e) {
    echo "projects.github_url column already exists or error: " . $e->getMessage() . "\n";
}

try {
    // 3. Add final_deadline to projects
    $pdo->exec("
        ALTER TABLE projects
        ADD COLUMN final_deadline DATE DEFAULT NULL
    ");
    echo "projects.final_deadline column added successfully.\n";
} catch (PDOException $e) {
    echo "projects.final_deadline column already exists or error: " . $e->getMessage() . "\n";
}

echo "Migration V2 complete.\n";
