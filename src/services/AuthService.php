<?php

require_once __DIR__ . '/../repository/UserRepository.php';

class AuthService
{
    private UserRepository $userRepository;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_SECONDS = 600; // 10 min
    private const BLOCK_SECONDS = 900; // 15 min

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
        * @param array|null $address Tablica z kluczami: street, apartment_number, city, postal_code, country
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public function register(string $email, string $password, string $fullName, string $phone = null, array $address = null): array
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
        $userId = $this->userRepository->register($email, $passwordHash, $fullName, $phone, $address);

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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $throttleKey = $this->getLoginThrottleKey($email);
        $blockInfo = $this->getLoginBlockInfo($throttleKey);
        if ($blockInfo['blocked']) {
            return [
                'success' => false,
                'message' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za kilka minut.',
                'user' => null,
                'blocked_until' => $blockInfo['blocked_until'],
            ];
        }

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
            $this->recordFailedLoginAttempt($throttleKey);
            return [
                'success' => false,
                'message' => 'Niepoprawny email lub hasło',
                'user' => null,
            ];
        }

        // Weryfikacja hasła
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedLoginAttempt($throttleKey);
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

        $this->clearLoginAttempts($throttleKey);

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
    
    // Tworzy unikalny klucz
    private function getLoginThrottleKey(string $email): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $normalizedEmail = strtolower(trim($email));
        return sha1($ip . '|' . $normalizedEmail);
    }

    // sprawdza czy jest blokada
    // wykonywane na poczatku login()
    private function getLoginBlockInfo(string $key): array
    {
        $now = time();
        $data = $_SESSION['login_throttle'][$key] ?? [
            'count' => 0,
            'first_attempt' => $now,
            'blocked_until' => 0,
        ];

        if (!empty($data['blocked_until']) && $now < $data['blocked_until']) {
            return [
                'blocked' => true,
                'blocked_until' => $data['blocked_until'],
            ];
        }

        if (($now - ($data['first_attempt'] ?? $now)) > self::ATTEMPT_WINDOW_SECONDS) {
            $data = [
                'count' => 0,
                'first_attempt' => $now,
                'blocked_until' => 0,
            ];
            $_SESSION['login_throttle'][$key] = $data;
        }

        return [
            'blocked' => false,
            'blocked_until' => 0,
        ];
    }
    // rejestrowanie nieudanej próby
    private function recordFailedLoginAttempt(string $key): void
    {
        $now = time();
        $data = $_SESSION['login_throttle'][$key] ?? [
            'count' => 0,
            'first_attempt' => $now,
            'blocked_until' => 0,
        ];

        if (($now - ($data['first_attempt'] ?? $now)) > self::ATTEMPT_WINDOW_SECONDS) {
            $data = [
                'count' => 0,
                'first_attempt' => $now,
                'blocked_until' => 0,
            ];
        }

        $data['count'] = ($data['count'] ?? 0) + 1;

        if ($data['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $data['blocked_until'] = $now + self::BLOCK_SECONDS;
        }

        $_SESSION['login_throttle'][$key] = $data;
    }
    // czyszczenie po poprawnym logowaniu
    private function clearLoginAttempts(string $key): void
    {
        if (isset($_SESSION['login_throttle'][$key])) {
            unset($_SESSION['login_throttle'][$key]);
        }
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
