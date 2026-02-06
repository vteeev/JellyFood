<?php

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../services/AuthService.php';

class profile
{
    private UserRepository $userRepository;
    private AuthService $authService;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->authService = new AuthService();
    }

    /**
     * Obsługuje żądania do /profile/*
     */
    public function handle_request(string $action, array $request, array $params): ?string
    {
        // Sprawdź czy użytkownik jest zalogowany
        if (!$this->authService->isLoggedIn()) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'index') {
                // Dla GET - jeśli nie zalogowany, pokaż login page
                header('Location: /login');
                exit;
            }
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        // Jeśli GET /profile - sprawdź czy to AJAX czy HTML request
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'index') {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            // Jeśli request ma Accept: application/json - zwróć JSON
            if (strpos($accept, 'application/json') !== false) {
                return $this->handleGetProfile();
            }
            // Wpp - pokaż HTML widok
            render_view('profile.html');
            return null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'index') {
            return $this->handlePostProfile();
        }

        return match ($action) {
            'address' => $this->handleAddressRequest($_SERVER['REQUEST_METHOD']),
            default => null,
        };
    }

    /**
     * GET /profile (AJAX) - Pobranie profilu użytkownika
     */
    private function handleGetProfile(): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return json_encode(['success' => false, 'message' => 'Nie znaleziono użytkownika']);
        }

        $userData = $this->userRepository->getUserById($user['id']);
        $address = $this->userRepository->getUserAddress($user['id']);

        return json_encode([
            'success' => true,
            'user' => [
                'id' => $userData['id'],
                'full_name' => $userData['full_name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'created_at' => $userData['created_at'],
            ],
            'address' => $address
        ]);
    }

    /**
     * POST /profile (AJAX) - Aktualizacja profilu
     */
    private function handlePostProfile(): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Metoda niedozwolona']);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return json_encode(['success' => false, 'message' => 'Nie znaleziono użytkownika']);
        }

        $fullName = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (empty($fullName)) {
            return json_encode(['success' => false, 'message' => 'Imię i nazwisko jest wymagane']);
        }

        // Aktualizuj profil
        $updatedUser = $this->userRepository->updateUser(
            $user['id'],
            $fullName,
            !empty($phone) ? $phone : null
        );

        if ($updatedUser) {
            
            // Aktualizuj sesję
            $_SESSION['user_id'] = $updatedUser['id'];
            $_SESSION['user'] = [
                'id' => $updatedUser['id'],
                'full_name' => $updatedUser['full_name'],
                'email' => $updatedUser['email']
            ];

            header('Content-Type: application/json');
            return json_encode([
                'success' => true,
                'message' => 'Profil zaktualizowany',
                'user' => [
                    'id' => $updatedUser['id'],
                    'full_name' => $updatedUser['full_name'],
                    'email' => $updatedUser['email'],
                    'phone' => $updatedUser['phone']
                ]
            ]);
        }

        return json_encode(['success' => false, 'message' => 'Błąd aktualizacji profilu']);
    }

    /**
     * POST /profile/address (AJAX) - Aktualizacja adresu
     */
    private function handleAddressRequest(string $method): string
    {
        if ($method === 'GET') {
            $user = $this->authService->getCurrentUser();
            if (!$user) {
                return json_encode(['success' => false, 'message' => 'Brak dostępu']);
            }

            $address = $this->userRepository->getUserAddress($user['id']);
            return json_encode([
                'success' => true,
                'address' => $address
            ]);
        }

        if ($method !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Metoda niedozwolona']);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return json_encode(['success' => false, 'message' => 'Nie znaleziono użytkownika']);
        }

        $street = trim($input['street'] ?? '');
        $city = trim($input['city'] ?? '');
        $postalCode = trim($input['postal_code'] ?? '');
        $apartmentNumber = trim($input['apartment_number'] ?? '');
        $country = trim($input['country'] ?? 'Polska');

        if (empty($street) || empty($city) || empty($postalCode)) {
            return json_encode(['success' => false, 'message' => 'Uzupełnij wymagane pola']);
        }

        $result = $this->userRepository->saveUserAddress(
            $user['id'],
            $street,
            $city,
            $postalCode,
            !empty($apartmentNumber) ? $apartmentNumber : null,
            $country
        );

        if ($result) {
            return json_encode([
                'success' => true,
                'message' => 'Adres zaktualizowany'
            ]);
        }

        header('Content-Type: application/json');
        return json_encode(['success' => false, 'message' => 'Błąd aktualizacji adresu']);
    }
}

// Inicjalizacja kontrolera
$profileController = new profile();

/**
 * Funkcja wymagana przez Routing.php
 */
function handle_request(string $action, array $request, array $params): ?string
{
    global $profileController;
    return $profileController->handle_request($action, $request, $params);
}
