<?php
require_once '../config.php';
header('Content-Type: application/json');

$scrim_id = filter_input(INPUT_GET, 'scrim_id', FILTER_VALIDATE_INT);
if (!$scrim_id) {
    echo json_encode(['status' => false, 'message' => 'Invalid Scrim ID']);
    exit;
}

$db = getDB();

// Query leaderboard aggregated over all matches within a scrim
$query = "
    SELECT 
        r.id as registration_id,
        r.team_name,
        p.ign,
        SUM(mr.kills) as total_kills,
        SUM(mr.placement_points) as total_placement_points,
        SUM(mr.kill_points) as total_kill_points,
        SUM(mr.total_points) as grand_total
    FROM match_results mr
    JOIN matches m ON mr.match_id = m.id
    JOIN registrations r ON mr.registration_id = r.id
    JOIN players p ON r.player_id = p.id
    WHERE m.scrim_id = ?
    GROUP BY r.id
    ORDER BY grand_total DESC, total_kills DESC, total_placement_points DESC
";

$stmt = $db->prepare($query);
$stmt->execute([$scrim_id]);
$leaderboard = $stmt->fetchAll();

echo json_encode([
    'status' => true,
    'scrim_id' => $scrim_id,
    'data' => $leaderboard
]);
