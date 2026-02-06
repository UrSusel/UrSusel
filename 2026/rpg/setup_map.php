<?php
require 'db.php';

// Medieval-themed name generators
$prefixes = ['Kingdom of', 'Duchy of', 'Barony of', 'Principality of', 'Realm of', 'Shire of', 'County of'];
$names = ['Aldor', 'Briar', 'Carth', 'Dunhelm', 'Eldwyn', 'Fallow', 'Gareth', 'Haven', 'Iver', 'Keld', 'Lorien', 'Mire', 'Norwick', 'Oakmoor'];

$width = rand(20, 40);
$height = rand(30, 60);
$worldName = $prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)];

echo "<h2>Tworzenie świata: $worldName ($width x $height)...</h2>";

try {
    // 1. Dodajemy wpis do tabeli worlds (is_tutorial = 0)
    $stmt = $pdo->prepare("INSERT INTO worlds (name, width, height, is_tutorial) VALUES (?, ?, ?, 0)");
    $stmt->execute([$worldName, $width, $height]);
    $worldId = $pdo->lastInsertId();

    echo "ID nowego świata: $worldId<br>";

    // 2. Generujemy kafelki dla tego konkretnego ID
    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (?, ?, ?, ?)");
    $count = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            
            $type = (rand(0, 1) == 0) ? 'grass' : 'grass2';
            $rand = rand(1, 100);

            if ($rand > 65) $type = 'forest';
            if ($rand > 85) $type = 'mountain';
            if ($rand > 93) $type = 'water';
            
            // Punkt startowy (spawn) w nowym świecie
            if ($x == 0 && $y == 0) $type = 'city_capital';
            
            $stmt->execute([$worldId, $x, $y, $type]);
            $count++;
        }
    }

    // 3. Dodawanie wiosek
    $villagesToPlace = rand(3, 6);
    $vCount = 0;
    $updateStmt = $pdo->prepare("UPDATE map_tiles SET type = 'city_village' WHERE world_id = ? AND x = ? AND y = ?");

    while ($vCount < $villagesToPlace) {
        $vx = rand(2, max(2, $width - 1));
        $vy = rand(2, max(2, $height - 1));
        $updateStmt->execute([$worldId, $vx, $vy]);
        $vCount++;
    }

    echo "<h1 style='color:green'>SUKCES! Dodano świat '$worldName'.</h1>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>WRÓĆ DO GRY</a>";

} catch (PDOException $e) {
    die("Błąd SQL: " . $e->getMessage());
}
?>