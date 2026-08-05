<?php
require_once '../config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$query = strtolower(trim($input['query'] ?? ''));

if (empty($query)) {
    echo json_encode(['answer' => 'Please type a valid question!']);
    exit;
}

$db = getDB();
$faqs = $db->query("SELECT * FROM faqs")->fetchAll();

$best_match = "I'm sorry, I couldn't find an immediate answer. Please contact Support via WhatsApp!";
$highest_score = 0;

foreach ($faqs as $faq) {
    $keywords = explode(',', strtolower($faq['keywords']));
    $score = 0;
    
    foreach ($keywords as $keyword) {
        $trimmed = trim($keyword);
        if (!empty($trimmed) && strpos($query, $trimmed) !== false) {
            $score++;
        }
    }
    
    if ($score > $highest_score) {
        $highest_score = $score;
        $best_match = $faq['answer'];
    }
}

echo json_encode(['answer' => $best_match]);
