<?php
require 'db.php';

try {
    // Create tables if they don't exist (non-destructive)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS worlds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        width INT NOT NULL,
        height INT NOT NULL,
        is_tutorial BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(50) DEFAULT 'Bezimienny',
        class_id INT DEFAULT NULL,
        hp INT DEFAULT 100,
        max_hp INT DEFAULT 100,
        energy INT DEFAULT 10,
        max_energy INT DEFAULT 10,
        base_attack INT DEFAULT 1,
        base_defense INT DEFAULT 0,
        stat_points INT DEFAULT 0,
        skill_points INT DEFAULT 0,
        pos_x INT DEFAULT 0,
        pos_y INT DEFAULT 0,
        world_id INT DEFAULT 1,
        tutorial_completed BOOLEAN DEFAULT FALSE,
        xp INT DEFAULT 0,
        max_xp INT DEFAULT 100,
        level INT DEFAULT 1,
        steps_buffer INT DEFAULT 0,
        in_combat BOOLEAN DEFAULT FALSE,
        enemy_hp INT DEFAULT 0,
        enemy_max_hp INT DEFAULT 0,
        combat_state TEXT DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (world_id) REFERENCES worlds(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        base_hp INT DEFAULT 100,
        base_energy INT DEFAULT 10,
        description TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        type ENUM('weapon', 'armor', 'consumable') NOT NULL,
        power INT DEFAULT 0,
        optimal_class_id INT,
        icon VARCHAR(10)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT DEFAULT 1,
        is_equipped BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (character_id) REFERENCES characters(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS map_tiles (
        world_id INT NOT NULL,
        x INT NOT NULL,
        y INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        PRIMARY KEY (world_id, x, y),
        FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
    )");

    // New: store last known position per character per world
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_positions (
        character_id INT NOT NULL,
        world_id INT NOT NULL,
        pos_x INT NOT NULL DEFAULT 0,
        pos_y INT NOT NULL DEFAULT 0,
        PRIMARY KEY (character_id, world_id),
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
    )");

    // --- Remove ONLY the tutorial world (id=1) and its tiles ---
    $pdo->prepare("DELETE FROM map_tiles WHERE world_id = ?")->execute([1]);
    $pdo->prepare("DELETE FROM worlds WHERE id = ?")->execute([1]);

    // Insert (or recreate) tutorial world with id = 1
    $pdo->prepare("INSERT INTO worlds (id, name, width, height, is_tutorial) VALUES (1, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name = VALUES(name), width = VALUES(width), height = VALUES(height), is_tutorial = VALUES(is_tutorial)")
        ->execute(['Wyspa Tutorialowa', 15, 15]);

    // Ensure a default user and character exist (idempotent)
    $pdo->prepare("INSERT INTO users (id, username, password) VALUES (1, 'Tester', 'admin')
        ON DUPLICATE KEY UPDATE username = VALUES(username), password = VALUES(password)")
        ->execute();

    $pdo->prepare("INSERT INTO characters (id, user_id, name, hp, max_hp, energy, max_energy, base_attack, world_id, tutorial_completed)
        VALUES (1, 1, 'Bohater', 100, 100, 10, 10, 1, 1, 0)
        ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), name=VALUES(name), hp=VALUES(hp), max_hp=VALUES(max_hp),
            energy=VALUES(energy), max_energy=VALUES(max_energy), base_attack=VALUES(base_attack), world_id=VALUES(world_id), tutorial_completed=VALUES(tutorial_completed)")
        ->execute();

    // Insert classes/items if missing (safe, will ignore duplicates)
    $pdo->exec("INSERT IGNORE INTO classes (id, name, base_hp, base_energy, description) VALUES
        (1, 'Wojownik', 150, 8, 'Mistrz miecza.'),
        (2, 'Mag', 80, 12, 'Włada magią.'),
        (3, 'Łotrzyk', 100, 10, 'Szybki i zwinny.')");

    $pdo->exec("INSERT IGNORE INTO items (id, name, type, power, optimal_class_id, icon) VALUES
        (1, 'Zardzewiały Miecz', 'weapon', 10, 1, '⚔️'),
        (2, 'Stary Kostur', 'weapon', 12, 2, '🪄'),
        (3, 'Sztylet', 'weapon', 9, 3, '🗡️'),
        (4, 'Skórzana Kurtka', 'armor', 5, 3, '👕'),
        (5, 'Płytowa Zbroja', 'armor', 15, 1, '🛡️'),
        (6, 'Szata Ucznia', 'armor', 3, 2, '👘'),
        (7, 'Mikstura Życia', 'consumable', 50, NULL, '🧪'),
        (8, 'Bandaż', 'consumable', 20, NULL, '🩹')");

    // Ensure the tutorial character has basic consumables (delete specific items then reinsert to avoid duplicates)
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ? AND item_id IN (7,8)")->execute([1]);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, 7, 3), (?, 8, 3)")->execute([1,1]);

    // GENERATE MAP FOR TUTORIAL (world_id = 1)
    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (1, ?, ?, ?)");

    for ($y = 0; $y < 15; $y++) {
        for ($x = 0; $x < 15; $x++) {
            $type = 'grass';
            $r = rand(1, 100);
            if ($r > 50) $type = 'grass2';
            if ($r > 75) $type = 'forest';
            if ($r > 90) $type = 'mountain';
            if ($r > 96) $type = 'water';

            // Safe starting zone
            if ($x < 3 && $y < 3) $type = 'grass';
            if ($x == 0 && $y == 0) $type = 'city_village';

            $stmt->execute([$x, $y, $type]);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h1 style='color:green'>✅ Gotowe! (Tutorial world recreated)</h1>";
    echo "<a href='index.php'>WRÓĆ DO GRY</a>";

    // Ensure last_seen column exists (non-destructive, safe)
    $colCheck = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'characters' AND COLUMN_NAME = 'last_seen'
    ");
    $colCheck->execute();
    $has = (int)$colCheck->fetchColumn();
    if (!$has) {
        $pdo->exec("ALTER TABLE characters ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

} catch (PDOException $e) {
    die("Błąd SQL: " . $e->getMessage());
}
?>