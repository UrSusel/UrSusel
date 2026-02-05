<?php
require 'db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    echo "Aktualizacja bazy danych...<br>";
    $pdo->exec("DROP TABLE IF EXISTS inventory");
    $pdo->exec("DROP TABLE IF EXISTS items");
    $pdo->exec("DROP TABLE IF EXISTS classes");
    $pdo->exec("DROP TABLE IF EXISTS map_tiles");
    $pdo->exec("DROP TABLE IF EXISTS characters");
    $pdo->exec("DROP TABLE IF EXISTS users");

    // TWORZENIE TABEL
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE characters (
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
        xp INT DEFAULT 0,
        max_xp INT DEFAULT 100,
        level INT DEFAULT 1,
        steps_buffer INT DEFAULT 0,
        in_combat BOOLEAN DEFAULT FALSE,
        enemy_hp INT DEFAULT 0,
        enemy_max_hp INT DEFAULT 0,
        combat_state TEXT DEFAULT NULL, 
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        base_hp INT DEFAULT 100,
        base_energy INT DEFAULT 10,
        description TEXT
    )");

    $pdo->exec("CREATE TABLE items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        type ENUM('weapon', 'armor', 'consumable') NOT NULL,
        power INT DEFAULT 0, 
        optimal_class_id INT,
        icon VARCHAR(10)
    )");

    $pdo->exec("CREATE TABLE inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT DEFAULT 1,
        is_equipped BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (character_id) REFERENCES characters(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )");

    $pdo->exec("CREATE TABLE map_tiles (
        x INT NOT NULL,
        y INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        PRIMARY KEY (x, y)
    )");

    // DANE STARTOWE
    $pdo->exec("INSERT INTO users (id, username, password) VALUES (1, 'Tester', 'admin')");
    $pdo->exec("INSERT INTO characters (id, user_id, name, hp, max_hp, energy, max_energy, base_attack) 
                VALUES (1, 1, 'Bohater', 100, 100, 10, 10, 1)");

    $pdo->exec("INSERT INTO classes (id, name, base_hp, base_energy, description) VALUES 
    (1, 'Wojownik', 150, 8, 'Mistrz miecza.'),
    (2, 'Mag', 80, 12, 'Włada magią.'),
    (3, 'Łotrzyk', 100, 10, 'Szybki i zwinny.')");

    $pdo->exec("INSERT INTO items (id, name, type, power, optimal_class_id, icon) VALUES 
    (1, 'Zardzewiały Miecz', 'weapon', 10, 1, '⚔️'),
    (2, 'Stary Kostur', 'weapon', 12, 2, '🪄'),
    (3, 'Sztylet', 'weapon', 9, 3, '🗡️'),
    (4, 'Skórzana Kurtka', 'armor', 5, 3, '👕'),
    (5, 'Płytowa Zbroja', 'armor', 15, 1, '🛡️'),
    (6, 'Szata Ucznia', 'armor', 3, 2, '👘'),
    (7, 'Mikstura Życia', 'consumable', 50, NULL, '🧪'),
    (8, 'Bandaż', 'consumable', 20, NULL, '🩹')");

    // GENEROWANIE MAPY (Szybkie)
    echo "Generowanie terenu...<br>";
    $stmt = $pdo->prepare("INSERT INTO map_tiles (x, y, type) VALUES (?, ?, ?)");
    for ($y = 0; $y < 20; $y++) {
        for ($x = 0; $x < 20; $x++) {
            $type = (rand(0,1) == 1) ? 'grass2' : 'grass';
            $rand = rand(1, 100);
            if ($rand > 65) $type = 'forest';
            if ($rand > 85) $type = 'mountain';
            if ($rand > 93) $type = 'water';
            if ($x == 0 && $y == 0) $type = 'city_capital';
            $stmt->execute([$x, $y, $type]);
        }
    }
    
    // STARTOWE PRZEDMIOTY
    $pdo->exec("INSERT INTO inventory (character_id, item_id, quantity) VALUES (1, 7, 3)");
    $pdo->exec("INSERT INTO inventory (character_id, item_id, quantity) VALUES (1, 8, 3)");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h1 style='color:green'>✅ Gotowe! Zresetowano bazę.</h1>";
    echo "<a href='index.php'>WRÓĆ DO GRY</a>";

} catch (PDOException $e) {
    die("Błąd SQL: " . $e->getMessage());
}
?>