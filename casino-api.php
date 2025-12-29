<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
define('MAX_ATTEMPTS', 3);
define('WINDOW_HOURS', 1);
define('DATA_FILE', 'casino_data.json');

// Fonction pour obtenir un identifiant unique de l'utilisateur
function getUserId() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ip . $ua);
}

// Charger les données
function loadData() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true) ?: [];
}

// Sauvegarder les données
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Nettoyer les anciennes sessions (plus de 24h)
function cleanOldSessions(&$data) {
    $now = time();
    foreach ($data as $userId => $session) {
        if ($now - $session['start'] > 86400) { // 24 heures
            unset($data[$userId]);
        }
    }
}

$action = $_GET['action'] ?? '';
$userId = getUserId();

if ($action === 'check') {
    // Vérifier le statut de l'utilisateur
    $data = loadData();
    cleanOldSessions($data);
    
    $now = time();
    $windowSeconds = WINDOW_HOURS * 3600;
    
    if (!isset($data[$userId])) {
        $data[$userId] = [
            'start' => $now,
            'attempts' => 0
        ];
        saveData($data);
    }
    
    $session = $data[$userId];
    
    // Vérifier si la fenêtre est expirée
    if ($now - $session['start'] > $windowSeconds) {
        $data[$userId] = [
            'start' => $now,
            'attempts' => 0
        ];
        saveData($data);
        $session = $data[$userId];
    }
    
    echo json_encode([
        'success' => true,
        'attempts' => $session['attempts'],
        'maxAttempts' => MAX_ATTEMPTS,
        'remaining' => MAX_ATTEMPTS - $session['attempts'],
        'canPlay' => $session['attempts'] < MAX_ATTEMPTS,
        'resetIn' => max(0, $windowSeconds - ($now - $session['start']))
    ]);
    
} elseif ($action === 'play') {
    // Enregistrer une tentative
    $data = loadData();
    cleanOldSessions($data);
    
    $now = time();
    $windowSeconds = WINDOW_HOURS * 3600;
    
    if (!isset($data[$userId])) {
        $data[$userId] = [
            'start' => $now,
            'attempts' => 0
        ];
    }
    
    $session = $data[$userId];
    
    // Vérifier si la fenêtre est expirée
    if ($now - $session['start'] > $windowSeconds) {
        $data[$userId] = [
            'start' => $now,
            'attempts' => 0
        ];
        $session = $data[$userId];
    }
    
    // Vérifier si peut jouer
    if ($session['attempts'] >= MAX_ATTEMPTS) {
        echo json_encode([
            'success' => false,
            'error' => 'Max attempts reached',
            'resetIn' => max(0, $windowSeconds - ($now - $session['start']))
        ]);
        exit;
    }
    
    // Incrémenter les tentatives
    $data[$userId]['attempts']++;
    saveData($data);
    
    echo json_encode([
        'success' => true,
        'attempts' => $data[$userId]['attempts'],
        'remaining' => MAX_ATTEMPTS - $data[$userId]['attempts']
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
}
?>