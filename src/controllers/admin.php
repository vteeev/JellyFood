<?php

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../repository/RestaurantRepository.php';

class admin
{
    private AuthService $authService;
    private RestaurantRepository $restaurantRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->restaurantRepository = new RestaurantRepository();
    }

    public function handle_request(string $action, array $request, array $params): ?string
    {
        // Sprawdź czy użytkownik jest adminem
        if (!$this->authService->isLoggedIn()) {
            header('HTTP/1.1 401 Unauthorized');
            return json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }

        $user = $this->authService->getCurrentUser();
        if (!$user || $user['role_id'] != 1) {
            header('HTTP/1.1 403 Forbidden');
            return json_encode(['success' => false, 'message' => 'Brak dostępu']);
        }

        // POST /admin/restaurant/add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restaurant' && !empty($params[0]) && $params[0] === 'add') {
            return $this->handleAddRestaurant();
        }

        // POST /admin/restaurant/{id}/menu/add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restaurant' && !empty($params[0]) && isset($params[1]) && $params[1] === 'menu' && isset($params[2]) && $params[2] === 'add') {
            return $this->handleAddMenuItemToRestaurant((int)$params[0]);
        }

        // POST /admin/restaurant/{id}/category/add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restaurant' && !empty($params[0]) && isset($params[1]) && $params[1] === 'category' && isset($params[2]) && $params[2] === 'add') {
            return $this->handleAddCategory((int)$params[0]);
        }

        return match ($action) {
            'restaurants' => $this->handleGetRestaurants(),
            'restaurant' => $this->handleRestaurant($params),
            'menu-item' => $this->handleMenuItem($params),
            'cities' => $this->handleGetCities(),
            'kitchen-types' => $this->handleGetKitchenTypes(),
            'upload-image' => $this->handleImageUpload(),
            'staff' => $this->handleStaff($params),
            default => json_encode(['success' => false, 'message' => 'Nieznana akcja']),
        };
    }

    private function handleGetRestaurants(): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            $stmt = $db->connect()->prepare('
                SELECT id, name, description, image_url, phone, street, building_number, apartment_number, city, postal_code
                FROM restaurants
                ORDER BY name ASC
            ');
            $stmt->execute();
            $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            return json_encode([
                'success' => true,
                'restaurants' => $restaurants
            ]);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleRestaurant(array $params): string
    {
        if (empty($params[0])) {
            return json_encode(['success' => false, 'message' => 'Brakuje ID restauracji']);
        }

        $restaurantId = (int)$params[0];
        $action = $params[1] ?? 'get';

        return match ($_SERVER['REQUEST_METHOD']) {
            'GET' => $this->handleGetRestaurantDetail($restaurantId, $action),
            'POST' => $this->handleEditRestaurant($restaurantId),
            'DELETE' => $this->handleDeleteRestaurant($restaurantId),
            default => json_encode(['success' => false, 'message' => 'Method not allowed']),
        };
    }

    private function handleGetRestaurantDetail(int $restaurantId, string $action): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            $stmt = $conn->prepare('
                SELECT id, name, description, image_url, phone, street, building_number, apartment_number, city, postal_code
                FROM restaurants
                WHERE id = :id
                LIMIT 1
            ');
            $stmt->execute([':id' => $restaurantId]);
            $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$restaurant) {
                header('HTTP/1.1 404 Not Found');
                return json_encode(['success' => false, 'message' => 'Restauracja nie znaleziona']);
            }

            // Pobierz przypisane kitchen types
            $stmtKitchen = $conn->prepare('
                SELECT kitchen_type_id FROM restaurant_kitchen_types
                WHERE restaurant_id = :restaurant_id
            ');
            $stmtKitchen->execute([':restaurant_id' => $restaurantId]);
            $restaurant['kitchen_types'] = $stmtKitchen->fetchAll(PDO::FETCH_COLUMN);

            if ($action === 'menu') {
                return $this->handleGetMenu($restaurantId, $restaurant);
            }

            header('Content-Type: application/json');
            return json_encode([
                'success' => true,
                'restaurant' => $restaurant
            ]);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleGetMenu(int $restaurantId, array $restaurant): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            $stmt = $db->connect()->prepare('
                SELECT 
                    mc.id,
                    mc.name,
                    mi.id as item_id,
                    mi.name as item_name,
                    mi.description,
                    mi.price,
                    mi.is_active
                FROM menu_categories mc
                LEFT JOIN menu_items mi ON mc.id = mi.category_id
                WHERE mc.restaurant_id = :restaurant_id
                ORDER BY mc.name, mi.name
            ');
            $stmt->execute([':restaurant_id' => $restaurantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pogrupuj według kategorii
            $categories = [];
            foreach ($rows as $row) {
                $catId = $row['id'];
                if (!isset($categories[$catId])) {
                    $categories[$catId] = [
                        'id' => $catId,
                        'name' => $row['name'],
                        'items' => []
                    ];
                }
                if ($row['item_id']) {
                    $categories[$catId]['items'][] = [
                        'id' => $row['item_id'],
                        'name' => $row['item_name'],
                        'description' => $row['description'],
                        'price' => (float)$row['price'],
                        'is_active' => (bool)$row['is_active']
                    ];
                }
            }

            header('Content-Type: application/json');
            return json_encode([
                'success' => true,
                'restaurant' => $restaurant,
                'categories' => array_values($categories)
            ]);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleEditRestaurant(int $restaurantId): string
    {
        ob_start(); // Rozpocznij buforowanie wyjścia
        
        try {
            $rawInput = file_get_contents('php://input');
            
            $data = json_decode($rawInput, true);

            if (!$data) {
                ob_end_clean(); // Wyczyść bufor
                header('Content-Type: application/json; charset=utf-8');
                return json_encode(['success' => false, 'message' => 'Brakuje danych lub nieprawidłowy format JSON']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            if (!$conn) {
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                return json_encode(['success' => false, 'message' => 'Błąd połączenia z bazą danych']);
            }

            $stmt = $conn->prepare('
                UPDATE restaurants
                SET name = :name,
                    description = :description,
                    phone = :phone,
                    street = :street,
                    building_number = :building_number,
                    apartment_number = :apartment_number,
                    city = :city,
                    postal_code = :postal_code,
                    image_url = :image_url
                WHERE id = :id
            ');

            $result = $stmt->execute([
                ':id' => $restaurantId,
                ':name' => $data['name'] ?? null,
                ':description' => $data['description'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':street' => $data['street'] ?? null,
                ':building_number' => $data['building_number'] ?? null,
                ':apartment_number' => $data['apartment_number'] ?? null,
                ':city' => $data['city'] ?? null,
                ':postal_code' => $data['postal_code'] ?? null,
                ':image_url' => $data['image_url'] ?? null,
            ]);

            if (!$result) {
                ob_end_clean();
                header('Content-Type: application/json; charset=utf-8');
                return json_encode(['success' => false, 'message' => 'Nie udało się zaktualizować restauracji']);
            }

            // Zaktualizuj kitchen types
            if (isset($data['kitchen_types']) && is_array($data['kitchen_types'])) {
                // Usuń stare przypisania
                $stmtDelete = $conn->prepare('DELETE FROM restaurant_kitchen_types WHERE restaurant_id = :restaurant_id');
                $stmtDelete->execute([':restaurant_id' => $restaurantId]);

                // Dodaj nowe przypisania
                $stmtInsert = $conn->prepare('
                    INSERT INTO restaurant_kitchen_types (restaurant_id, kitchen_type_id)
                    VALUES (:restaurant_id, :kitchen_type_id)
                    ON CONFLICT DO NOTHING
                ');
                
                foreach ($data['kitchen_types'] as $kitchenTypeId) {
                    $stmtInsert->execute([
                        ':restaurant_id' => $restaurantId,
                        ':kitchen_type_id' => (int)$kitchenTypeId
                    ]);
                }
            }

            ob_end_clean(); // Wyczyść bufor przed wysłaniem odpowiedzi
            header('Content-Type: application/json; charset=utf-8');
            return json_encode(['success' => true, 'message' => 'Restauracja zaktualizowana']);
        } catch (Exception $e) {
            ob_end_clean(); // Wyczyść bufor w przypadku błędu
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json; charset=utf-8');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleDeleteRestaurant(int $restaurantId): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            // Usuń restaurację (CASCADE usunie powiązane dane)
            $stmt = $db->connect()->prepare('DELETE FROM restaurants WHERE id = :id');
            $stmt->execute([':id' => $restaurantId]);

            header('Content-Type: application/json');
            return json_encode(['success' => true, 'message' => 'Restauracja usunięta']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleMenuItem(array $params): string
    {
        if (empty($params[0])) {
            return json_encode(['success' => false, 'message' => 'Brakuje ID dania']);
        }

        $menuItemId = (int)$params[0];

        return match ($_SERVER['REQUEST_METHOD']) {
            'DELETE' => $this->handleDeleteMenuItem($menuItemId),
            default => json_encode(['success' => false, 'message' => 'Method not allowed']),
        };
    }

    private function handleDeleteMenuItem(int $menuItemId): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            $stmt = $db->connect()->prepare('DELETE FROM menu_items WHERE id = :id');
            $stmt->execute([':id' => $menuItemId]);

            header('Content-Type: application/json');
            return json_encode(['success' => true, 'message' => 'Danie usunięte']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleAddRestaurant(): string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['name']) || empty($data['street']) || empty($data['building_number']) || empty($data['city']) || empty($data['postal_code'])) {
                return json_encode(['success' => false, 'message' => 'Brakuje wymaganych pól']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            $stmt = $conn->prepare('
                INSERT INTO restaurants (name, description, phone, street, building_number, apartment_number, city, postal_code, image_url, is_active)
                VALUES (:name, :description, :phone, :street, :building_number, :apartment_number, :city, :postal_code, :image_url, true)
                RETURNING id
            ');

            $result = $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':street' => $data['street'],
                ':building_number' => $data['building_number'],
                ':apartment_number' => $data['apartment_number'] ?? null,
                ':city' => $data['city'],
                ':postal_code' => $data['postal_code'],
                ':image_url' => $data['image_url'] ?? null,
            ]);

            if ($result) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $restaurantId = $row['id'];

                // Dodaj kitchen types jeśli podano
                if (!empty($data['kitchen_types']) && is_array($data['kitchen_types'])) {
                    $stmtKitchen = $conn->prepare('
                        INSERT INTO restaurant_kitchen_types (restaurant_id, kitchen_type_id)
                        VALUES (:restaurant_id, :kitchen_type_id)
                        ON CONFLICT DO NOTHING
                    ');
                    
                    foreach ($data['kitchen_types'] as $kitchenTypeId) {
                        $stmtKitchen->execute([
                            ':restaurant_id' => $restaurantId,
                            ':kitchen_type_id' => (int)$kitchenTypeId
                        ]);
                    }
                }

                header('Content-Type: application/json');
                return json_encode(['success' => true, 'message' => 'Restauracja dodana', 'restaurant_id' => $restaurantId]);
            }

            return json_encode(['success' => false, 'message' => 'Nie udało się dodać restauracji']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleAddMenuItemToRestaurant(int $restaurantId): string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['category_id']) || empty($data['name']) || !isset($data['price'])) {
                return json_encode(['success' => false, 'message' => 'Brakuje wymaganych pól']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            $stmt = $db->connect()->prepare('
                INSERT INTO menu_items (category_id, name, description, price, image, is_active)
                VALUES (:category_id, :name, :description, :price, :image, true)
                RETURNING id
            ');

            $result = $stmt->execute([
                ':category_id' => $data['category_id'],
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':price' => $data['price'],
                ':image' => $data['image'] ?? null,
            ]);

            if ($result) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                header('Content-Type: application/json');
                return json_encode(['success' => true, 'message' => 'Danie dodane', 'menu_item_id' => $row['id']]);
            }

            return json_encode(['success' => false, 'message' => 'Nie udało się dodać dania']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleAddCategory(int $restaurantId): string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['name'])) {
                return json_encode(['success' => false, 'message' => 'Brakuje nazwy kategorii']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();

            $stmt = $db->connect()->prepare('
                INSERT INTO menu_categories (restaurant_id, name)
                VALUES (:restaurant_id, :name)
                RETURNING id
            ');

            $result = $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':name' => trim($data['name']),
            ]);

            if ($result) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                header('Content-Type: application/json');
                return json_encode(['success' => true, 'message' => 'Kategoria utworzona', 'category_id' => $row['id']]);
            }

            return json_encode(['success' => false, 'message' => 'Nie udało się utworzyć kategorii']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleGetKitchenTypes(): string
    {
        $db = new Database();
        $conn = $db->connect();

        $stmt = $conn->prepare("SELECT id, name, image_url FROM kitchen_types ORDER BY name");
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'success' => true,
            'kitchen_types' => $types
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function handleGetCities(): string
    {
        $db = new Database();
        $conn = $db->connect();

        $stmt = $conn->prepare("SELECT DISTINCT city FROM restaurants ORDER BY city");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'success' => true,
            'cities' => $cities
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function handleImageUpload(): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['success' => false, 'message' => 'Method not allowed']);
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return json_encode(['success' => false, 'message' => 'Brak pliku lub błąd uploadu']);
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return json_encode(['success' => false, 'message' => 'Niedozwolony typ pliku. Dozwolone: JPG, PNG, GIF, WebP']);
        }

        if ($file['size'] > $maxSize) {
            return json_encode(['success' => false, 'message' => 'Plik jest za duży. Maksymalny rozmiar: 5MB']);
        }

        // Ustal folder dla uploadu - restauracje lub menu_items
        $uploadDir = __DIR__ . '/../../public/uploads/restaurants/';
        $urlPrefix = '/public/uploads/restaurants/';
        
        // Jeśli zdjęcie pochodzi z menu item upload (sprawdź czy plik pochodzi z menu upload)
        if (strpos($_SERVER['HTTP_REFERER'] ?? '', 'menu') !== false || isset($_GET['type']) && $_GET['type'] === 'menu') {
            $uploadDir = __DIR__ . '/../../public/uploads/menu_items/';
            $urlPrefix = '/public/uploads/menu_items/';
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = (strpos($uploadDir, 'menu_items') !== false ? 'menu_' : 'restaurant_') . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imageUrl = $urlPrefix . $filename;
            return json_encode([
                'success' => true,
                'image_url' => $imageUrl
            ]);
        }

        return json_encode(['success' => false, 'message' => 'Nie udało się przesłać pliku']);
    }

    private function handleStaff(array $params): string
    {
        if (empty($params[0])) {
            return json_encode(['success' => false, 'message' => 'Brakuje ID restauracji']);
        }

        $restaurantId = (int)$params[0];
        $subAction = $params[1] ?? 'list';

        return match ($_SERVER['REQUEST_METHOD']) {
            'GET' => $this->handleGetStaff($restaurantId),
            'POST' => $this->handleAddStaff($restaurantId),
            'DELETE' => $this->handleDeleteStaff($restaurantId, $subAction),
            default => json_encode(['success' => false, 'message' => 'Method not allowed']),
        };
    }

    private function handleGetStaff(int $restaurantId): string
    {
        try {
            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            $stmt = $conn->prepare('
                SELECT u.id, u.email, u.full_name, u.phone, rs.id as assignment_id
                FROM restaurant_system rs
                JOIN users u ON rs.user_id = u.id
                WHERE rs.restaurant_id = :restaurant_id
                ORDER BY u.full_name ASC
            ');
            $stmt->execute([':restaurant_id' => $restaurantId]);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            return json_encode(['success' => true, 'staff' => $staff]);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleAddStaff(int $restaurantId): string
    {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!$data || empty($data['email']) || empty($data['full_name']) || empty($data['password'])) {
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Brakuje wymaganych danych']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            // Sprawdź czy użytkownik już istnieje
            $stmtCheck = $conn->prepare('SELECT id FROM users WHERE email = :email');
            $stmtCheck->execute([':email' => $data['email']]);
            if ($stmtCheck->fetch()) {
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Użytkownik z tym emailem już istnieje']);
            }

            // Utwórz użytkownika
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmtInsertUser = $conn->prepare('
                INSERT INTO users (role_id, email, password_hash, full_name, phone)
                VALUES (3, :email, :password_hash, :full_name, :phone)
            ');
            $stmtInsertUser->execute([
                ':email' => $data['email'],
                ':password_hash' => $passwordHash,
                ':full_name' => $data['full_name'],
                ':phone' => $data['phone'] ?? null
            ]);

            $userId = $conn->lastInsertId();

            // Przypisz do restauracji
            $stmtAssign = $conn->prepare('
                INSERT INTO restaurant_system (user_id, restaurant_id)
                VALUES (:user_id, :restaurant_id)
            ');
            $stmtAssign->execute([
                ':user_id' => $userId,
                ':restaurant_id' => $restaurantId
            ]);

            header('Content-Type: application/json');
            return json_encode(['success' => true, 'message' => 'Pracownik dodany', 'user_id' => $userId]);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleDeleteStaff(int $restaurantId, string $userId): string
    {
        try {
            if (!is_numeric($userId)) {
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Nieprawidłowe ID użytkownika']);
            }

            require_once __DIR__ . '/../../Database.php';
            $db = new Database();
            $conn = $db->connect();

            // Sprawdź czy pracownik należy do tej restauracji
            $stmtCheck = $conn->prepare('
                SELECT id FROM restaurant_system 
                WHERE user_id = :user_id AND restaurant_id = :restaurant_id
            ');
            $stmtCheck->execute([
                ':user_id' => (int)$userId,
                ':restaurant_id' => $restaurantId
            ]);
            
            if (!$stmtCheck->fetch()) {
                header('Content-Type: application/json');
                return json_encode(['success' => false, 'message' => 'Pracownik nie należy do tej restauracji']);
            }

            // Usuń przypisanie
            $stmtDelete = $conn->prepare('
                DELETE FROM restaurant_system 
                WHERE user_id = :user_id AND restaurant_id = :restaurant_id
            ');
            $stmtDelete->execute([
                ':user_id' => (int)$userId,
                ':restaurant_id' => $restaurantId
            ]);

            header('Content-Type: application/json');
            return json_encode(['success' => true, 'message' => 'Pracownik usunięty']);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

// Inicjalizacja kontrolera
$adminController = new admin();

function handle_request(string $action, array $request, array $params): ?string
{
    global $adminController;
    return $adminController->handle_request($action, $request, $params);
}
