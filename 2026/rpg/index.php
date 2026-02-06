<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>RPG World</title>
    <style>
        /* --- OGÓLNE --- */
        body { 
            background-color: #121212; 
            color: #e0e0e0; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; padding: 0; 
            overflow: hidden; 
            height: 100vh; 
            cursor: url('assets/ui/Cursor_01.png') 20 18, auto;
        }
        button, .tile, .class-card, .tab-btn, .item-slot, input, a, .pointer-cursor, .world-item {
            cursor: url('assets/ui/Cursor_02.png') 20 18, pointer !important;
        }
        #game-layout { display: flex; height: 100vh; }
        
        /* --- TŁO --- */
        #left-panel { 
            flex: 1; overflow: hidden; position: relative; 
            display: flex; align-items: center; justify-content: center; 
            background-color: #050011; 
            background-image: url('img/Starry background  - Layer 02 - Stars.png'), url('img/Starry background  - Layer 01 - Void.png');
            background-repeat: repeat-x; background-size: auto 100%; 
            animation: spaceScroll 60s linear infinite;
        }
        @keyframes spaceScroll { from { background-position: 0 0, 0 0; } to { background-position: -2000px 0, -500px 0; } }
        
        #map { position: absolute; width: 2000px; height: 2000px; background: transparent; top: 0; left: 0; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); }

        /* --- KAFELKI --- */
        .tile { 
            width: 64px; height: 64px; position: absolute; 
            background-size: 100% 100%; background-repeat: no-repeat;
            image-rendering: pixelated; cursor: pointer;
            filter: drop-shadow(0px 5px 5px rgba(0,0,0,0.5));
        }
        .tile.grass { background-image: url('img/grass.png'); }
        .tile.grass2 { background-image: url('img/grass2.png'); }
        .tile.forest { background-image: url('img/forest.png'); }
        .tile.mountain { background-image: url('img/mountain.png'); }
        .tile.water { background-image: url('img/water.png'); }
        .tile.city_capital { background-image: url('img/castle.png'); }
        .tile.city_village { background-image: url('img/vilage.png'); }
        .tile:hover { filter: brightness(1.3) drop-shadow(0 0 10px white); z-index: 100 !important; }

        /* --- GRACZ/WRÓG --- */
        .player { 
            width: 64px; height: 64px; 
            background-repeat: no-repeat; background-position: center bottom; background-size: contain;
            image-rendering: pixelated; position: absolute; z-index: 1000; 
            pointer-events: none; 
            filter: drop-shadow(0px 5px 5px rgba(0,0,0,0.5));
        }
        .player.enemy {
            pointer-events: auto !important; cursor: url('assets/ui/Cursor_02.png') 15 15, crosshair !important;
        }
        .player.enemy:hover {
            transform: scaleX(-1) scale(1.1);
            filter: drop-shadow(0 0 10px red) hue-rotate(150deg) brightness(0.8);
        }

        /* --- UI PANEL --- */
        #right-panel { width: 350px; background: #1e1e1e; border-left: 1px solid #333; display: flex; flex-direction: column; z-index: 200; }
        .tabs { display: flex; background: #252525; border-bottom: 1px solid #333; }
        .tab-btn { background: transparent; color: #888; border: none; padding: 20px; cursor: pointer; flex: 1; font-weight: bold; }
        .tab-btn.active { color: #fff; background: #333; border-bottom: 3px solid #f44336; }
        .tab-content { display: none; padding: 20px; overflow-y: auto; }
        .tab-content.active { display: block; }
        
        .bar-container { width: 100%; height: 8px; background: #333; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        .bar-fill { height: 100%; width: 50%; transition: width 0.5s; }
        .hp-bar { background: #d32f2f; } .en-bar { background: #2196f3; } .xp-bar { background: #00e676; }
        
        .item-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .item-slot { 
            background: #252525; border: 1px solid #444; border-radius: 5px; 
            height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative; transition: all 0.2s;
        }
        .item-slot:hover { border-color: #aaa; background: #333; }
        .item-slot.equipped { border-color: #ffd700; box-shadow: 0 0 5px #ffd700; }
        
        /* Modale */
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 999; display: none; flex-direction: column; align-items: center; justify-content: center; color: white; }
        .combat-btn { padding: 12px 30px; background: #c62828; color: white; border: none; font-size: 16px; margin: 10px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        
        #start-screen { display: flex; z-index: 2000; background: #000; }
        .class-card { padding: 20px; border: 2px solid #444; border-radius: 10px; cursor: pointer; text-align: center; width: 120px; transition: 0.3s; }
        .class-card:hover { border-color: #f44336; background: #222; transform: translateY(-5px); }
        
        /* Lista Światów */
        .world-item { background: #333; border: 1px solid #555; padding: 15px; margin: 5px; width: 300px; text-align: center; border-radius: 5px; transition: 0.2s; }
        .world-item:hover { background: #444; border-color: #00e676; }

        /* Przycisk zmiany świata */
        #world-btn {
            position: absolute; top: 10px; right: 20px; 
            padding: 10px 20px; background: #00e676; color: #000; 
            font-weight: bold; border: none; border-radius: 5px; cursor: pointer;
            z-index: 200; display: none; box-shadow: 0 0 10px rgba(0,230,118,0.5);
        }
        #world-btn:hover { background: #00c853; }

        /* Przycisk zamknięcia (czerwony X, jak w Windows XP) */
        .modal-panel { position: relative; }
        .close-x {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 30px;
            height: 30px;
            background: linear-gradient(#ff5c5c, #d32f2f);
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 0 rgba(0,0,0,0.4), inset 0 -2px 0 rgba(0,0,0,0.12);
            line-height: 0;
            font-size: 16px;
        }
        .close-x:hover { filter: brightness(0.9); transform: translateY(-1px); }

        /* Auth & Character Selection */
        .auth-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 2001; display: none; flex-direction: column; align-items: center; justify-content: center; }
        .auth-form { background: #1b1b1b; padding: 30px; border-radius: 8px; width: 300px; color: #fff; }
        .auth-form h2 { margin-top: 0; color: #00e676; }
        .auth-form input { width: 100%; padding: 10px; margin: 10px 0; background: #252525; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .auth-form button { width: 100%; padding: 12px; background: #00e676; color: #000; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .auth-form button:hover { background: #00c853; }
        .auth-form .toggle-link { text-align: center; margin-top: 15px; color: #888; font-size: 12px; }
        .auth-form .toggle-link a { color: #00e676; cursor: pointer; text-decoration: underline; }

        .char-selection { background: #1b1b1b; padding: 30px; border-radius: 8px; width: 400px; color: #fff; }
        .char-selection h2 { margin-top: 0; color: #00e676; }
        .char-slots { display: flex; flex-direction: column; gap: 10px; margin: 20px 0; }
        .char-slot { background: #252525; padding: 15px; border-radius: 5px; border: 1px solid #444; cursor: pointer; transition: 0.2s; }
        .char-slot:hover { border-color: #00e676; background: #2a2a2a; }
        .char-slot.empty { color: #888; }
        .char-slot-name { font-weight: bold; color: #00e676; }
        .char-slot-class { font-size: 12px; color: #bbb; }
    </style>
</head>
<body>

<div id="auth-modal" class="auth-modal">
    <div class="auth-form">
        <h2 id="auth-title">Zaloguj się</h2>
        <div id="login-form">
            <input type="text" id="login-username" placeholder="Nazwa użytkownika">
            <input type="password" id="login-password" placeholder="Hasło">
            <label style="display:flex; align-items:center; gap:8px; margin:10px 0; color:#bbb; font-size:12px;">
                <input type="checkbox" id="remember-me" style="width:16px; height:16px; cursor:pointer;">
                Zapamiętaj mnie na 7 dni
            </label>
            <button onclick="handleLogin()">Zaloguj</button>
            <div class="toggle-link">Nowe konto? <a onclick="toggleAuthForm()">Zarejestruj się</a></div>
        </div>
        <div id="register-form" style="display:none;">
            <input type="text" id="register-username" placeholder="Nazwa użytkownika">
            <input type="password" id="register-password" placeholder="Hasło">
            <input type="password" id="register-password2" placeholder="Potwierdź hasło">
            <button onclick="handleRegister()">Zarejestruj</button>
            <div class="toggle-link">Masz już konto? <a onclick="toggleAuthForm()">Zaloguj się</a></div>
        </div>
    </div>
</div>

<div id="char-selection-modal" class="modal">
    <div class="char-selection">
        <h2>Wybierz Postać</h2>
        <div class="char-slots" id="char-slots-container"></div>
        <button class="combat-btn" style="width:100%; margin-top:20px;" onclick="document.getElementById('char-selection-modal').style.display='none'">Zamknij</button>
    </div>
</div>

<div id="start-screen" class="modal" style="display:flex;">
    <h1>RPG WORLD</h1>
    <button class="combat-btn" onclick="showAuthModal()">ZALOGUJ SIĘ</button>
</div>

<div id="game-layout">
    <div id="left-panel">
        <div style="position:absolute; top:10px; left:10px; color:#aaa; font-size:12px; z-index:100;">
            ŚWIAT: <span id="world-info" style="color:white; font-weight:bold;">...</span>
        </div>

        <button id="world-btn" style="display:none;" onclick="showWorldSelection()">Wybierz świat 🌐</button>

        <div id="map"></div>
    </div>

    <div id="right-panel">
        <div style="padding:15px; border-bottom:1px solid #333; display:flex; align-items:center; gap:10px; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:10px; flex:1;">
                <button id="music-btn" onclick="toggleMusic()" style="width:40px; height:40px; background:#333; border:none; color:white; border-radius:50%;">🔇</button>
                <div style="font-size:12px; color:#888;">GŁOŚNOŚĆ</div>
                <input type="range" min="0" max="1" step="0.1" oninput="setVolume(this.value)">
            </div>
            <button id="logout-btn" onclick="handleLogout()" style="padding:8px 12px; background:#d32f2f; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px; display:none;">Wyloguj</button>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('stats')">Postać</button>
            <button class="tab-btn" onclick="switchTab('inventory')">Ekwipunek</button>
            <button class="tab-btn" onclick="switchTab('logs')">Dziennik</button>
        </div>

        <div id="tab-stats" class="tab-content active">
            <h2 id="class-name" style="margin:0;">Postać</h2>
            <div style="font-size:12px; color:#888; margin-bottom:20px;">Poziom <span id="lvl">1</span></div>
            <div>Zdrowie: <span id="hp">100 / 100</span></div>
            <div class="bar-container"><div class="bar-fill hp-bar" id="hp-fill"></div></div>
            <div style="margin-top:15px;">Energia: <span id="energy">10 / 10</span></div>
            <div class="bar-container"><div class="bar-fill en-bar" id="en-fill"></div></div>
            <div style="text-align:right; font-size:11px; color:#666;">Kroki: <span id="steps-info">0/10</span></div>
            <div style="margin-top:15px;">XP: <span id="xp-text">0 / 100</span></div>
            <div class="bar-container"><div class="bar-fill xp-bar" id="xp-fill"></div></div>
        </div>

        <div id="tab-inventory" class="tab-content">
            <h3>Plecak</h3>
            <div id="inventory-grid" class="item-grid"></div>
        </div>
        
        <div id="tab-logs" class="tab-content">
            <div id="log-container" style="font-family:monospace; color:#bbb;"></div>
        </div>
    </div>
</div>

<div id="combat-screen" class="modal">
    <h2 style="color:#e53935; margin-bottom:10px;">⚔️ WALKA TAKTYCZNA</h2>
    <div style="display:flex; justify-content:space-between; width:550px; margin-bottom:10px;">
        <div style="text-align:left;">
            <div style="color:#4caf50;">TY (<span id="combat-hp">100</span> HP)</div>
            <div class="bar-container" style="width:200px;"><div class="bar-fill hp-bar" id="combat-hp-bar" style="width:100%"></div></div>
        </div>
        <div style="text-align:right;">
            <div style="color:#f44336;">WRÓG (<span id="enemy-hp">??</span> HP)</div>
            <div class="bar-container" style="width:200px;"><div class="bar-fill hp-bar" style="width:100%; background:#f44336;"></div></div>
        </div>
    </div>
    <div style="margin-top:20px; display:flex; gap:10px; justify-content: center;">
        <button class="combat-btn" style="background:#4caf50;" onclick="handleCombatDefend()">🛡️ Obrona (1AP)</button>
        <button class="combat-btn" style="background:#2196f3;" onclick="useItem(7)">🧪 Mikstura (2AP)</button>
        <button class="combat-btn" style="background:#ff9800;" onclick="useItem(8)">🩹 Bandaż (2AP)</button>
    </div>
    <p id="combat-log" style="color:#bbb; margin-top:15px; font-style:italic; height:20px;">Oczekiwanie...</p>
</div>

<div id="death-screen" class="modal">
    <h1 style="color:#f44336;">POLEGŁEŚ</h1>
    <button class="combat-btn" onclick="respawnPlayer()">Odrodzenie</button>
</div>

<div id="world-selection" class="modal" style="display:none;">
    <div class="modal-panel" style="background:#1b1b1b; padding:20px; border-radius:8px; width:400px; max-height:70vh; overflow:auto; color:#fff;">
        <button class="close-x" onclick="document.getElementById('world-selection').style.display='none'">✖</button>

        <h2 style="color:#00e676; margin-top:0;">Wybierz świat</h2>
        <div id="world-list" style="display:flex; flex-direction:column; gap:8px; margin-top:10px;"></div>
    </div>
</div>
<div id="class-selection" class="modal">
    <h1>Wybierz Klasę</h1>
    <div style="display:flex; gap:20px;">
        <div class="class-card" onclick="selectClass(1)"><h3>Wojownik</h3></div>
        <div class="class-card" onclick="selectClass(2)"><h3>Mag</h3></div>
        <div class="class-card" onclick="selectClass(3)"><h3>Łotrzyk</h3></div>
    </div>
</div>

<script src="game.js"></script>
</body>
</html>