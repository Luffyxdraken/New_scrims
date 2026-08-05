<?php
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in'])) { exit; }
$db = getDB();

$scrim_id = filter_input(INPUT_GET, 'scrim_id', FILTER_VALIDATE_INT) ?? 1;

// Fetch all registrations for this scrim
$registrations = $db->prepare("
    SELECT r.id, r.slot_number, r.team_name, p.ign 
    FROM registrations r 
    JOIN players p ON r.player_id = p.id 
    WHERE r.scrim_id = ? AND r.payment_status = 'Approved'
");
$registrations->execute([$scrim_id]);
$regs = $registrations->fetchAll();

$slotMap = [];
foreach($regs as $r) {
    if($r['slot_number']) {
        $slotMap[$r['slot_number']] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Drag & Drop Slot Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container" style="margin-top:30px;">
    <h2>Manage Slots (Drag & Drop)</h2>
    <p style="color:var(--text-muted);">Drag a team block into a numbered slot to reassign positions.</p>

    <div class="slot-grid">
        <?php for($i = 1; $i <= 48; $i++): 
            $hasTeam = isset($slotMap[$i]);
            $regItem = $hasTeam ? $slotMap[$i] : null;
        ?>
            <div class="slot-box <?= $hasTeam ? 'occupied' : '' ?>" 
                 ondrop="drop(event, <?= $i ?>)" 
                 ondragover="allowDrop(event)">
                <span style="font-size:0.75rem; color:var(--text-muted); display:block;">SLOT <?= $i ?></span>
                <?php if($hasTeam): ?>
                    <div id="drag-<?= $regItem['id'] ?>" 
                         draggable="true" 
                         ondragstart="drag(event, <?= $regItem['id'] ?>)" 
                         style="font-weight:700; color:var(--cyan); margin-top:5px; cursor:grab;">
                        <?= sanitize($regItem['team_name'] ?: $regItem['ign']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<script>
function allowDrop(ev) { ev.preventDefault(); }

function drag(ev, regId) {
    ev.dataTransfer.setData("regId", regId);
}

function drop(ev, slotNumber) {
    ev.preventDefault();
    const regId = ev.dataTransfer.getData("regId");
    
    // AJAX update to persist state
    fetch('api_update_slot.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `reg_id=${regId}&slot_number=${slotNumber}&scrim_id=<?= $scrim_id ?>`
    }).then(r => r.json()).then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Slot assignment failed.');
        }
    });
}
</script>
</body>
</html>
