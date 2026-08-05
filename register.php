<?php
require_once 'config.php';
$db = getDB();

$scrim_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$scrim_id) { die("Invalid Scrim"); }

$scrim = $db->prepare("SELECT s.*, g.slug as game_slug FROM scrims s JOIN games g ON s.game_id = g.id WHERE s.id = ?");
$scrim->execute([$scrim_id]);
$scrim = $scrim->fetch();

if (!$scrim) { die("Scrim not found"); }

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in_game_id = sanitize($_POST['in_game_id']);
    $ign = sanitize($_POST['ign']);
    $player_name = sanitize($_POST['player_name']);
    $whatsapp = sanitize($_POST['whatsapp']);
    $team_name = sanitize($_POST['team_name'] ?? '');
    $txn_id = sanitize($_POST['txn_id'] ?? '');

    try {
        $db->beginTransaction();

        // 1. Handle File Uploads
        $profile_ss = uploadFile($_FILES['profile_ss'], 'uploads/profiles/');
        $payment_ss = null;
        if ($scrim['mode'] === 'Paid' && isset($_FILES['payment_ss'])) {
            $payment_ss = uploadFile($_FILES['payment_ss'], 'uploads/payments/');
        }

        // 2. Prevent Duplicate Registration
        $checkPlayer = $db->prepare("SELECT id FROM players WHERE in_game_id = ?");
        $checkPlayer->execute([$in_game_id]);
        $player = $checkPlayer->fetch();

        if (!$player) {
            $insPlayer = $db->prepare("INSERT INTO players (in_game_id, whatsapp, ign, player_name, profile_ss) VALUES (?, ?, ?, ?, ?)");
            $insPlayer->execute([$in_game_id, $whatsapp, $ign, $player_name, $profile_ss]);
            $player_id = $db->lastInsertId();
        } else {
            $player_id = $player['id'];
        }

        // Check if player is already registered in this scrim
        $checkReg = $db->prepare("SELECT id FROM registrations WHERE scrim_id = ? AND player_id = ?");
        $checkReg->execute([$scrim_id, $player_id]);
        if ($checkReg->fetch()) {
            throw new Exception("This In-Game ID is already registered for this scrim!");
        }

        // 3. Determine Slot Number and Approval Status
        $payment_status = ($scrim['mode'] === 'Free' && $scrim['reg_type'] === 'Auto') ? 'Approved' : 'Pending';
        
        // Find next open slot if auto-approved
        $slot_number = null;
        if ($payment_status === 'Approved') {
            $stmt = $db->prepare("SELECT slot_number FROM registrations WHERE scrim_id = ? AND slot_number IS NOT NOT NULL ORDER BY slot_number ASC");
            $stmt->execute([$scrim_id]);
            $taken_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            for ($i = 1; $i <= $scrim['total_slots']; $i++) {
                if (!in_array($i, $taken_slots)) {
                    $slot_number = $i;
                    break;
                }
            }
        }

        // 4. Save Registration
        $insReg = $db->prepare("INSERT INTO registrations (scrim_id, player_id, team_name, slot_number, payment_status, payment_ss, txn_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insReg->execute([$scrim_id, $player_id, $team_name, $slot_number, $payment_status, $payment_ss, $txn_id]);
        $reg_id = $db->lastInsertId();

        // 5. Store Team Members (Duo/Squad)
        if ($scrim['type'] !== 'Solo' && isset($_POST['members'])) {
            $insMember = $db->prepare("INSERT INTO team_members (registration_id, player_number, ign, in_game_id) VALUES (?, ?, ?, ?)");
            foreach ($_POST['members'] as $index => $member) {
                if (!empty($member['ign']) && !empty($member['id'])) {
                    $insMember->execute([$reg_id, $index + 2, sanitize($member['ign']), sanitize($member['id'])]);
                }
            }
        }

        $db->commit();
        $message = "<div style='color:var(--success); margin-bottom:15px;'>Registration submitted! Status: $payment_status</div>";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "<div style='color:var(--accent); margin-bottom:15px;'>Error: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - <?= sanitize($scrim['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container" style="max-width:600px; margin-top:40px;">
    <div class="card card-body">
        <h2>Register for <?= sanitize($scrim['title']) ?></h2>
        <p style="color:var(--text-muted); margin-bottom:20px;">Mode: <?= $scrim['mode'] ?> | Entry Fee: ₹<?= $scrim['entry_fee'] ?></p>
        
        <?= $message ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Player Name</label>
                <input type="text" name="player_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label><?= strtoupper($scrim['game_slug']) ?> In-Game ID (Numeric)</label>
                <input type="text" name="in_game_id" class="form-control" required>
            </div>

            <div class="form-group">
                <label><?= strtoupper($scrim['game_slug']) ?> In-Game Name (IGN)</label>
                <input type="text" name="ign" class="form-control" required>
            </div>

            <div class="form-group">
                <label>WhatsApp Number</label>
                <input type="text" name="whatsapp" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Profile Screenshot</label>
                <input type="file" name="profile_ss" class="form-control" accept="image/*" required>
            </div>

            <?php if ($scrim['type'] !== 'Solo'): ?>
                <div class="form-group">
                    <label>Team Name</label>
                    <input type="text" name="team_name" class="form-control" required>
                </div>

                <div id="team-members">
                    <h4>Team Members Info</h4>
                    <?php 
                    $count = $scrim['type'] === 'Duo' ? 1 : 3; 
                    for ($i = 1; $i <= $count; $i++): 
                    ?>
                        <div style="border:1px solid var(--border); padding:10px; margin-top:10px; border-radius:4px;">
                            <h5>Player <?= $i + 1 ?></h5>
                            <input type="text" name="members[<?= $i ?>][ign]" placeholder="IGN" class="form-control" style="margin-bottom:5px;" required>
                            <input type="text" name="members[<?= $i ?>][id]" placeholder="ID / UID" class="form-control" required>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <?php if ($scrim['mode'] === 'Paid'): ?>
                <div style="background:#0a0b0e; border:1px solid var(--accent); padding:15px; margin:20px 0; border-radius:4px; text-align:center;">
                    <h4>Payment Details</h4>
                    <p style="margin:5px 0;">Scan & Pay ₹<?= $scrim['entry_fee'] ?></p>
                    <img src="assets/img/upi_qr.png" alt="UPI QR" style="width:150px; height:150px; margin:10px 0;">
                    
                    <div class="form-group" style="text-align:left;">
                        <label>Transaction ID / UTR</label>
                        <input type="text" name="txn_id" class="form-control" required>
                    </div>
                    <div class="form-group" style="text-align:left;">
                        <label>Payment Screenshot</label>
                        <input type="file" name="payment_ss" class="form-control" accept="image/*" required>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn" style="width:100%; margin-top:15px;">Complete Registration</button>
        </form>
    </div>
</div>
</body>
</html>
