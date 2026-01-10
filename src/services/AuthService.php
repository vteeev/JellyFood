<?php

require_once __DIR__ . '/../repository/UserRepository.php';

class AuthService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Rejestracja nowego użytkownika
     * 
     * @param string $email
     * @param string $password
     * @param string $fullName
     * @param string|null $phone
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public function register(string $email, string $password, string $fullName, string $phone = null): array
    {
        // Walidacja
        $validation = $this->validateRegistrationInput($email, $password, $fullName);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['error'],
                'user_id' => null,
            ];
        }

        // Sprawdzenie czy email już istnieje
        if ($this->userRepository->emailExists($email)) {
            return [
                'success' => false,
                'message' => 'Email już istnieje w systemie',
                'user_id' => null,
            ];
        }

        // Hashowanie hasła
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Rejestracja użytkownika
        $userId = $this->userRepository->register($email, $passwordHash, $fullName, $phone);

        if ($userId) {
            return [
                'success' => true,
                'message' => 'Rejestracja pomyślna',
                'user_id' => $userId,
            ];
        }

        return [
            'success' => false,
            'message' => 'Błąd podczas rejestracji',
            'user_id' => null,
        ];
    }

    /**
     * Logowanie użytkownika
     * 
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login(string $email, string $password): array
    {
        // Walidacja podstawowa
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email i hasło są wymagane',
                'user' => null,
            ];
        }

        // Pobranie użytkownika
        $user = $this->userRepository->getUserByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Niepoprawny email lub hasło',
                'user' => null,
            ];
        }

        // Weryfikacja hasła
        if (!password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Niepoprawny email lub hasło',
                'user' => null,
            ];
        }

        // Aktualizacja ostatniego logowania
        $this->userRepository->updateLastLogin($user['id']);

        // Usunięcie hasła z odpowiedzi
        unset($user['password_hash']);

        // Uruchomienie sesji
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['user_name'] = $user['full_name'];

        return [
            'success' => true,
            'message' => 'Logowanie pomyślne',
            'user' => $user,
        ];
    }

    /**
     * Wylogowanie użytkownika
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();
    }

    /**
     * Sprawdzenie czy użytkownik jest zalogowany
     */
    public function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_id']);
    }

    /**
     * Pobranie aktualnego zalogowanego użytkownika
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->userRepository->getUserById($_SESSION['user_id']);
    }

    /**
     * Walidacja danych rejestracji
     */
    private function validateRegistrationInput(string $email, string $password, string $fullName): array
    {
        // Walidacja emailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Niepoprawny format emailu'];
        }

        // Walidacja hasła (minimum 8 znaków)
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Hasło musi mieć co najmniej 8 znaków'];
        }

        // Walidacja nazwy
        if (strlen($fullName) < 2 || strlen($fullName) > 255) {
            return ['valid' => false, 'error' => 'Imię i nazwisko musi mieć 2-255 znaków'];
        }

        return ['valid' => true];
    }
}
