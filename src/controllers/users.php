<?php

require_once __DIR__ . '/../repository/UserRepository.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Pobierz adres użytkownika
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/users/(\d+)/address$#', $requestPath, $matches)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json');
    
    $requestedUserId = (int)$matches[1];
    
    // Sprawdź czy użytkownik jest zalogowany
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nie jesteś zalogowany']);
        exit();
    }
    
    // Sprawdź czy użytkownik próbuje pobrać swój własny adres
    if ($_SESSION['user_id'] !== $requestedUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
        exit();
    }
    
    $userRepository = new UserRepository();
    $address = $userRepository->getUserAddress($requestedUserId);
    
    if ($address) {
        echo json_encode([
            'success' => true,
            'address' => $address
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Brak adresu'
        ]);
    }
    exit();
}

// Zapisz/zaktualizuj adres użytkownika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $requestPath === '/users/address') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json');
    
    // Sprawdź czy użytkownik jest zalogowany
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nie jesteś zalogowany']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['street']) || !isset($data['city']) || !isset($data['postal_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Brak wymaganych danych']);
        exit();
    }
    
    $street = trim($data['street']);
    $city = trim($data['city']);
    $postalCode = trim($data['postal_code']);
    
    if (empty($street) || empty($city) || empty($postalCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Wszystkie pola są wymagane']);
        exit();
    }
    
    $userRepository = new UserRepository();
    $result = $userRepository->saveUserAddress($_SESSION['user_id'], $street, $city, $postalCode);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Adres został zapisany'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Błąd podczas zapisywania adresu'
        ]);
    }
    exit();
}
