<?php
require 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$userId = 1; 

// Pobranie postaci
$stmt = $pdo->prepare("SELECT * FROM characters WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$char = $stmt->fetch(PDO::FETCH_ASSOC);
$charId = $char['id'] ?? 0;

if (!$char) {
    echo json_encode(['status' => 'error', 'message' => 'Brak postaci']); exit;
}

$STEPS_PER_ENERGY = 10; 
$MAX_SPEED_NORMAL = 5;
$MAX_SPEED_EXHAUSTED = 1;

// --- PRECYZYJNA LOGIKA SĄSIADÓW (Odd-Row) ---
// To jest "święta lista" sąsiadów. Tylko te przesunięcia to dystans 1.
function getNeighbors($x, $y) {
    $neighbors = [];
    // Dla rzędów nieparzystych (1, 3, 5...) - przesunięte w prawo
    if ($y % 2 !== 0) { 
        $offsets = [
            [1, 0], [-1, 0],  // Prawo, Lewo
            [0, -1], [1, -1], // Góra-Lewo, Góra-Prawo
            [0, 1], [1, 1]    // Dół-Lewo, Dół-Prawo
        ];
    } 
    // Dla rzędów parzystych (0, 2, 4...)
    else { 
        $offsets = [
            [1, 0], [-1, 0],  // Prawo, Lewo
            [-1, -1], [0, -1],// Góra-Lewo, Góra-Prawo
            [-1, 1], [0, 1]   // Dół-Lewo, Dół-Prawo
        ];
    }

    foreach ($offsets as $o) {
        $neighbors[] = ['x' => $x + $o[0], 'y' => $y + $o[1]];
    }
    return $neighbors;
}

// Funkcja sprawdzająca czy pola są bezpośrednimi sąsiadami
function areNeighbors($x1, $y1, $x2, $y2) {
    $nbs = getNeighbors($x1, $y1);
    foreach ($nbs as $n) {
        if ($n['x'] == $x2 && $n['y'] == $y2) return true;
    }
    return false;
}

// Algorytm BFS do liczenia faktycznej odległości w krokach
// To naprawia błąd, gdzie skok na skos liczony był jako 1. Teraz policzy jako 2.
function getPathDistance($sx, $sy, $ex, $ey) {
    if ($sx == $ex && $sy == $ey) return 0;
    
    $queue = [['x' => $sx, 'y' => $sy, 'd' => 0]];
    $visited = ["$sx,$sy" => true];
    
    $maxDepth = 20; // Zabezpieczenie przed zbyt długim szukaniem

    while (!empty($queue)) {
        $current = array_shift($queue);
        if ($current['d'] >= $maxDepth) continue;

        $nbs = getNeighbors($current['x'], $current['y']);
        foreach ($nbs as $n) {
            if ($n['x'] == $ex && $n['y'] == $ey) return $current['d'] + 1;
            
            $key = "{$n['x']},{$n['y']}";
            if (!isset($visited[$key])) {
                $visited[$key] = true;
                $queue[] = ['x' => $n['x'], 'y' => $n['y'], 'd' => $current['d'] + 1];
            }
        }
    }
    return 999; // Brak ścieżki lub za daleko
}

// Stara funkcja, potrzebna tylko pomocniczo do renderowania mapy, nie do ruchu
function offsetToCube($col, $row) {
    $x = $col - ($row - ($row & 1)) / 2;
    $z = $row;
    $y = -$x - $z;
    return ['x' => $x, 'y' => $y, 'z' => $z];
}
function hexDistance($x1, $y1, $x2, $y2) {
    $a = offsetToCube($x1, $y1);
    $b = offsetToCube($x2, $y2);
    return (abs($a['x'] - $b['x']) + abs($a['y'] - $b['y']) + abs($a['z'] - $b['z'])) / 2;
}

// --- AKCJE API ---

if ($action === 'select_class') {
    $classId = (int)$input['class_id'];
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $cls = $stmt->fetch();
    
    $pdo->prepare("UPDATE characters SET class_id = ?, hp = ?, max_hp = ?, energy = ?, max_energy = ? WHERE id = ?")
        ->execute([$classId, $cls['base_hp'], $cls['base_hp'], $cls['base_energy'], $cls['base_energy'], $charId]);
    
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ?")->execute([$charId]);
    $weaponId = $classId; $armorId = ($classId==1)?5:($classId==2?6:4);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, is_equipped) VALUES (?, ?, 1), (?, ?, 1)")->execute([$charId, $weaponId, $charId, $armorId]);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, 7, 3), (?, 8, 3)")->execute([$charId, $charId]);

    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'get_state') {
    $invStmt = $pdo->prepare("SELECT i.id as item_id, i.name, i.type, i.power, i.icon, inv.quantity, inv.is_equipped FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.character_id = ?");
    $invStmt->execute([$charId]);
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAttack = 1 + ($char['base_attack'] ?? 1); 
    foreach ($inventory as $item) {
        if ($item['is_equipped'] && $item['type'] == 'weapon') $totalAttack += $item['power'];
    }

    $char['attack'] = $totalAttack;
    $char['inventory'] = $inventory;
    $char['speed'] = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    
    if ($char['in_combat'] && empty($char['combat_state'])) {
        $pdo->prepare("UPDATE characters SET in_combat = 0 WHERE id = ?")->execute([$charId]);
        $char['in_combat'] = 0;
    }

    echo json_encode(['status' => 'success', 'data' => $char]);
    exit;
}

if ($action === 'get_map') {
    $stmt = $pdo->query("SELECT * FROM map_tiles");
    echo json_encode(['status' => 'success', 'tiles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

if ($action === 'move') {
    $targetX = (int)$input['x']; $targetY = (int)$input['y'];

    if ($char['hp'] <= 0) { echo json_encode(['status' => 'dead', 'message' => 'Jesteś martwy.']); exit; }
    if ($char['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Jesteś w walce!']); exit; }

    // TUTAJ ZMIANA: Używamy getPathDistance zamiast hexDistance
    // To policzy faktyczną liczbę kroków
    $dist = getPathDistance($char['pos_x'], $char['pos_y'], $targetX, $targetY);
    
    $currentSpeed = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    
    if ($dist > $currentSpeed) { echo json_encode(['status' => 'error', 'message' => 'Za daleko!']); exit; }

    $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ?");
    $tileStmt->execute([$targetX, $targetY]);
    $targetTile = $tileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetTile || $targetTile['type'] === 'water' || $targetTile['type'] === 'mountain') {
        echo json_encode(['status' => 'error', 'message' => 'Teren niedostępny!']); exit;
    }

    $isSafe = (strpos($targetTile['type'], 'city') !== false);
    $encounter = false; $enemyHp = 0; $msg = "Podróżujesz...";

    if ($isSafe) {
        $char['hp'] = $char['max_hp']; $char['energy'] = $char['max_energy']; $char['steps_buffer'] = 0;
        $msg = "Odpoczywasz w mieście.";
    } else {
        // Zużywamy energię na podstawie faktycznej liczby kroków ($dist)
        $char['steps_buffer'] += $dist;
        while ($char['steps_buffer'] >= $STEPS_PER_ENERGY) {
            if ($char['energy'] > 0) { $char['energy']--; $char['steps_buffer'] -= $STEPS_PER_ENERGY; } else break;
        }

        if (rand(1, 100) <= 15) { 
            $encounter = true;
            $enemyHp = rand(30, 60);
            
            $arenaTiles = [];
            for ($ay = 0; $ay < 5; $ay++) {
                for ($ax = 0; $ax < 7; $ax++) {
                    $r = rand(1, 100);
                    $atype = 'grass';
                    if ($r > 60) $atype = 'grass2';
                    if ($r > 90) $atype = 'water';
                    if (($ax == 1 && $ay == 2) || ($ax == 5 && $ay == 2)) $atype = 'grass';
                    $arenaTiles[] = ['x' => $ax, 'y' => $ay, 'type' => $atype];
                }
            }
            
            $combatState = [
                'player_pos' => ['x' => 1, 'y' => 2],
                'enemy_pos' => ['x' => 5, 'y' => 2],
                'tiles' => $arenaTiles,
                'turn' => 'player',
                'player_ap' => 2,
                'enemy_ap' => 2,
                'is_defending' => false
            ];
            
            $pdo->prepare("UPDATE characters SET in_combat = 1, enemy_hp = ?, enemy_max_hp = ?, pos_x = ?, pos_y = ?, energy = ?, steps_buffer = ?, combat_state = ? WHERE id = ?")
                ->execute([$enemyHp, $enemyHp, $targetX, $targetY, $char['energy'], $char['steps_buffer'], json_encode($combatState), $charId]);
            $msg = "⚔️ ZASADZKA!";
        }
    }

    if (!$encounter) {
        $pdo->prepare("UPDATE characters SET pos_x = ?, pos_y = ?, hp = ?, energy = ?, steps_buffer = ? WHERE id = ?")
            ->execute([$targetX, $targetY, $char['hp'], $char['energy'], $char['steps_buffer'], $charId]);
    }

    echo json_encode([
        'status' => 'success', 'new_x' => $targetX, 'new_y' => $targetY,
        'hp' => $char['hp'], 'energy' => $char['energy'], 'steps_buffer' => $char['steps_buffer'],
        'encounter' => $encounter, 'message' => $msg, 'enemy_hp' => $enemyHp
    ]);
    exit;
}

if ($action === 'respawn') {
    $stmt = $pdo->prepare("SELECT max_hp, max_energy FROM characters WHERE id = ?");
    $stmt->execute([$charId]);
    $stats = $stmt->fetch();
    $pdo->prepare("UPDATE characters SET hp = ?, energy = ?, steps_buffer = 0, pos_x = 0, pos_y = 0, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$stats['max_hp'], $stats['max_energy'], $charId]);
    echo json_encode(['status' => 'success']); exit;
}

// --- LOGIKA WALKI ---

if ($action === 'combat_move') {
    $tx = (int)$input['x']; $ty = (int)$input['y'];
    $cState = json_decode($char['combat_state'], true);
    
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }

    // Walidacja sąsiedztwa w walce - STRICT
    if (!areNeighbors($cState['player_pos']['x'], $cState['player_pos']['y'], $tx, $ty)) {
        echo json_encode(['status' => 'error', 'message' => 'Za daleko! Możesz iść tylko o 1 pole.']); exit;
    }

    // Walidacja zajętości
    if ($tx == $cState['enemy_pos']['x'] && $ty == $cState['enemy_pos']['y']) { 
        echo json_encode(['status' => 'error', 'message' => 'Tam stoi wróg!']); exit; 
    }
    
    $cState['player_ap'] -= 1;
    $cState['player_pos'] = ['x' => $tx, 'y' => $ty];

    if ($cState['player_ap'] <= 0) {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }
    
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState]); exit;
}

if ($action === 'combat_defend') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }

    $cState['player_ap'] -= 1;
    $cState['is_defending'] = true;

    if ($cState['player_ap'] <= 0) {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }

    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState, 'message' => 'Przyjmujesz postawę obronną (-50% obrażeń).']); exit;
}

if ($action === 'combat_attack') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['player_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Atak wymaga 2 AP!']); exit; }

    // Walidacja sąsiedztwa przy ataku
    if (!areNeighbors($cState['player_pos']['x'], $cState['player_pos']['y'], $cState['enemy_pos']['x'], $cState['enemy_pos']['y'])) {
        echo json_encode(['status' => 'error', 'message' => 'Wróg za daleko! Podejdź bliżej.']); exit;
    }
    
    $invStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'weapon'");
    $invStmt->execute([$charId]);
    $weaponDmg = $invStmt->fetchColumn() ?: 0;
    
    $dmg = rand(10, 15) + $char['base_attack'] + $weaponDmg;
    $char['enemy_hp'] -= $dmg;
    $cState['player_ap'] = 0; 
    
    $log = "Zadajesz $dmg obrażeń!";
    $win = false;
    
    if ($char['enemy_hp'] <= 0) {
        $win = true; $xp = rand(15, 25);
        $char['xp'] += $xp; $char['in_combat'] = 0; $char['combat_state'] = NULL;
        if ($char['xp'] >= $char['max_xp']) {
            $char['level']++; $char['xp'] = 0; $char['max_xp'] *= 1.2;
            $char['max_hp'] += 10; $char['hp'] = $char['max_hp'];
            $log .= " WYGRANA! AWANS!";
        } else {
            $log .= " WYGRANA!";
        }
    } else {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }
    
    $pdo->prepare("UPDATE characters SET hp=?, enemy_hp=?, xp=?, max_xp=?, level=?, max_hp=?, in_combat=?, combat_state=? WHERE id=?")
        ->execute([$char['hp'], max(0,$char['enemy_hp']), $char['xp'], $char['max_xp'], $char['level'], $char['max_hp'], $char['in_combat'], json_encode($cState), $charId]);
        
    echo json_encode(['status' => 'success', 'enemy_hp' => max(0,$char['enemy_hp']), 'win' => $win, 'log' => $log, 'combat_state' => $cState]); exit;
}

if ($action === 'combat_use_item') {
    $itemId = (int)$input['item_id'];
    $stmt = $pdo->prepare("SELECT inventory.id, items.power, inventory.quantity FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item || $item['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak przedmiotu!']); exit; }
    
    $heal = $item['power'];
    $char['hp'] = min($char['max_hp'], $char['hp'] + $heal);
    
    if ($item['quantity'] > 1) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$item['id']]);
    } else {
        $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]);
    }
    
    $cState = json_decode($char['combat_state'], true);
    $cState['player_ap'] = 0;
    $cState['turn'] = 'enemy';
    $cState['enemy_ap'] = 2;

    $pdo->prepare("UPDATE characters SET hp = ?, combat_state = ? WHERE id = ?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'hp' => $char['hp'], 'combat_state' => $cState, 'message' => "Uleczono o $heal HP. Tura wroga."]); exit;
}

if ($action === 'enemy_turn') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'enemy') { echo json_encode(['status' => 'error']); exit; }
    
    $log = "";
    $actions_performed = []; 
    
    while ($cState['enemy_ap'] > 0) {
        $pl = $cState['player_pos']; $en = $cState['enemy_pos'];
        
        // Sprawdzamy sąsiedztwo - atak jeśli blisko
        $isNeighbor = areNeighbors($en['x'], $en['y'], $pl['x'], $pl['y']);
        
        if ($isNeighbor && $cState['enemy_ap'] >= 2) {
            $dmg = rand(10, 18);
            
            if (isset($cState['is_defending']) && $cState['is_defending'] === true) {
                $dmg = ceil($dmg * 0.5);
                $log = "Wróg atakuje! Twoja obrona zmniejsza obrażenia do $dmg HP.";
            } else {
                $log = "Wróg atakuje! Tracisz $dmg HP.";
            }
            
            $char['hp'] -= $dmg;
            $cState['enemy_ap'] = 0;
            $actions_performed[] = ['type' => 'attack', 'dmg' => $dmg];
            break;
        } else if ($cState['enemy_ap'] >= 1) {
            // Ruch wroga - używamy tej samej logiki sąsiadów
            $potentialMoves = getNeighbors($en['x'], $en['y']);
            $bestMove = null; 
            $minDist = 999;

            foreach ($potentialMoves as $nxny) {
                $nx = $nxny['x'];
                $ny = $nxny['y'];
                
                $tileExists = false;
                foreach ($cState['tiles'] as $t) { 
                    if ($t['x'] == $nx && $t['y'] == $ny && $t['type'] !== 'water') { 
                        $tileExists = true; 
                        break; 
                    } 
                }
                
                if (!$tileExists) continue;
                if ($nx == $pl['x'] && $ny == $pl['y']) continue; 
                
                // Liczymy dystans BFS do gracza
                $d = getPathDistance($nx, $ny, $pl['x'], $pl['y']);
                if ($d < $minDist) { 
                    $minDist = $d; 
                    $bestMove = ['x' => $nx, 'y' => $ny]; 
                }
            }

            if ($bestMove) { 
                $cState['enemy_pos'] = $bestMove; 
                $cState['enemy_ap'] -= 1; 
                $actions_performed[] = ['type' => 'move', 'to' => $bestMove]; 
            } else { 
                $cState['enemy_ap'] = 0; 
            }
        } else { 
            $cState['enemy_ap'] = 0; 
        }
    }
    
    $cState['turn'] = 'player';
    $cState['player_ap'] = 2;
    $cState['is_defending'] = false; 
    
    $died = ($char['hp'] <= 0);
    if ($died) { $char['hp'] = 0; $cState = NULL; }
    
    $pdo->prepare("UPDATE characters SET hp=?, combat_state=? WHERE id=?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status'=>'success', 'hp'=>$char['hp'], 'log'=>$log, 'combat_state'=>$cState, 'player_died'=>$died, 'actions' => $actions_performed]); exit;
}
?>