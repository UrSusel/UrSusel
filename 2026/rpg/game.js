const mapSize = 20; 
let playerMarker = document.createElement('div');
playerMarker.classList.add('player');

let HEX_WIDTH = 75;   
let HEX_HEIGHT = 22; 

let gameState = {
    x: 0, y: 0, 
    hp: 100, max_hp: 100,
    energy: 10, max_energy: 10,
    xp: 0, max_xp: 100,
    steps_buffer: 0, 
    in_combat: false,
    tutorial_completed: false
};

let inCombatMode = false;
let combatState = null;

// --- AUDIO ---
const AUDIO_PATHS = {
    walk: Array.from({length: 8}, (_, i) => `assets/walking/stepdirt_${i+1}.wav`),
    hit: ['assets/combat/damage/Hit 1.wav', 'assets/combat/damage/Hit 2.wav'],
    damage: Array.from({length: 10}, (_, i) => `assets/combat/damage/damage_${i+1}_ian.wav`),
    combatMusic: "assets/combat/If It's a Fight You Want.ogg"
};

const playlist = ['assets/Journey Across the Blue.ogg', 'assets/World Travelers.ogg'];
let explorationAudio = new Audio();
let combatAudio = new Audio(AUDIO_PATHS.combatMusic);
combatAudio.loop = true;
let isPlaying = false;
explorationAudio.volume = 0.2;
combatAudio.volume = 0.2;

let stepInterval = null;
const playerSprites = {
    idle: ['assets/player/idle1.png', 'assets/player/idle2.png', 'assets/player/idle3.png','assets/player/idle4.png', 'assets/player/idle5.png', 'assets/player/idle6.png','assets/player/idle7.png', 'assets/player/idle8.png', 'assets/player/idle9.png'],
    run: ['assets/player/run1.png', 'assets/player/run2.png', 'assets/player/run3.png', 'assets/player/run4.png', 'assets/player/run5.png', 'assets/player/run6.png']
};

let currentAnimState = 'idle';
let currentFrameIndex = 0;
let animationInterval;
let moveTimeout = null;
const ANIMATION_SPEED = 100;
const MOVEMENT_SPEED_PX = 150; 

function playSoundEffect(category, damageValue = 0) {
    let src = '';
    if (category === 'walk') { src = AUDIO_PATHS.walk[Math.floor(Math.random() * AUDIO_PATHS.walk.length)]; } 
    else if (category === 'hit') { src = AUDIO_PATHS.hit[Math.floor(Math.random() * AUDIO_PATHS.hit.length)]; } 
    else if (category === 'damage') { let index = Math.ceil(damageValue / 2); if (index < 1) index = 1; if (index > 10) index = 10; src = AUDIO_PATHS.damage[index - 1]; }
    if (src) { const sfx = new Audio(src); sfx.volume = 0.3; sfx.play().catch(() => {}); }
}

function startWalkingSound() { if (stepInterval) return; playSoundEffect('walk'); stepInterval = setInterval(() => { playSoundEffect('walk'); }, 400); }
function stopWalkingSound() { if (stepInterval) { clearInterval(stepInterval); stepInterval = null; } }

// --- START ---

function startGame() {
    document.getElementById('start-screen').style.display = 'none';
    playRandomTrack();
    isPlaying = true;
    const btn = document.getElementById('music-btn');
    if(btn) { btn.innerText = '🔊'; btn.classList.add('playing'); }
    initGame();
}

async function initGame() {
    try {
        const res = await fetch('api.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_state' }) 
        });
        const json = await res.json();
        
        if (json.status === 'success') {
            if (json.data.class_id === null) {
                document.getElementById('class-selection').style.display = 'flex';
                return;
            }
            document.getElementById('class-selection').style.display = 'none';

            updateLocalState(json.data);
            
            // UI Świata
            document.getElementById('world-info').innerText = json.data.world_name || 'Nieznany świat';
            updateUI(json.data);
            checkTutorialStatus(); // Sprawdzamy status PO updateLocalState

            if (gameState.in_combat && json.data.combat_state) {
                combatState = JSON.parse(json.data.combat_state);
                toggleCombatMode(true, gameState.hp, json.data.enemy_hp);
            } else {
                await loadAndDrawMap();
                startPlayerAnimation();
                setTimeout(() => { updatePlayerVisuals(gameState.x, gameState.y, true); }, 50);
            }
            renderInventory(json.data.inventory);
            checkLifeStatus();
        }
    } catch(e) { console.error("Init Error:", e); }
}

// Sprawdza czy guzik ma być widoczny
async function checkTutorialStatus() {
    try {
        const json = await apiPost('get_state');
        if (json.status === 'success') {
            const state = json.data ?? json.state ?? json;
            gameState.tutorial_completed = (state.tutorial_completed == 1 || state.tutorial_completed === true);
            const btn = document.getElementById('world-btn');
            if (btn) btn.style.display = gameState.tutorial_completed ? 'inline-block' : 'none';
        } else {
            console.warn('get_state failed', json);
        }
    } catch (e) {
        console.error('checkTutorialStatus error', e);
    }
}
async function showWorldSelection() {
    try {
        const data = await apiPost('get_worlds_list');
        const modal = document.getElementById('world-selection');
        const list = document.getElementById('world-list');
        if (!modal || !list) return;
        list.innerHTML = '';

        if (data.status === 'success' && Array.isArray(data.worlds) && data.worlds.length) {
            data.worlds.forEach(w => {
                const el = document.createElement('div');
                el.className = 'world-item';
                el.style.cursor = 'pointer';
                el.innerHTML = `<strong>${escapeHtml(w.name)}</strong>
                                <div style="font-size:12px;color:#ccc">${w.width}x${w.height} • ${w.player_count} graczy</div>`;
                el.addEventListener('click', () => joinWorld(parseInt(w.id)));
                list.appendChild(el);
            });
        } else {
            list.innerHTML = '<div style="color:#ccc">Brak dostępnych światów</div>';
        }

        modal.style.display = 'flex';
    } catch (e) {
        console.error('showWorldSelection error', e);
        alert('Błąd pobierania listy światów');
    }
}

async function joinWorld(worldId) {
    try {
        const data = await apiPost('join_world', { world_id: worldId });
        if (data.status === 'success') {
            const modal = document.getElementById('world-selection');
            if (modal) modal.style.display = 'none';
            if (typeof initGame === 'function') await initGame();
            else location.reload();
        } else {
            alert(data.message || 'Nie udało się dołączyć do świata');
        }
    } catch (e) {
        console.error('joinWorld error', e);
        alert('Błąd połączenia z serwerem');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[s]));
}
async function apiPost(action, body = {}) {
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action }, body))
        });
        return await res.json();
    } catch (e) {
        console.error('apiPost error', e);
        return { status: 'error', message: 'Network error' };
    }
}
// Expose single global block for inline handlers — ensure no other block re-exports these later.
window.showWorldSelection = showWorldSelection;
window.joinWorld = joinWorld;

// Ensure world button visibility on load
document.addEventListener('DOMContentLoaded', () => {
    checkTutorialStatus().catch(e => console.error(e));
});
window.respawnPlayer = async function() {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'respawn' })
        });
        location.reload();
    } catch (e) {
        console.error('respawnPlayer error', e);
        location.reload();
    }
};
window.selectClass = async function(id) {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'select_class', class_id: id })
        });
        location.reload();
    } catch (e) {
        console.error('selectClass error', e);
    }
};

// Run tutorial check on load
document.addEventListener('DOMContentLoaded', () => {
    checkTutorialStatus().catch(e => console.error(e));
});



async function loadAndDrawMap() {
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_map' })
    });
    const result = await res.json();
    const mapDiv = document.getElementById('map');
    
    if (!result.tiles) return;
    mapDiv.innerHTML = ''; 
    mapDiv.appendChild(playerMarker); 

    result.tiles.forEach(t => { 
        const tile = document.createElement('div');
        tile.className = `tile ${t.type}`;
        
        let offsetX = (parseInt(t.y) % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        let posX = (parseInt(t.x) * HEX_WIDTH) + offsetX;
        let posY = (parseInt(t.y) * HEX_HEIGHT);
        if (t.type === 'mountain') posY -= 10; 
        
        tile.style.left = posX + 'px';
        tile.style.top = posY + 'px';
        tile.style.zIndex = parseInt(t.y);

        tile.dataset.x = t.x; 
        tile.dataset.y = t.y;
        tile.onclick = () => attemptMove(t.x, t.y);
        mapDiv.appendChild(tile);
    });
}

function updatePlayerVisuals(x, y, isInstant = false) {
    const targetTile = document.querySelector(`.tile[data-x='${x}'][data-y='${y}']`);
    if (targetTile) {
        const tLeft = targetTile.offsetLeft;
        const tTop = targetTile.offsetTop;
        const targetPixelX = tLeft - 5; 
        const targetPixelY = tTop - 12;

        if (isInstant) {
            playerMarker.style.transition = 'none';
            playerMarker.style.left = targetPixelX + 'px';
            playerMarker.style.top = targetPixelY + 'px';
            setAnimationState('idle');
        } else {
            const currentLeft = parseFloat(playerMarker.style.left || 0);
            const currentTop = parseFloat(playerMarker.style.top || 0);

            const deltaX = targetPixelX - currentLeft;
            const deltaY = targetPixelY - currentTop;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            const duration = distance / MOVEMENT_SPEED_PX; 

            setAnimationState('run');
            
            playerMarker.style.transition = `top ${duration}s linear, left ${duration}s linear`;
            playerMarker.style.left = targetPixelX + 'px';
            playerMarker.style.top = targetPixelY + 'px';

            if (moveTimeout) clearTimeout(moveTimeout);
            moveTimeout = setTimeout(() => { setAnimationState('idle'); }, duration * 1000);
        }
        playerMarker.style.zIndex = 1000; 
        centerMapOnPlayer(tLeft, tTop);
    }
}

function centerMapOnPlayer(pixelX, pixelY) {
    const panel = document.getElementById('left-panel');
    const map = document.getElementById('map');
    if (!panel || !map) return;
    const moveX = (panel.offsetWidth / 2) - pixelX - 32; 
    const moveY = (panel.offsetHeight / 2) - pixelY - 32;
    map.style.transform = `translate(${moveX}px, ${moveY}px)`;
}

function setAnimationState(newState) {
    if (currentAnimState === newState) return;
    currentAnimState = newState; currentFrameIndex = 0; updatePlayerSprite();
    if (newState === 'run') { startWalkingSound(); } else { stopWalkingSound(); }
}

function startPlayerAnimation() {
    if (animationInterval) clearInterval(animationInterval);
    updatePlayerSprite();
    animationInterval = setInterval(() => {
        currentFrameIndex++;
        if (currentFrameIndex >= playerSprites[currentAnimState].length) currentFrameIndex = 0;
        updatePlayerSprite();
    }, ANIMATION_SPEED);
}

function updatePlayerSprite() {
    const frames = playerSprites[currentAnimState];
    if (frames && frames.length > 0) playerMarker.style.backgroundImage = `url('${frames[currentFrameIndex]}')`;
}

async function attemptMove(targetX, targetY) {
    if (gameState.hp <= 0 || gameState.in_combat) return;
    if (targetX < gameState.x) playerMarker.style.transform = "scaleX(-1)"; else if (targetX > gameState.x) playerMarker.style.transform = "scaleX(1)";

    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move', x: targetX, y: targetY })
    });
    const result = await res.json();

    if (result.status === 'success') {
        gameState.x = result.new_x; gameState.y = result.new_y;
        gameState.hp = parseInt(result.hp); gameState.energy = parseInt(result.energy);
        updatePlayerVisuals(gameState.x, gameState.y, false);
        updateUI(result);
        if (result.encounter) { setTimeout(() => { initGame(); }, 1000); }
    }
}

function renderInventory(inventory) {
    const container = document.getElementById('inventory-grid');
    if (!container) return;
    container.innerHTML = '';
    if (!inventory || inventory.length === 0) { container.innerHTML = '<div style="color:#666; padding:10px;">Pusty plecak...</div>'; return; }
    inventory.forEach(item => {
        const slot = document.createElement('div');
        slot.className = 'item-slot';
        if (item.is_equipped == 1) slot.classList.add('equipped');
        slot.innerHTML = `<div style="font-size:24px;">${item.icon || '📦'}</div><div style="font-size:11px; margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name}</div>${item.quantity > 1 ? `<div style="position:absolute; bottom:2px; right:5px; font-size:10px; color:#aaa;">x${item.quantity}</div>` : ''}`;
        container.appendChild(slot);
    });
}

function toggleCombatMode(active, currentHp, enemyHp = 0) {
    const combatScreen = document.getElementById('combat-screen');
    const mapDiv = document.getElementById('map');
    inCombatMode = active; gameState.in_combat = active;

    if (active && isPlaying) { explorationAudio.pause(); combatAudio.currentTime = 0; combatAudio.play().catch(e => console.log(e)); } 
    else if (!active && isPlaying) { combatAudio.pause(); explorationAudio.play().catch(e => console.log(e)); }

    if (active) {
        mapDiv.style.display = 'none'; combatScreen.style.display = 'flex';
        let existingContainer = document.getElementById('combat-arena-container');
        if (existingContainer) existingContainer.remove();
        let container = document.createElement('div');
        container.id = 'combat-arena-container';
        container.style.width = '550px'; container.style.height = '350px';
        container.style.position = 'relative'; container.style.margin = '20px auto'; 
        combatScreen.insertBefore(container, document.getElementById('combat-log'));
        if (combatState) renderCombatArena();
        document.getElementById('enemy-hp').innerText = enemyHp;
        document.getElementById('combat-hp').innerText = gameState.hp;
        updateApDisplay();
    } else {
        mapDiv.style.display = 'block'; combatScreen.style.display = 'none';
        combatState = null;
        loadAndDrawMap();
        updatePlayerVisuals(gameState.x, gameState.y, true);
    }
}

function updateApDisplay() {
    const log = document.getElementById('combat-log');
    if (combatState && combatState.turn === 'player') { log.innerText = `Twój ruch. AP: ${combatState.player_ap}/2.`; } else { log.innerText = "Tura wroga..."; }
}

function renderCombatArena() {
    const container = document.getElementById('combat-arena-container');
    container.innerHTML = ''; 
    if (!combatState || !combatState.tiles) return;
    combatState.tiles.forEach(t => {
        const tile = document.createElement('div'); tile.className = `tile ${t.type}`;
        let offsetX = (t.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        let posX = (t.x * HEX_WIDTH) + offsetX;
        let posY = (t.y * HEX_HEIGHT);
        tile.style.left = posX + 'px'; tile.style.top = posY + 'px'; tile.style.zIndex = t.y;
        tile.onclick = () => { if(combatState.turn === 'player' && combatState.player_ap >= 1) handleCombatMove(t.x, t.y); };
        container.appendChild(tile);
    });
    createCombatEntity(combatState.player_pos, 'player', container);
    createCombatEntity(combatState.enemy_pos, 'enemy', container);
    updateApDisplay();
    if (combatState.turn === 'enemy') setTimeout(handleEnemyTurn, 500);
}

function createCombatEntity(pos, type, container) {
    const el = document.createElement('div'); el.className = `player ${type}`; el.id = `combat-${type}`; 
    let off = (pos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    el.style.left = ((pos.x * HEX_WIDTH) + off - 5) + 'px';
    el.style.top = ((pos.y * HEX_HEIGHT) - 12) + 'px';
    el.style.zIndex = 100;
    el.style.backgroundImage = `url('assets/player/idle1.png')`;
    if (type === 'enemy') { el.style.filter = "hue-rotate(150deg) brightness(0.8)"; el.style.transform = "scaleX(-1)"; el.onclick = () => { if(combatState.turn === 'player') handleCombatAttack(); }; }
    container.appendChild(el);
}

function animateCombatMove(type, targetPos) {
    const el = document.getElementById(`combat-${type}`);
    if (!el) return;
    let off = (targetPos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    let targetPxX = (targetPos.x * HEX_WIDTH) + off - 5;
    let targetPxY = (targetPos.y * HEX_HEIGHT) - 12;
    el.style.backgroundImage = `url('assets/player/run1.png')`; startWalkingSound();
    el.style.transition = "all 0.4s linear"; el.style.left = targetPxX + 'px'; el.style.top = targetPxY + 'px';
    setTimeout(() => { el.style.backgroundImage = `url('assets/player/idle1.png')`; stopWalkingSound(); }, 400);
}

async function handleCombatMove(x, y) {
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_move', x: x, y: y }) 
    });
    const json = await res.json();
    if (json.status === 'success') { animateCombatMove('player', {x: x, y: y}); setTimeout(() => { combatState = json.combat_state; renderCombatArena(); }, 400); } 
    else { document.getElementById('combat-log').innerText = json.message; }
}

async function handleCombatDefend() {
    if (!combatState || combatState.turn !== 'player') return;
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_defend' }) 
    });
    const json = await res.json();
    if (json.status === 'success') { document.getElementById('combat-log').innerText = json.message; combatState = json.combat_state; renderCombatArena(); }
}

// --- POPRAWIONA FUNKCJA WALKI ---
async function handleCombatAttack() {
    playSoundEffect('hit');
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_attack' }) 
    });
    const json = await res.json();
    if (json.status === 'success') {
        document.getElementById('enemy-hp').innerText = json.enemy_hp;
        document.getElementById('combat-log').innerText = json.log;
        combatState = json.combat_state;
        renderCombatArena();
        if (json.win) { 
            setTimeout(async () => { 
                toggleCombatMode(false); 
                
                // Najpierw odświeżamy stan, żeby flaga tutorial_completed się zaktualizowała
                await initGame();
                
                // Jeśli tutorial właśnie się skończył (wg odpowiedzi z walki)
                if (json.tutorial_finished) {
                    showWorldSelection();
                } else {
                    // Tylko zwykły komunikat jeśli to nie koniec tutoriala
                    alert(json.log);
                }
            }, 1000); 
        }
    } else { document.getElementById('combat-log').innerText = json.message; }
}

async function handleEnemyTurn() {
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'enemy_turn' }) 
    });
    const json = await res.json();
    if (json.status === 'success') {
        const actions = json.actions || [];
        const playAction = (index) => {
            if (index >= actions.length) {
                setTimeout(() => {
                    document.getElementById('combat-hp').innerText = json.hp;
                    document.getElementById('combat-log').innerText = json.log;
                    if (json.player_died) { toggleCombatMode(false); checkLifeStatus(); } else { combatState = json.combat_state; renderCombatArena(); }
                }, 500); return;
            }
            const action = actions[index];
            if (action.type === 'move') { animateCombatMove('enemy', action.to); setTimeout(() => playAction(index + 1), 600); } 
            else if (action.type === 'attack') {
                const pEl = document.getElementById('combat-player'); playSoundEffect('hit');
                if (action.dmg > 0) setTimeout(() => playSoundEffect('damage', action.dmg), 100);
                if(pEl) pEl.style.filter = "brightness(0.5) sepia(1) hue-rotate(-50deg) saturate(5)"; 
                setTimeout(() => { if(pEl) pEl.style.filter = ""; }, 200);
                setTimeout(() => playAction(index + 1), 400);
            }
        }; playAction(0);
    }
}

async function useItem(itemId) {
    if (!inCombatMode) return;
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_use_item', item_id: itemId }) 
    });
    const json = await res.json();
    if (json.status === 'success') {
        document.getElementById('combat-hp').innerText = json.hp;
        document.getElementById('combat-log').innerText = json.message;
        combatState = json.combat_state;
        renderCombatArena();
    }
}

function updateLocalState(data) {
    gameState.x = parseInt(data.pos_x);
    gameState.y = parseInt(data.pos_y);
    gameState.hp = parseInt(data.hp);
    gameState.max_hp = parseInt(data.max_hp) || 100;
    gameState.energy = parseInt(data.energy);
    gameState.max_energy = parseInt(data.max_energy) || 10;
    gameState.xp = parseInt(data.xp);
    gameState.max_xp = parseInt(data.max_xp) || 100;
    gameState.steps_buffer = parseInt(data.steps_buffer);
    gameState.in_combat = (data.in_combat == 1);
    // Luźne porównanie (==) bo PHP może zwrócić "1" lub 1
    gameState.tutorial_completed = (data.tutorial_completed == 1);
}

function updateUI(data) {
    if(!data) return;
    if(data.hp !== undefined) { const maxHp = data.max_hp || gameState.max_hp; document.getElementById('hp').innerText = `${data.hp} / ${maxHp}`; document.getElementById('hp-fill').style.width = (data.hp / maxHp * 100) + '%'; }
    if(data.energy !== undefined) { const maxEn = data.max_energy || gameState.max_energy; document.getElementById('energy').innerText = `${data.energy} / ${maxEn}`; document.getElementById('en-fill').style.width = (data.energy / maxEn * 100) + '%'; }
    if(data.steps_buffer !== undefined) document.getElementById('steps-info').innerText = data.steps_buffer + '/10';
    if(data.xp !== undefined) { const maxXp = data.max_xp || gameState.max_xp; document.getElementById('xp-text').innerText = `${data.xp} / ${maxXp}`; document.getElementById('xp-fill').style.width = (data.xp / maxXp * 100) + '%'; }
    if(data.level) document.getElementById('lvl').innerText = data.level;
}

function checkLifeStatus() { const ds = document.getElementById('death-screen'); if (gameState.hp <= 0) ds.style.display = 'flex'; else ds.style.display = 'none'; }



window.selectClass = async function(id) { await fetch('api.php', { method: 'POST', body: JSON.stringify({ action: 'select_class', class_id: id }) }); location.reload(); }
window.respawnPlayer = async function() { await fetch('api.php', { method: 'POST', body: JSON.stringify({ action: 'respawn' }) }); location.reload(); }
window.switchTab = function(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

function toggleMusic() {
    const btn = document.getElementById('music-btn');
    if (isPlaying) { explorationAudio.pause(); combatAudio.pause(); isPlaying = false; btn.innerText = '🔇'; btn.classList.remove('playing'); } 
    else { if (inCombatMode) { combatAudio.play(); } else { if (!explorationAudio.src) playRandomTrack(); else explorationAudio.play(); } isPlaying = true; btn.innerText = '🔊'; btn.classList.add('playing'); }
}
function setVolume(val) { explorationAudio.volume = val; combatAudio.volume = val; }
function playRandomTrack() { let next = Math.floor(Math.random() * playlist.length); explorationAudio.src = playlist[next]; explorationAudio.play().catch(e => console.log("Autoplay blocked:", e)); }
explorationAudio.addEventListener('ended', playRandomTrack);