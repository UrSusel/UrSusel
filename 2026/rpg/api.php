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

if (!$char && $action !== 'select_class') {
    echo json_encode(['status' => 'error', 'message' => 'Brak postaci']); exit;
}

$STEPS_PER_ENERGY = 10; 
$MAX_SPEED_NORMAL = 5;
$MAX_SPEED_EXHAUSTED = 1;

// --- FUNKCJE POMOCNICZE ---
function offsetToCube($col, $row) {
    $q = $col - ($row - ($row & 1)) / 2;
    $r = $row;
    $s = -$q - $r;
    return ['q' => $q, 'r' => $r, 's' => $s];
}

function hexDistance($x1, $y1, $x2, $y2) {
    $a = offsetToCube($x1, $y1);
    $b = offsetToCube($x2, $y2);
    return (abs($a['q'] - $b['q']) + abs($a['r'] - $b['r']) + abs($a['s'] - $b['s'])) / 2;
}

// --- NOWE ENDPOINTY ŚWIATA ---

if ($action === 'get_worlds_list') {
    
    $stmt = $pdo->query("
        SELECT w.id, w.name, w.width, w.height, 
        (SELECT COUNT(*) FROM characters c WHERE c.world_id = w.id) as player_count
        FROM worlds w 
        WHERE w.is_tutorial = 0
    ");
    $worlds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'worlds' => $worlds]); exit;
}

if ($action === 'join_world') {
    $targetWorldId = (int)$input['world_id'];
    
    // Sprawdź czy świat istnieje
    $stmt = $pdo->prepare("SELECT id FROM worlds WHERE id = ?");
    $stmt->execute([$targetWorldId]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Świat nie istnieje.']); exit;
    }


    $curWorldId = (int)($char['world_id'] ?? 0);
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
        ->execute([$charId, $curWorldId, (int)$char['pos_x'], (int)$char['pos_y']]);

   
    if ($curWorldId != 1) {
        $posX = (int)($char['pos_x'] ?? 0);
        $posY = (int)($char['pos_y'] ?? 0);
        $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ? LIMIT 1");
        $tileStmt->execute([$posX, $posY, $curWorldId]);
        $curTile = $tileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curTile || strpos($curTile['type'], 'city') === false) {
            echo json_encode(['status' => 'error', 'message' => 'Musisz być w mieście lub wiosce, by zmienić świat.']); exit;
        }
    }

    
    $posStmt = $pdo->prepare("SELECT pos_x, pos_y FROM saved_positions WHERE character_id = ? AND world_id = ? LIMIT 1");
    $posStmt->execute([$charId, $targetWorldId]);
    $saved = $posStmt->fetch(PDO::FETCH_ASSOC);
    $newX = $saved ? (int)$saved['pos_x'] : 0;
    $newY = $saved ? (int)$saved['pos_y'] : 0;

    
    $pdo->prepare("UPDATE characters SET world_id = ?, pos_x = ?, pos_y = ?, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$targetWorldId, $newX, $newY, $charId]);

    echo json_encode(['status' => 'success']); exit;
}

// --- LOGIKA GRY ---

if ($action === 'select_class') {
    $classId = (int)$input['class_id'];
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $cls = $stmt->fetch();
    
    
    $pdo->prepare("UPDATE characters SET class_id = ?, hp = ?, max_hp = ?, energy = ?, max_energy = ?, world_id = 1, tutorial_completed = 0 WHERE id = ?")
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
    
    // Pobierz nazwę świata
    $wStmt = $pdo->prepare("SELECT name FROM worlds WHERE id = ?");
    $wStmt->execute([$char['world_id']]);
    $worldName = $wStmt->fetchColumn();
    $char['world_name'] = $worldName;

    if ($char['in_combat'] && empty($char['combat_state'])) {
        $pdo->prepare("UPDATE characters SET in_combat = 0 WHERE id = ?")->execute([$charId]);
        $char['in_combat'] = 0;
    }

    echo json_encode(['status' => 'success', 'data' => $char]);
    exit;
}

if ($action === 'get_map') {
    
    $stmt = $pdo->prepare("SELECT * FROM map_tiles WHERE world_id = ?");
    $stmt->execute([$char['world_id']]);
    echo json_encode(['status' => 'success', 'tiles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

if ($action === 'move') {
    $targetX = (int)$input['x']; $targetY = (int)$input['y'];

    if ($char['hp'] <= 0) { echo json_encode(['status' => 'dead', 'message' => 'Jesteś martwy.']); exit; }
    if ($char['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Jesteś w walce!']); exit; }

    $currentSpeed = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    $dist = hexDistance($char['pos_x'], $char['pos_y'], $targetX, $targetY);
    
    if ($dist > $currentSpeed) { echo json_encode(['status' => 'error', 'message' => 'Za daleko!']); exit; }

    
    $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ?");
    $tileStmt->execute([$targetX, $targetY, $char['world_id']]);
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
        $char['steps_buffer'] += $dist;
        while ($char['steps_buffer'] >= $STEPS_PER_ENERGY) {
            if ($char['energy'] > 0) { $char['energy']--; $char['steps_buffer'] -= $STEPS_PER_ENERGY; } else break;
        }

        
        $chance = 15;
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $chance = 35; // 35% szansy w tutorialu żeby szybko spotkać wroga
        }

        if (rand(1, 100) <= $chance) { 
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

        
        $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
            ->execute([$charId, (int)$char['world_id'], $targetX, $targetY]);
    } else {
        
        $pdo->prepare("UPDATE characters SET in_combat = 1, enemy_hp = ?, enemy_max_hp = ?, pos_x = ?, pos_y = ?, energy = ?, steps_buffer = ?, combat_state = ? WHERE id = ?")
            ->execute([$enemyHp, $enemyHp, $targetX, $targetY, $char['energy'], $char['steps_buffer'], json_encode($combatState), $charId]);

        $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
            ->execute([$charId, (int)$char['world_id'], $targetX, $targetY]);
        $msg = "⚔️ ZASADZKA!";
    }

    echo json_encode([
        'status' => 'success', 'new_x' => $targetX, 'new_y' => $targetY,
        'hp' => $char['hp'], 'energy' => $char['energy'], 'steps_buffer' => $char['steps_buffer'],
        'encounter' => $encounter, 'message' => $msg, 'enemy_hp' => $enemyHp
    ]);
    exit;
}

if ($action === 'respawn') {
    $stmt = $pdo->prepare("SELECT max_hp, max_energy, world_id FROM characters WHERE id = ?");
    $stmt->execute([$charId]);
    $stats = $stmt->fetch();
    $pdo->prepare("UPDATE characters SET hp = ?, energy = ?, steps_buffer = 0, pos_x = 0, pos_y = 0, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$stats['max_hp'], $stats['max_energy'], $charId]);

    
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, 0, 0)
        ON DUPLICATE KEY UPDATE pos_x = 0, pos_y = 0")
        ->execute([$charId, (int)$stats['world_id']]);

    echo json_encode(['status' => 'success']); exit;
}

// --- WALKA ---

if ($action === 'combat_move') {
    $tx = (int)$input['x']; $ty = (int)$input['y'];
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }

    $tileType = 'water';
    foreach ($cState['tiles'] as $t) { if ($t['x'] == $tx && $t['y'] == $ty) { $tileType = $t['type']; break; } }
    if ($tileType === 'water') { echo json_encode(['status' => 'error', 'message' => 'Woda!']); exit; }
    if ($tx == $cState['enemy_pos']['x'] && $ty == $cState['enemy_pos']['y']) { echo json_encode(['status' => 'error', 'message' => 'Tam stoi wróg!']); exit; }
    $dist = hexDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $tx, $ty);
    if ($dist > 1.1) { echo json_encode(['status' => 'error', 'message' => 'Za daleko.']); exit; }
    
    $cState['player_ap'] -= 1;
    $cState['player_pos'] = ['x' => $tx, 'y' => $ty];
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState]); exit;
}

if ($action === 'combat_defend') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }
    $cState['player_ap'] -= 1;
    $cState['is_defending'] = true; 
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState, 'message' => '🛡️ Postawa obronna! (-50% obrażeń)']); exit;
}

if ($action === 'combat_attack') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['player_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Atak wymaga 2 AP!']); exit; }
    $dist = hexDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $cState['enemy_pos']['x'], $cState['enemy_pos']['y']);
    if ($dist > 1.1) { echo json_encode(['status' => 'error', 'message' => 'Wróg za daleko!']); exit; }
    
    $invStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'weapon'");
    $invStmt->execute([$charId]);
    $weaponDmg = $invStmt->fetchColumn() ?: 0;
    
    $dmg = rand(10, 15) + $char['base_attack'] + $weaponDmg;
    $char['enemy_hp'] -= $dmg;
    $cState['player_ap'] = 0; 
    
    $log = "Zadajesz $dmg obrażeń!";
    $win = false;
    $tutorialFinishedNow = false;
    
    if ($char['enemy_hp'] <= 0) {
        $win = true; $xp = rand(15, 25);
        $char['xp'] += $xp; $char['in_combat'] = 0; $char['combat_state'] = NULL;
        
        // --- SPRAWDZENIE UKOŃCZENIA TUTORIALU ---
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $pdo->prepare("UPDATE characters SET tutorial_completed = 1 WHERE id = ?")->execute([$charId]);
            $char['tutorial_completed'] = 1;
            $tutorialFinishedNow = true;
            $log .= " WYGRANA! Ukończyłeś Tutorial!";
        } else {
            if ($char['xp'] >= $char['max_xp']) {
                $char['level']++; $char['xp'] = 0; $char['max_xp'] *= 1.2;
                $char['max_hp'] += 10; $char['hp'] = $char['max_hp'];
                $log .= " WYGRANA! AWANS!";
            } else {
                $log .= " WYGRANA!";
            }
        }
    } else {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }
    
    $pdo->prepare("UPDATE characters SET hp=?, enemy_hp=?, xp=?, max_xp=?, level=?, max_hp=?, in_combat=?, combat_state=? WHERE id=?")
        ->execute([$char['hp'], max(0,$char['enemy_hp']), $char['xp'], $char['max_xp'], $char['level'], $char['max_hp'], $char['in_combat'], json_encode($cState), $charId]);
        
    
    echo json_encode([
        'status' => 'success', 
        'enemy_hp' => max(0,$char['enemy_hp']), 
        'win' => $win, 
        'log' => $log, 
        'combat_state' => $cState,
        'tutorial_finished' => $tutorialFinishedNow
    ]); exit;
}

if ($action === 'combat_use_item') {
    $itemId = (int)$input['item_id'];
    $stmt = $pdo->prepare("SELECT inventory.id, items.power, inventory.quantity FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || $item['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak przedmiotu!']); exit; }
    
    $heal = $item['power'];
    $char['hp'] = min($char['max_hp'], $char['hp'] + $heal);
    if ($item['quantity'] > 1) { $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$item['id']]); } 
    else { $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]); }
    
    $cState = json_decode($char['combat_state'], true);
    $cState['player_ap'] = 0; $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2;

    $pdo->prepare("UPDATE characters SET hp = ?, combat_state = ? WHERE id = ?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'hp' => $char['hp'], 'combat_state' => $cState, 'message' => "Uleczono o $heal HP. Tura wroga."]); exit;
}

if ($action === 'enemy_turn') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'enemy') { echo json_encode(['status' => 'error']); exit; }
    $log = ""; $actions_performed = []; 
    
    while ($cState['enemy_ap'] > 0) {
        $pl = $cState['player_pos']; $en = $cState['enemy_pos'];
        $dist = hexDistance($pl['x'], $pl['y'], $en['x'], $en['y']);
        
        if ($dist <= 1.1 && $cState['enemy_ap'] >= 2) {
            $dmg = rand(10, 18);
            if (!empty($cState['is_defending'])) {
                $dmg = ceil($dmg * 0.5);
                $log = "Wróg atakuje! Blokujesz ($dmg dmg).";
            } else {
                $log = "Wróg atakuje! Tracisz $dmg HP.";
            }
            $char['hp'] -= $dmg;
            $cState['enemy_ap'] = 0;
            $actions_performed[] = ['type' => 'attack', 'dmg' => $dmg];
            break;
        } else if ($cState['enemy_ap'] >= 1) {
            $offsets = ($en['y'] % 2 != 0) ? [[1,0], [1,-1], [0,-1], [-1,0], [0,1], [1,1]] : [[1,0], [0,-1], [-1,-1], [-1,0], [-1,1], [0,1]];
            $bestMove = null; $minDist = 999;
            foreach ($offsets as $o) {
                $nx = $en['x'] + $o[0]; $ny = $en['y'] + $o[1];
                $valid = false;
                foreach ($cState['tiles'] as $t) { if ($t['x'] == $nx && $t['y'] == $ny && $t['type'] !== 'water') { $valid = true; break; } }
                if ($nx == $pl['x'] && $ny == $pl['y']) $valid = false; 
                if ($valid) { $d = hexDistance($nx, $ny, $pl['x'], $pl['y']); if ($d < $minDist) { $minDist = $d; $bestMove = ['x' => $nx, 'y' => $ny]; } }
            }
            if ($bestMove) { $cState['enemy_pos'] = $bestMove; $cState['enemy_ap'] -= 1; $actions_performed[] = ['type' => 'move', 'to' => $bestMove]; } 
            else { $cState['enemy_ap'] = 0; }
        } else { $cState['enemy_ap'] = 0; }
    }
    
    $cState['turn'] = 'player'; $cState['player_ap'] = 2; $cState['is_defending'] = false; 
    $died = ($char['hp'] <= 0); if ($died) { $char['hp'] = 0; $cState = NULL; }
    $pdo->prepare("UPDATE characters SET hp=?, combat_state=? WHERE id=?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status'=>'success', 'hp'=>$char['hp'], 'log'=>$log, 'combat_state'=>$cState, 'player_died'=>$died, 'actions' => $actions_performed]); exit;
}
?>