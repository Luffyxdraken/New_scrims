<?php
require_once '../config.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$db = getDB();

$my_scrims = $db->prepare("
    SELECT r.*, s.title, s.scrim_date, s.scrim_time, s.status as scrim_status,
           m.room_id, m.room_password, m.start_time
    FROM registrations r
    JOIN scrims s ON r.scrim_id = s.id
    LEFT JOIN matches m ON m.scrim_id = s.id AND m.status = 'Scheduled'
    WHERE r.player_id = ?
    ORDER BY r.id DESC
");
$my_scrims->execute([$player_id]);
$registrations = $my_scrims->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Player Dashboard - pirtaes.co</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container" style="margin-top:30px;">
    <h2>My Scrim Registrations</h2>

    <div class="scrim-grid">
        <?php foreach($registrations as $reg): 
            // Calculate if Room Details should be visible (10 minutes before start)
            $show_room = false;
            if (!empty($reg['start_time'])) {
                $match_time = strtotime($reg['start_time']);
                $now = time();
                $diff_minutes = ($match_time - $now) / 60;
                if ($diff_minutes <= 10 && $diff_minutes >= -120) {
                    $show_room = true;
                }
            }
        ?>
            <div class="card card-body">
                <h3><?= sanitize($reg['title']) ?></h3>
                <p style="color:var(--text-muted); font-size:0.85rem;">Date: <?= $reg['scrim_date'] ?> AT <?= $reg['scrim_time'] ?></p>
                
                <div style="margin:10px 0;">
                    <span>Status: </span>
                    <strong style="color: <?= $reg['payment_status'] === 'Approved' ? 'var(--success)' : 'var(--accent)' ?>">
                        <?= $reg['payment_status'] ?>
                    </strong>
                </div>

                <div style="background:#0a0b0e; padding:10px; border-radius:4px; margin-top:10px;">
                    <div>Assigned Slot: <strong style="color:var(--cyan);"><?= $reg['slot_number'] ? '#'.$reg['slot_number'] : 'Unassigned' ?></strong></div>
                </div>

                <!-- Protected Room Section -->
                <div style="margin-top:15px; border-top:1px solid var(--border); padding-top:10px;">
                    <h4>Room Credentials</h4>
                    <?php if ($reg['payment_status'] !== 'Approved'): ?>
                        <p style="color:var(--accent); font-size:0.8rem;">Requires approved registration.</p>
                    <?php elseif ($show_room): ?>
                        <div style="background:rgba(0,240,255,0.1); border:1px solid var(--cyan); padding:10px; border-radius:4px; margin-top:5px;">
                            <div>Room ID: <strong><?= sanitize($reg['room_id']) ?></strong></div>
                            <div>Password: <strong><?= sanitize($reg['room_password']) ?></strong></div>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-size:0.8rem;">Room credentials unlock 10 minutes before match start.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
