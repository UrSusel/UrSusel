<?php
require 'db.php';

// --- KONFIGURACJA ROZMIARU MAPY ---
$width = 30;   // Szerokość
$height = 60;  // Wysokość (mapa prostokątna w dół)

echo "<h2>Generowanie mapy $width x $height...</h2>";

try {
    // 1. Czyścimy starą mapę
    $pdo->exec("TRUNCATE TABLE map_tiles");

    // 2. Przygotowujemy wstawianie
    $stmt = $pdo->prepare("INSERT INTO map_tiles (x, y, type) VALUES (?, ?, ?)");
    $count = 0;

    // 3. Główna pętla generowania
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            
            // Losujemy bazowy typ terenu: grass albo grass2
            $type = (rand(0, 1) == 0) ? 'grass' : 'grass2';

            // Dopiero teraz losujemy inne tereny (lasy, góry, wodę)
            $rand = rand(1, 100);

            if ($rand > 65) $type = 'forest';   // 35% szans na las
            if ($rand > 85) $type = 'mountain'; // 15% szans na góry
            if ($rand > 93) $type = 'water';    // 7% szans na wodę
            
            // Stolica zawsze na 0,0
            if ($x == 0 && $y == 0) $type = 'city_capital';
            
            // Zapisujemy kafelek
            $stmt->execute([$x, $y, $type]);
            $count++;
        }
    }

    // 4. Dodawanie losowych wiosek
    $villagesToPlace = 5; 
    $vCount = 0;
    
    $updateStmt = $pdo->prepare("UPDATE map_tiles SET type = 'city_village' WHERE x = ? AND y = ?");

    while ($vCount < $villagesToPlace) {
        $vx = rand(2, $width - 1);
        $vy = rand(2, $height - 1);
        $updateStmt->execute([$vx, $vy]);
        $vCount++;
    }

    echo "<h1 style='color:green'>SUKCES! Wygenerowano $count kafelków.</h1>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>WRÓĆ DO GRY</a>";

} catch (PDOException $e) {
    die("Błąd SQL: " . $e->getMessage());
}
?>