<?php

require_once 'Repository.php';

class CartRepository extends Repository
{
    /**
     * Pobranie koszyka użytkownika
     */
    public function getCartByUserId(int $userId): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT cart_data
            FROM user_cart
            WHERE user_id = :user_id
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['cart_data'])) {
            return json_decode($result['cart_data'], true);
        }
        
        return [];
    }

    /**
     * Zapisanie koszyka użytkownika
     */
    public function saveCart(int $userId, array $cartData): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO user_cart (user_id, cart_data, updated_at)
                VALUES (:user_id, :cart_data, NOW())
                ON CONFLICT (user_id) 
                DO UPDATE SET 
                    cart_data = :cart_data,
                    updated_at = NOW()
            ');
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':cart_data' => json_encode($cartData)
            ]);
        } catch (PDOException $e) {
            error_log("Error saving cart: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Usunięcie koszyka użytkownika
     */
    public function clearCart(int $userId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                DELETE FROM user_cart
                WHERE user_id = :user_id
            ');
            
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error clearing cart: " . $e->getMessage());
            return false;
        }
    }
}
