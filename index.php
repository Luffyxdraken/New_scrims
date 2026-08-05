<?php
require_once 'config.php';
$db = getDB();

// Fetch Website Settings
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();

// Fetch Active Notices
$notices = $db->query("SELECT * FROM notices WHERE is_active = 1 ORDER BY id DESC")->fetchAll();

// Fetch Scrims
$scrims = $db->query("
    SELECT s.*, g.name as game_name,
    (SELECT COUNT(id) FROM registrations WHERE scrim_id = s.id AND payment_status = 'Approved') as filled_slots
    FROM scrims s
    JOIN games g ON s.game_id = g.id
    ORDER BY s.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($settings['site_name']) ?> - Multi-Game Scrims</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <div class="container nav-container">
        <a href="index.php" class="logo"><?= sanitize($settings['site_name']) ?></a>
        <div>
            <a href="player/login.php" class="btn btn-secondary">Player Portal</a>
            <a href="admin/login.php" class="btn">Admin Panel</a>
        </div>
    </div>
</nav>

<div class="container" style="margin-top: 20px;">
    <?php foreach($notices as $notice): ?>
        <div style="background: rgba(0,240,255,0.1); border-left: 4px solid var(--cyan); padding: 12px; margin-bottom: 15px; border-radius: 4px;">
            <strong>📢 <?= sanitize($notice['title']) ?>:</strong> <?= sanitize($notice['message']) ?>
        </div>
    <?php endforeach; ?>

    <h2 style="text-transform: uppercase; letter-spacing: 1px;">Upcoming Scrims</h2>
    
    <div class="scrim-grid">
        <?php foreach ($scrims as $scrim): ?>
            <div class="card">
                <img src="<?= sanitize($scrim['banner_path']) ?>" class="card-banner" alt="Scrim Poster">
                <div class="card-body">
                    <span class="badge <?= $scrim['mode'] === 'Free' ? 'badge-free' : 'badge-paid' ?>"><?= $scrim['mode'] ?></span>
                    <span class="badge" style="background:#232736"><?= sanitize($scrim['game_name']) ?></span>
                    <span class="badge" style="background:#232736"><?= $scrim['type'] ?></span>
                    
                    <h3 style="margin: 10px 0; font-size: 1.2rem;"><?= sanitize($scrim['title']) ?></h3>
                    <p style="color: var(--text-muted); font-size:0.85rem;">Date: <?= $scrim['scrim_date'] ?> | Time: <?= $scrim['scrim_time'] ?></p>
                    <p style="color: var(--cyan); font-weight:700; margin-top:5px;">Prize Pool: <?= sanitize($scrim['prize_pool']) ?></p>

                    <div style="margin-top:15px; background:#0a0b0e; border-radius:4px; padding:8px;">
                        <div style="display:flex; justify-between; font-size:0.8rem; margin-bottom:5px;">
                            <span>Slots</span>
                            <span><?= $scrim['filled_slots'] ?> / <?= $scrim['total_slots'] ?> Filled</span>
                        </div>
                        <div style="background:#232736; height:6px; border-radius:3px; overflow:hidden;">
                            <div style="background:var(--accent); height:100%; width: <?= ($scrim['filled_slots']/$scrim['total_slots'])*100 ?>%;"></div>
                        </div>
                    </div>

                    <a href="register.php?id=<?= $scrim['id'] ?>" class="btn" style="width:100%; margin-top:15px;">Register Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- AI Helpline Floating Interface -->
<div id="ai-helpline">
    <button onclick="toggleChat()" class="btn" style="border-radius:50%; width:50px; height:50px; padding:0;">🤖</button>
    <div id="chat-window" class="chat-window" style="display:none; position:absolute; bottom:60px; right:0;">
        <div style="background:var(--bg-card-hover); padding:10px; font-weight:700; border-bottom:1px solid var(--border);">AI Assistant</div>
        <div id="chat-messages" class="chat-messages">
            <div class="chat-bubble bot">Hello! Ask me anything about registration, slots, or rules.</div>
        </div>
        <div style="padding:10px; display:flex; gap:5px;">
            <input type="text" id="chat-input" class="form-control" placeholder="Ask a question..." onkeypress="handleKey(event)">
            <button onclick="sendMessage()" class="btn">Send</button>
        </div>
    </div>
</div>

<script>
function toggleChat() {
    const win = document.getElementById('chat-window');
    win.style.display = win.style.display === 'none' ? 'flex' : 'none';
}

function handleKey(e) { if(e.key === 'Enter') sendMessage(); }

function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if(!msg) return;

    const messages = document.getElementById('chat-messages');
    messages.innerHTML += `<div class="chat-bubble user">${msg}</div>`;
    input.value = '';

    fetch('api/ai_helpline.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ query: msg })
    })
    .then(r => r.json())
    .then(data => {
        messages.innerHTML += `<div class="chat-bubble bot">${data.answer}</div>`;
        messages.scrollTop = messages.scrollHeight;
    });
}
</script>
</body>
</html>
