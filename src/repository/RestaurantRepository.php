<?php

require_once 'Repository.php';

class RestaurantRepository extends Repository
{
    /**
     * Pobranie wszystkich restauracji z typami kuchni
     */
    public function getAllRestaurants(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT DISTINCT
                r.id,
                r.name,
                r.description,
                r.phone,
                r.street,
                r.building_number,
                r.apartment_number,
                r.city,
                r.postal_code,
                r.created_at,
                r.is_active,
                ARRAY_AGG(kt.name) as kitchen_types
            FROM restaurants r
            LEFT JOIN restaurant_kitchen_types rkt ON r.id = rkt.restaurant_id
            LEFT JOIN kitchen_types kt ON rkt.kitchen_type_id = kt.id
            WHERE r.is_active = true
            GROUP BY r.id
            ORDER BY r.name ASC
        ');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobranie restauracji po ID z typami kuchni
     */
    public function getRestaurantById(int $id): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT DISTINCT
                r.id,
                r.name,
                r.description,
                r.phone,
                r.street,
                r.building_number,
                r.apartment_number,
                r.city,
                r.postal_code,
                r.created_at,
                r.is_active,
                ARRAY_AGG(kt.name) as kitchen_types
            FROM restaurants r
            LEFT JOIN restaurant_kitchen_types rkt ON r.id = rkt.restaurant_id
            LEFT JOIN kitchen_types kt ON rkt.kitchen_type_id = kt.id
            WHERE r.id = :id
            GROUP BY r.id
        ');
        $stmt->execute([':id' => $id]);
        
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
        return $restaurant ?: null;
    }

    /**
     * Pobranie restauracji po typie kuchni
     */
    public function getRestaurantsByKitchenType(string $kitchenType): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT DISTINCT
                r.id,
                r.name,
                r.description,
                r.phone,
                r.street,
                r.building_number,
                r.apartment_number,
                r.city,
                r.postal_code,
                r.created_at,
                r.is_active,
                ARRAY_AGG(kt.name) as kitchen_types
            FROM restaurants r
            LEFT JOIN restaurant_kitchen_types rkt ON r.id = rkt.restaurant_id
            LEFT JOIN kitchen_types kt ON rkt.kitchen_type_id = kt.id
            WHERE r.is_active = true
                AND kt.name = :kitchen_type
            GROUP BY r.id
            ORDER BY r.name ASC
        ');
        $stmt->execute([':kitchen_type' => $kitchenType]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobranie wszystkich typów kuchni
     */
    public function getAllKitchenTypes(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, name
            FROM kitchen_types
            ORDER BY name ASC
        ');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dodanie typu kuchni do restauracji
     */
    public function addKitchenTypeToRestaurant(int $restaurantId, int $kitchenTypeId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO restaurant_kitchen_types (restaurant_id, kitchen_type_id)
                VALUES (:restaurant_id, :kitchen_type_id)
                ON CONFLICT DO NOTHING
            ');
            
            return $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':kitchen_type_id' => $kitchenTypeId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Usunięcie typu kuchni z restauracji
     */
    public function removeKitchenTypeFromRestaurant(int $restaurantId, int $kitchenTypeId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                DELETE FROM restaurant_kitchen_types
                WHERE restaurant_id = :restaurant_id
                    AND kitchen_type_id = :kitchen_type_id
            ');
            
            return $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':kitchen_type_id' => $kitchenTypeId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Wyszukiwanie restauracji po nazwie
     */
    public function searchRestaurants(string $query): array
    {
        $searchTerm = '%' . $query . '%';
        
        $stmt = $this->database->connect()->prepare('
            SELECT DISTINCT
                r.id,
                r.name,
                r.description,
                r.phone,
                r.street,
                r.building_number,
                r.apartment_number,
                r.city,
                r.postal_code,
                r.created_at,
                r.is_active,
                ARRAY_AGG(kt.name) as kitchen_types
            FROM restaurants r
            LEFT JOIN restaurant_kitchen_types rkt ON r.id = rkt.restaurant_id
            LEFT JOIN kitchen_types kt ON rkt.kitchen_type_id = kt.id
            WHERE r.is_active = true
                AND (r.name ILIKE :query OR r.description ILIKE :query)
            GROUP BY r.id
            ORDER BY r.name ASC
        ');
        $stmt->execute([':query' => $searchTerm]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobranie menu restauracji z kategoriami
     */
    public function getRestaurantMenu(int $restaurantId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                mc.id as category_id,
                mc.name as category_name,
                mi.id,
                mi.name,
                mi.description,
                mi.price,
                mi.image,
                mi.is_active
            FROM menu_categories mc
            LEFT JOIN menu_items mi ON mc.id = mi.category_id
            WHERE mc.restaurant_id = :restaurant_id
                AND (mi.is_active = true OR mi.is_active IS NULL)
            ORDER BY mc.id, mi.id
        ');
        $stmt->execute([':restaurant_id' => $restaurantId]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grupowanie według kategorii
        $menu = [];
        foreach ($results as $row) {
            $categoryId = $row['category_id'];
            
            if (!isset($menu[$categoryId])) {
                $menu[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $row['category_name'],
                    'items' => []
                ];
            }
            
            if ($row['id']) {
                $menu[$categoryId]['items'][] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'image' => $row['image'],
                    'is_active' => $row['is_active']
                ];
            }
        }
        
        return array_values($menu);
    }
}
