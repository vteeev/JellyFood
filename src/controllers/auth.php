<?php

require_once __DIR__ . '/../services/AuthService.php';

class auth
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Obsługuje żądania do /auth/*
     */
    public function handle_request(string $action, array $request, array $params): ?string
    {
        return match ($action) {
            'register' => $this->handleRegister($request),
            'login' => $this->handleLogin($request),
            'logout' => $this->handleLogout(),
            'check' => $this->handleCheck(),
            default => null,
        };
    }

    /**
     * POST /auth/register
     * 
     * Wymagane pola: email, password, password_confirm, full_name
    * Opcjonalnie: phone, street, apartment_number, city, postal_code, country
     */
    private function handleRegister(array $request): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Method not allowed']);
        }

        $email = sanitize($request['email'] ?? '');
        $password = $request['password'] ?? '';
        $passwordConfirm = $request['password_confirm'] ?? '';
        $fullName = sanitize($request['full_name'] ?? '');
        $phone = sanitize($request['phone'] ?? null);

        // Dane adresowe (opcjonalne)
        $address = null;
        if (isset($request['street']) && isset($request['city']) && isset($request['postal_code'])) {
            $address = [
                'street' => sanitize($request['street']),
                'apartment_number' => sanitize($request['apartment_number'] ?? null),
                'city' => sanitize($request['city']),
                'postal_code' => sanitize($request['postal_code']),
                'country' => sanitize($request['country'] ?? 'Polska'),
            ];
        }

        // Walidacja
        if (empty($email) || empty($password) || empty($fullName)) {
            return json_encode([
                'success' => false,
                'message' => 'Brak wymaganych pól: email, password, full_name',
            ]);
        }

        if ($password !== $passwordConfirm) {
            return json_encode([
                'success' => false,
                'message' => 'Hasła się nie zgadzają',
            ]);
        }

        // Rejestracja
        $result = $this->authService->register($email, $password, $fullName, $phone, $address);

        json_response($result);
    }

    /**
     * POST /auth/login
     * 
     * Wymagane pola: email, password
     */
    private function handleLogin(array $request): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Method not allowed']);
        }

        $email = sanitize($request['email'] ?? '');
        $password = $request['password'] ?? '';

        if (empty($email) || empty($password)) {
            return json_encode([
                'success' => false,
                'message' => 'Email i hasło są wymagane',
            ]);
        }

        $result = $this->authService->login($email, $password);

        json_response($result);
    }

    /**
     * GET /auth/logout
     */
    private function handleLogout(): string
    {
        $this->authService->logout();

        return json_encode([
            'success' => true,
            'message' => 'Wylogowanie pomyślne',
        ]);
    }

    /**
     * GET /auth/check
     * 
     * Sprawdza czy użytkownik jest zalogowany
     */
    private function handleCheck(): string
    {
        if ($this->authService->isLoggedIn()) {
            $user = $this->authService->getCurrentUser();
            unset($user['password_hash']);

            return json_encode([
                'authenticated' => true,
                'success' => true,
                'user' => $user,
            ]);
        }

        return json_encode([
            'authenticated' => false,
            'success' => false,
            'message' => 'Użytkownik nie jest zalogowany',
        ]);
    }
}

/**
 * Bezpieczne oczyszczanie danych
 */
function sanitize(?string $data): ?string
{
    if ($data === null) {
        return null;
    }

    return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
}

// Inicjalizacja kontrolera
$authController = new auth();

/**
 * Funkcja wymagana przez Routing.php
 */
function handle_request(string $action, array $request, array $params): ?string
{
    global $authController;
    return $authController->handle_request($action, $request, $params);
}
