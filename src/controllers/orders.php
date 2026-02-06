<?php

require_once __DIR__ . '/../repository/OrdersRepository.php';
require_once __DIR__ . '/../services/AuthService.php';

class orders
{
    private OrdersRepository $ordersRepository;
    private AuthService $authService;

    public function __construct()
    {
        $this->ordersRepository = new OrdersRepository();
        $this->authService = new AuthService();
    }

    /**
     * Obsługuje żądania do /orders/*
     */
    public function handle_request(string $action, array $request, array $params): ?string
    {
        // Ustaw nagłówki do debugowania
        header('Content-Type: application/json');
        
        // Sprawdź czy użytkownik jest zalogowany
        if (!$this->authService->isLoggedIn()) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'index') {
                // Dla GET - jeśli nie zalogowany, pokaż login page
                header('Location: /login');
                exit;
            }
            header('HTTP/1.1 401 Unauthorized');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        // POST /orders/create - Stwórz nowe zamówienie
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
            return $this->handleCreateOrder($request);
        }

        // GET /orders/restaurant-dashboard - Panel restauracji (aktywne zamówienia)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'restaurant-dashboard') {
            return $this->handleRestaurantDashboard();
        }

        // GET /orders/restaurant-history - Historia zamówień restauracji
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'restaurant-history') {
            return $this->handleRestaurantHistory();
        }

        // POST /orders/update-status - Aktualizuj status zamówienia
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update-status') {
            return $this->handleUpdateOrderStatus($request);
        }

        // Jeśli GET /orders - sprawdź czy to AJAX czy HTML request
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'index') {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            // Jeśli request ma Accept: application/json - zwróć JSON
            if (strpos($accept, 'application/json') !== false) {
                return $this->handleGetOrders();
            }
            // Wpp - pokaż HTML widok
            header('Content-Type: text/html');
            render_view('orders.html');
            return null;
        }

        if ($action === 'get' && !empty($params)) {
            return $this->handleGetOrderDetail($params);
        }

        $result = match ($action) {
            'stats' => $this->handleGetStats(),
            default => json_encode(['success' => false, 'message' => 'Nieznana akcja: ' . $action, 'method' => $_SERVER['REQUEST_METHOD']]),
        };
        
        return $result;
    }

    /**
     * POST /orders/create - Stwórz nowe zamówienie
     */
    private function handleCreateOrder(array $request): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        // Pobierz dane z request
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['items']) || !isset($input['address'])) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Brakuje wymaganych danych']);
        }

        $items = $input['items'];
        $address = $input['address'];
        $notes = $input['notes'] ?? '';

        // Pobierz ID restauracji z pierwszego przedmiotu (wszystkie powinny być z tej samej restauracji)
        if (empty($items)) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Koszyk jest pusty']);
        }

        $restaurantId = $items[0]['restaurant_id'] ?? null;
        $addressId = $address['id'] ?? null;

        if (!$restaurantId || !$addressId) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Brakuje ID restauracji lub adresu']);
        }

        // Stwórz zamówienie
        $orderId = $this->ordersRepository->createOrder(
            $user['id'],
            $restaurantId,
            $addressId,
            $items
        );

        if (!$orderId) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Nie udało się stworzyć zamówienia']);
        }

        // Wyczyść koszyk użytkownika po złożeniu zamówienia
        require_once __DIR__ . '/../repository/CartRepository.php';
        $cartRepository = new CartRepository();
        $cartRepository->clearCart($user['id']);

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'message' => 'Zamówienie zostało złożone',
            'order_id' => $orderId
        ]);
    }

    /**
     * GET /orders (AJAX) - Pobierz zamówienia użytkownika
     */
    private function handleGetOrders(): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return json_encode(['success' => false, 'message' => 'Nie znaleziono użytkownika']);
        }

        $page = $_GET['page'] ?? 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Pobierz wszystkie zamówienia użytkownika (nie tylko zaplacone)
        $orders = $this->ordersRepository->getUserOrders($user['id'], $limit, $offset);
        $totalCount = $this->ordersRepository->getUserOrdersCount($user['id']);

        // Formatuj statusy
        $statusMap = [
            'pending' => 'Oczekiwanie',
            'accepted' => 'Zaakceptowane',
            'preparing' => 'Przygotowanie',
            'ready_for_pickup' => 'Gotowe do odebrania',
            'picked_up' => 'Wydane kurierowi',
            'delivered' => 'Dostarczone',
            'cancelled' => 'Anulowane'
        ];

        $paymentStatusMap = [
            'pending' => 'Oczekiwanie na płatność',
            'paid' => 'Zapłacone',
            'failed' => 'Nie powiodło się',
            null => 'Brak płatności'
        ];

        $paymentMethodMap = [
            'card' => 'Karta',
            'blik' => 'BLIK',
            'cash' => 'Gotówka',
            null => '-'
        ];

        foreach ($orders as &$order) {
            $order['status_label'] = $statusMap[$order['status']] ?? $order['status'];
            $order['payment_status_label'] = $paymentStatusMap[$order['payment_status']] ?? $paymentStatusMap[null];
            $order['payment_method_label'] = $paymentMethodMap[$order['payment_method']] ?? $paymentMethodMap[null];
            $order['total_amount'] = floatval($order['total_amount']);
            $order['amount'] = floatval($order['amount']);
            $order['created_at_formatted'] = $this->formatDate($order['created_at']);
        }

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'orders' => $orders,
            'pagination' => [
                'page' => (int)$page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    }

    /**
     * GET /orders/get/{id} (AJAX) - Pobierz szczegóły zamówienia
     */
    private function handleGetOrderDetail(array $params): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user || !isset($params[0])) {
            return json_encode(['success' => false, 'message' => 'Nieprawidłowe parametry']);
        }

        $order = $this->ordersRepository->getOrderById((int)$params[0], $user['id']);

        if (!$order) {
            return json_encode(['success' => false, 'message' => 'Zamówienie nie znalezione']);
        }

        // Formatuj statusy
        $statusMap = [
            'pending' => 'Oczekiwanie',
            'accepted' => 'Zaakceptowane',
            'preparing' => 'Przygotowanie',
            'ready_for_pickup' => 'Gotowe do odebrania',
            'picked_up' => 'Odebrane',
            'delivered' => 'Dostarczone',
            'cancelled' => 'Anulowane'
        ];

        $paymentStatusMap = [
            'pending' => 'Oczekiwanie na płatność',
            'paid' => 'Zapłacone',
            'failed' => 'Nie powiodło się',
            null => 'Brak płatności'
        ];

        $order['status_label'] = $statusMap[$order['status']] ?? $order['status'];
        $order['payment_status_label'] = $paymentStatusMap[$order['payment_status']] ?? $paymentStatusMap[null];
        $order['amount'] = $order['amount'] ? floatval($order['amount']) : 0;
        $order['created_at_formatted'] = $this->formatDate($order['created_at']);

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * GET /orders/stats (AJAX) - Pobierz statystyki zamówień
     */
    private function handleGetStats(): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return json_encode(['success' => false, 'message' => 'Nie znaleziono użytkownika']);
        }

        $totalOrders = $this->ordersRepository->getUserOrdersCount($user['id']);
        $totalSpent = $this->ordersRepository->getUserTotalSpent($user['id']);
        $averageOrderValue = $this->ordersRepository->getUserAverageOrderValue($user['id']);

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'stats' => [
                'total_orders' => $totalOrders,
                'total_spent' => round($totalSpent, 2),
                'average_order_value' => round($averageOrderValue, 2)
            ]
        ]);
    }

    /**
     * Formatuj datę na czytelny format
     */
    private function formatDate(string $date): string
    {
        $dateTime = new DateTime($date);
        return $dateTime->format('d.m.Y H:i');
    }

    /**
     * GET /orders/restaurant-dashboard - Panel restauracji z aktywnymi zamówieniami
     */
    private function handleRestaurantDashboard(): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        // Sprawdź czy użytkownik jest pracownikiem restauracji
        require_once __DIR__ . '/../repository/RestaurantRepository.php';
        $restaurantRepo = new RestaurantRepository();
        $restaurantId = $restaurantRepo->getRestaurantByUserId($user['id']);

        if (!$restaurantId) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Brak dostępu']);
        }

        // Pobierz aktywne zamówienia
        $activeOrders = $this->ordersRepository->getRestaurantActiveOrders($restaurantId);

        // Formatuj statusy
        $statusMap = [
            'pending' => 'Oczekiwanie',
            'accepted' => 'Zaakceptowane',
            'preparing' => 'Przygotowanie',
            'ready_for_pickup' => 'Gotowe do odebrania',
            'picked_up' => 'Wydane kurierowi',
            'delivered' => 'Dostarczone',
            'cancelled' => 'Anulowane'
        ];

        foreach ($activeOrders as &$order) {
            $order['status_label'] = $statusMap[$order['status']] ?? $order['status'];
            $order['total_amount'] = floatval($order['total_amount']);
        }

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'orders' => $activeOrders,
            'restaurant_id' => $restaurantId
        ]);
    }

    /**
     * GET /orders/restaurant-history - Historia zamówień restauracji
     */
    private function handleRestaurantHistory(): string
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        // Sprawdź czy użytkownik jest pracownikiem restauracji
        require_once __DIR__ . '/../repository/RestaurantRepository.php';
        $restaurantRepo = new RestaurantRepository();
        $restaurantId = $restaurantRepo->getRestaurantByUserId($user['id']);

        if (!$restaurantId) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Brak dostępu']);
        }

        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $historyOrders = $this->ordersRepository->getRestaurantOrdersHistory($restaurantId, $limit, $offset);
        $totalCount = $this->ordersRepository->getRestaurantOrdersHistoryCount($restaurantId);

        // Formatuj statusy
        $statusMap = [
            'pending' => 'Oczekiwanie',
            'accepted' => 'Zaakceptowane',
            'preparing' => 'Przygotowanie',
            'ready_for_pickup' => 'Gotowe do odebrania',
            'picked_up' => 'Odebrane',
            'delivered' => 'Dostarczone',
            'cancelled' => 'Anulowane'
        ];

        foreach ($historyOrders as &$order) {
            $order['status_label'] = $statusMap[$order['status']] ?? $order['status'];
            $order['total_amount'] = floatval($order['total_amount']);
            $order['created_at_formatted'] = $this->formatDate($order['created_at']);
        }

        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'orders' => $historyOrders,
            'pagination' => [
                'page' => (int)$page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    }

    /**
     * POST /orders/update-status - Aktualizuj status zamówienia
     */
    private function handleUpdateOrderStatus(array $request): string
    {
        // Wyłącz wyświetlanie błędów PHP w odpowiedzi
        error_reporting(0);

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('HTTP/1.1 405 Method Not Allowed');
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Method not allowed']);
            }

            $user = $this->authService->getCurrentUser();

            if (!$user) {
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
            }

            // Odczytaj dane JSON z body
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);

            if (!$input || !isset($input['order_id']) || !isset($input['status'])) {
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: application/json');
                return json_encode([
                    'success' => false,
                    'message' => 'Brakuje wymaganych danych',
                    'received' => $input
                ]);
            }

            $orderId = intval($input['order_id']);
            $newStatus = trim($input['status']);

            // Walidacja statusu (pracownik restauracji nie ustawia "delivered")
            $allowedStatuses = ['pending', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'cancelled'];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Nieprawidłowy status']);
            }

            // Sprawdź czy użytkownik jest pracownikiem restauracji
            require_once __DIR__ . '/../repository/RestaurantRepository.php';
            $restaurantRepo = new RestaurantRepository();
            $restaurantId = $restaurantRepo->getRestaurantByUserId($user['id']);

            if (!$restaurantId) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Brak dostępu']);
            }

            // Sprawdź czy zamówienie należy do restauracji
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $stmt = $db->connect()->prepare('
                SELECT id FROM orders WHERE id = :order_id AND restaurant_id = :restaurant_id
            ');
            $stmt->execute([
                ':order_id' => $orderId,
                ':restaurant_id' => $restaurantId
            ]);

            if (!$stmt->fetch()) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Nie masz dostępu do tego zamówienia']);
            }

            // Aktualizuj status
            if ($this->ordersRepository->updateOrderStatus($orderId, $newStatus)) {
                header('Content-Type: application/json');
                return json_encode(['success' => true, 'message' => 'Status zamówienia został zaktualizowany']);
            }

            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Nie udało się zaktualizować statusu']);
        } catch (Throwable $e) {
            @file_put_contents('/tmp/jellyfood_update_status_error.log', date('c') . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => 'Błąd serwera', 'error' => $e->getMessage()]);
        }
    }
}

// Inicjalizacja kontrolera
$ordersController = new orders();

/**
 * Funkcja wymagana przez Routing.php
 */
function handle_request(string $action, array $request, array $params): ?string
{
    global $ordersController;
    return $ordersController->handle_request($action, $request, $params);
}
