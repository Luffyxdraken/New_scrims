<?php
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in'])) { exit; }

$scrim_id = filter_input(INPUT_GET, 'scrim_id', FILTER_VALIDATE_INT);
if (!$scrim_id) { die("Scrim ID required."); }

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        r.slot_number,
        r.team_name,
        p.player_name,
        p.ign,
        p.in_game_id,
        p.whatsapp,
        r.payment_status,
        r.txn_id
    FROM registrations r
    JOIN players p ON r.player_id = p.id
    WHERE r.scrim_id = ?
    ORDER BY r.slot_number ASC
");
$stmt->execute([$scrim_id]);
$data = $stmt->fetchAll();

// Set HTTP Headers to trigger raw download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=scrim_' . $scrim_id . '_players.csv');

$output = fopen('php://output', 'w');

// Header Row
fputcsv($output, ['Slot #', 'Team Name', 'Player Name', 'IGN', 'In-Game ID / UID', 'WhatsApp', 'Payment Status', 'Txn ID']);

// Data Rows
foreach ($data as $row) {
    fputcsv($output, [
        $row['slot_number'] ?? 'N/A',
        $row['team_name'] ?? 'N/A',
        $row['player_name'],
        $row['ign'],
        $row['in_game_id'],
        $row['whatsapp'],
        $row['payment_status'],
        $row['txn_id'] ?? 'N/A'
    ]);
}

fclose($output);
exit;
