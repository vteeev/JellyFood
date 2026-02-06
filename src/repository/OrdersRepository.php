<?php

require_once 'Repository.php';

class OrdersRepository extends Repository
{
    /**
     * Pobierz zamówienia użytkownika
     */
    public function getUserOrders(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.status,
                o.created_at,
                o.updated_at,
                r.name as restaurant_name,
                p.amount,
                p.status as payment_status,
                p.payment_method,
                p.created_at as payment_date,
                COUNT(oi.id) as items_count,
                SUM(oi.quantity) as total_quantity,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_amount
            FROM orders o
            LEFT JOIN restaurants r ON o.restaurant_id = r.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.customer_id = :user_id
            GROUP BY o.id, r.id, p.id
            ORDER BY o.created_at DESC
            LIMIT :limit OFFSET :offset
        ');

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobierz szczegóły zamówienia
     */
    public function getOrderById(int $orderId, int $userId): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.address_id,
                o.status,
                o.created_at,
                o.updated_at,
                r.name as restaurant_name,
                r.phone as restaurant_phone,
                ua.street,
                ua.building_number,
                ua.apartment_number,
                ua.city,
                ua.postal_code,
                p.amount,
                p.status as payment_status,
                p.payment_method,
                p.created_at as payment_date,
                d.status as delivery_status,
                d.pickup_time,
                d.delivery_time
            FROM orders o
            LEFT JOIN restaurants r ON o.restaurant_id = r.id
            LEFT JOIN user_addresses ua ON o.address_id = ua.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN deliveries d ON o.id = d.order_id
            WHERE o.id = :order_id AND o.customer_id = :user_id
        ');

        $stmt->execute([
            ':order_id' => $orderId,
            ':user_id' => $userId
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Pobierz przedmioty zamówienia
        $itemsStmt = $this->database->connect()->prepare('
            SELECT 
                oi.id,
                oi.quantity,
                oi.price,
                mi.name,
                mi.description
            FROM order_items oi
            LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = :order_id
        ');

        $itemsStmt->execute([':order_id' => $orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    /**
     * Pobierz liczbę zamówień użytkownika
     */
    public function getUserOrdersCount(int $userId): int
    {
        $stmt = $this->database->connect()->prepare('
            SELECT COUNT(*) as count FROM orders WHERE customer_id = :user_id
        ');

        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Pobierz łączną kwotę wydanych pieniędzy
     */
    public function getUserTotalSpent(int $userId): float
    {
        $stmt = $this->database->connect()->prepare('
            SELECT COALESCE(SUM(p.amount), 0) as total
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE o.customer_id = :user_id AND p.status = \'paid\'
        ');

        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float)($result['total'] ?? 0);
    }

    /**
     * Pobierz średnią wartość zamówienia
     */
    public function getUserAverageOrderValue(int $userId): float
    {
        $stmt = $this->database->connect()->prepare('
            SELECT COALESCE(AVG(p.amount), 0) as avg
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE o.customer_id = :user_id AND p.status = \'paid\'
        ');

        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float)($result['avg'] ?? 0);
    }

    /**
     * Pobierz zamówienia po statusie
     */
    public function getOrdersByStatus(int $userId, string $status): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.status,
                o.created_at,
                r.name as restaurant_name,
                p.amount,
                p.status as payment_status
            FROM orders o
            LEFT JOIN restaurants r ON o.restaurant_id = r.id
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE o.customer_id = :user_id AND o.status = :status
            ORDER BY o.created_at DESC
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':status' => $status
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobierz zamówienia zaplacone (payment_status = 'paid')
     */
    public function getUserPaidOrders(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.status,
                o.created_at,
                o.updated_at,
                r.name as restaurant_name,
                p.amount,
                p.status as payment_status,
                p.payment_method,
                p.created_at as payment_date,
                COUNT(oi.id) as items_count,
                SUM(oi.quantity) as total_quantity,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_amount
            FROM orders o
            LEFT JOIN restaurants r ON o.restaurant_id = r.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.customer_id = :user_id AND p.status = \'paid\'
            GROUP BY o.id, r.id, p.id
            ORDER BY o.created_at DESC
            LIMIT :limit OFFSET :offset
        ');

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Stwórz nowe zamówienie
     */
    public function createOrder(int $customerId, int $restaurantId, int $addressId, array $items): ?int
    {
        $connection = $this->database->connect();
        
        try {
            $connection->beginTransaction();
            
            // Stwórz zamówienie
            $stmt = $connection->prepare('
                INSERT INTO orders (customer_id, restaurant_id, address_id, status)
                VALUES (:customer_id, :restaurant_id, :address_id, \'pending\')
                RETURNING id
            ');
            
            $stmt->execute([
                ':customer_id' => $customerId,
                ':restaurant_id' => $restaurantId,
                ':address_id' => $addressId
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $orderId = $result['id'] ?? null;
            
            if (!$orderId) {
                throw new Exception('Nie udało się stworzyć zamówienia');
            }
            
            // Dodaj przedmioty zamówienia
            foreach ($items as $item) {
                $itemStmt = $connection->prepare('
                    INSERT INTO order_items (order_id, menu_item_id, quantity, price)
                    VALUES (:order_id, :menu_item_id, :quantity, :price)
                ');
                
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':menu_item_id' => $item['id'] ?? null,
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);
            }
            
            $connection->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $connection->rollBack();
            error_log('Błąd tworzenia zamówienia: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pobierz aktywne zamówienia restauracji (pending, accepted, preparing, ready_for_pickup)
     */
    public function getRestaurantActiveOrders(int $restaurantId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.status,
                o.created_at,
                u.full_name as customer_name,
                u.phone as customer_phone,
                ua.street,
                ua.city,
                COUNT(oi.id) as items_count,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_amount,
                STRING_AGG(mi.name || \' (x\' || oi.quantity || \')\', \', \') as items_list
            FROM orders o
            LEFT JOIN users u ON o.customer_id = u.id
            LEFT JOIN user_addresses ua ON o.address_id = ua.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE o.restaurant_id = :restaurant_id 
                AND o.status IN (\'pending\', \'accepted\', \'preparing\', \'ready_for_pickup\')
            GROUP BY o.id, u.id, ua.id
            ORDER BY o.created_at DESC
        ');

        $stmt->execute([':restaurant_id' => $restaurantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobierz historię zamówień restauracji (picked_up, delivered, cancelled)
     */
    public function getRestaurantOrdersHistory(int $restaurantId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                o.id,
                o.customer_id,
                o.restaurant_id,
                o.status,
                o.created_at,
                u.full_name as customer_name,
                u.phone as customer_phone,
                COUNT(oi.id) as items_count,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_amount
            FROM orders o
            LEFT JOIN users u ON o.customer_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.restaurant_id = :restaurant_id 
                AND o.status IN (\'picked_up\', \'delivered\', \'cancelled\')
            GROUP BY o.id, u.id
            ORDER BY o.created_at DESC
            LIMIT :limit OFFSET :offset
        ');

        $stmt->bindValue(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobierz liczbę historycznych zamówień restauracji
     */
    public function getRestaurantOrdersHistoryCount(int $restaurantId): int
    {
        $stmt = $this->database->connect()->prepare('
            SELECT COUNT(*) as count FROM orders 
            WHERE restaurant_id = :restaurant_id 
                AND status IN (\'picked_up\', \'delivered\', \'cancelled\')
        ');

        $stmt->execute([':restaurant_id' => $restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Aktualizuj status zamówienia
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        $validStatuses = ['pending', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'delivered', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $stmt = $this->database->connect()->prepare('
            UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id
        ');

        return $stmt->execute([
            ':status' => $status,
            ':order_id' => $orderId
        ]);
    }
}
