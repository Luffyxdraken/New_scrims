<?php
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_payment') {
        $reg_id = (int)$_POST['reg_id'];
        $status = $_POST['status']; // Approved or Rejected
        $reason = sanitize($_POST['reason'] ?? '');

        if ($status === 'Approved') {
            // Assign Slot
            $scrim_stmt = $db->prepare("SELECT scrim_id FROM registrations WHERE id = ?");
            $scrim_stmt->execute([$reg_id]);
            $s_id = $scrim_stmt->fetchColumn();

            $slots_stmt = $db->prepare("SELECT slot_number FROM registrations WHERE scrim_id = ? AND slot_number IS NOT NULL");
            $slots_stmt->execute([$s_id]);
            $taken = $slots_stmt->fetchAll(PDO::FETCH_COLUMN);

            $assigned_slot = 1;
            while(in_array($assigned_slot, $taken)) { $assigned_slot++; }

            $upd = $db->prepare("UPDATE registrations SET payment_status = 'Approved', slot_number = ? WHERE id = ?");
            $upd->execute([$assigned_slot, $reg_id]);
        } else {
            $upd = $db->prepare("UPDATE registrations SET payment_status = 'Rejected', rejection_reason = ? WHERE id = ?");
            $upd->execute([$reason, $reg_id]);
        }
    }
}

// Fetch Pending Payments
$pendings = $db->query("
    SELECT r.*, p.player_name, p.ign, s.title as scrim_title, s.entry_fee
    FROM registrations r
    JOIN players p ON r.player_id = p.id
    JOIN scrims s ON r.scrim_id = s.id
    WHERE r.payment_status = 'Pending'
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - pirtaes.co</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container nav-container">
        <a href="index.php" class="logo">ADMIN <span>PIRTAES</span></a>
        <div>
            <a href="scrims.php" class="btn btn-secondary">Manage Scrims</a>
            <a href="slots.php" class="btn btn-secondary">Slot Grid</a>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container" style="margin-top: 30px;">
    <h2>Pending Payment Verification</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Scrim</th>
                    <th>Player/IGN</th>
                    <th>Txn ID</th>
                    <th>Proof</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pendings)): ?>
                    <tr><td colspan="5">No pending payment verifications.</td></tr>
                <?php endif; ?>
                <?php foreach($pendings as $p): ?>
                    <tr>
                        <td><?= sanitize($p['scrim_title']) ?></td>
                        <td><?= sanitize($p['player_name']) ?> (<?= sanitize($p['ign']) ?>)</td>
                        <td><code><?= sanitize($p['txn_id']) ?></code></td>
                        <td>
                            <a href="../<?= sanitize($p['payment_ss']) ?>" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:0.75rem;">View SS</a>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:5px;">
                                <input type="hidden" name="action" value="verify_payment">
                                <input type="hidden" name="reg_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="status" value="Approved" class="btn" style="background:var(--success); color:#000; padding:4px 8px;">Approve</button>
                                <input type="text" name="reason" placeholder="Reason if rejecting" class="form-control" style="width:120px; padding:2px 5px;">
                                <button type="submit" name="status" value="Rejected" class="btn" style="padding:4px 8px;">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
