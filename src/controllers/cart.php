<?php

require_once __DIR__ . '/../repository/CartRepository.php';
require_once __DIR__ . '/../services/AuthService.php';

class cart
{
    private CartRepository $cartRepository;
    private AuthService $authService;

    public function __construct()
    {
        $this->cartRepository = new CartRepository();
        $this->authService = new AuthService();
    }

    /**
     * Obsługuje żądania do /cart/*
     */
    public function handle_request(string $action, array $request, array $params): ?string
    {
        return match ($action) {
            'get' => $this->handleGetCart(),
            'save' => $this->handleSaveCart($request),
            'sync' => $this->handleSyncCart($request),
            default => null,
        };
    }

    /**
     * GET /cart/get
     * Pobiera koszyk użytkownika z bazy danych
     */
    private function handleGetCart(): string
    {
        if (!$this->authService->isLoggedIn()) {
            return json_encode([
                'success' => false,
                'message' => 'Użytkownik nie jest zalogowany'
            ]);
        }

        $user = $this->authService->getCurrentUser();
        $cart = $this->cartRepository->getCartByUserId($user['id']);

        return json_encode([
            'success' => true,
            'data' => $cart
        ]);
    }

    /**
     * POST /cart/save
     * Zapisuje koszyk użytkownika do bazy danych
     */
    private function handleSaveCart(array $request): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Method not allowed']);
        }

        if (!$this->authService->isLoggedIn()) {
            return json_encode([
                'success' => false,
                'message' => 'Użytkownik nie jest zalogowany'
            ]);
        }

        $user = $this->authService->getCurrentUser();
        $cartData = json_decode(file_get_contents('php://input'), true);

        if (!isset($cartData['cart']) || !is_array($cartData['cart'])) {
            return json_encode([
                'success' => false,
                'message' => 'Nieprawidłowe dane koszyka'
            ]);
        }

        $result = $this->cartRepository->saveCart($user['id'], $cartData['cart']);

        return json_encode([
            'success' => $result,
            'message' => $result ? 'Koszyk zapisany' : 'Błąd zapisu koszyka'
        ]);
    }

    /**
     * POST /cart/sync
     * Synchronizuje koszyk lokalny z bazą danych przy logowaniu
     */
    private function handleSyncCart(array $request): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Method not allowed']);
        }

        if (!$this->authService->isLoggedIn()) {
            return json_encode([
                'success' => false,
                'message' => 'Użytkownik nie jest zalogowany'
            ]);
        }

        $user = $this->authService->getCurrentUser();
        $inputData = json_decode(file_get_contents('php://input'), true);
        $localCart = $inputData['localCart'] ?? [];

        // Pobierz koszyk z bazy danych
        $serverCart = $this->cartRepository->getCartByUserId($user['id']);

        // Strategia synchronizacji: jeśli koszyk lokalny nie jest pusty, łączymy go z serwerowym
        if (!empty($localCart) && !empty($serverCart)) {
            // Połącz koszyki - priorytet dla koszyka lokalnego
            $mergedCart = $this->mergeCards($serverCart, $localCart);
            $this->cartRepository->saveCart($user['id'], $mergedCart);
            $finalCart = $mergedCart;
        } elseif (!empty($localCart)) {
            // Zapisz lokalny koszyk na serwerze
            $this->cartRepository->saveCart($user['id'], $localCart);
            $finalCart = $localCart;
        } else {
            // Użyj koszyka z serwera
            $finalCart = $serverCart;
        }

        return json_encode([
            'success' => true,
            'data' => $finalCart
        ]);
    }

    /**
     * Łączy dwa koszyki
     */
    private function mergeCards(array $serverCart, array $localCart): array
    {
        $merged = $serverCart;

        foreach ($localCart as $localItem) {
            $found = false;
            foreach ($merged as &$mergedItem) {
                if ($mergedItem['id'] === $localItem['id'] && $mergedItem['name'] === $localItem['name']) {
                    // Jeśli produkt już istnieje, zwiększ ilość
                    $mergedItem['quantity'] += $localItem['quantity'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // Dodaj nowy produkt
                $merged[] = $localItem;
            }
        }

        return $merged;
    }
}

// Inicjalizacja kontrolera
$cartController = new cart();

/**
 * Funkcja wymagana przez Routing.php
 */
function handle_request(string $action, array $request, array $params): ?string
{
    global $cartController;
    return $cartController->handle_request($action, $request, $params);
}
