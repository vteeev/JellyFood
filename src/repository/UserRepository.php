<?php

require_once 'Repository.php';

class UserRepository extends Repository
{
    /**
     * Pobranie użytkownika po emailu
     */
    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT u.*, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
            LIMIT 1
        ');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }

    /**
     * Pobranie użytkownika po ID
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT u.*, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }

    /**
     * Rejestracja nowego użytkownika (klient)
     */
    public function register(string $email, string $passwordHash, string $fullName, string $phone = null, array $address = null): ?int
    {
        try {
            $pdo = $this->database->connect();
            $pdo->beginTransaction();
            
            // Pobranie ID roli "klient"
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :role LIMIT 1');
            $roleStmt->execute([':role' => 'klient']);
            $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$role) {
                $pdo->rollBack();
                return null;
            }
            
            $stmt = $pdo->prepare('
                INSERT INTO users (role_id, email, password_hash, full_name, phone, created_at)
                VALUES (:role_id, :email, :password_hash, :full_name, :phone, NOW())
                RETURNING id
            ');
            
            $result = $stmt->execute([
                ':role_id' => $role['id'],
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':full_name' => $fullName,
                ':phone' => $phone,
            ]);
            
            if ($result) {
                $insertedId = $stmt->fetch(PDO::FETCH_ASSOC);
                $userId = (int)$insertedId['id'];
                
                // Zapisz adres jeśli został podany
                if ($address && isset($address['street']) && isset($address['city']) && isset($address['postal_code'])) {
                    $addressStmt = $pdo->prepare('
                        INSERT INTO user_addresses (user_id, street, building_number, apartment_number, city, postal_code, country)
                        VALUES (:user_id, :street, :building_number, :apartment_number, :city, :postal_code, :country)
                    ');
                    
                    // Parsuj street na street i building_number (uproszczone - można rozbudować)
                    $streetParts = $this->parseStreetAddress($address['street']);
                    
                    $addressStmt->execute([
                        ':user_id' => $userId,
                        ':street' => $streetParts['street'],
                        ':building_number' => $streetParts['building_number'],
                        ':apartment_number' => $address['apartment_number'] ?? null,
                        ':city' => $address['city'],
                        ':postal_code' => $address['postal_code'],
                        ':country' => $address['country'] ?? 'Polska',
                    ]);
                }
                
                $pdo->commit();
                return $userId;
            }
            
            return null;
        } catch (PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            return null;
        }
    }

    /**
     * Parsuje adres ulicy na ulicę i numer budynku
     */
    private function parseStreetAddress(string $fullAddress): array
    {
        $parts = explode(' ', trim($fullAddress));
        $buildingNumber = '';
        $street = '';

        // Znajdź ostatni element zawierający cyfry
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (preg_match('/\d/', $parts[$i])) {
                $buildingNumber = $parts[$i];
                $street = implode(' ', array_slice($parts, 0, $i));
                break;
            }
        }

        // Jeśli nie znaleziono numeru, traktuj wszystko jako ulicę
        if (empty($buildingNumber)) {
            $street = $fullAddress;
            $buildingNumber = '';
        }

        return [
            'street' => $street ?: $fullAddress,
            'building_number' => $buildingNumber ?: '0'
        ];
    }

    /**
     * Sprawdzenie czy email już istnieje
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 1 FROM users WHERE email = :email LIMIT 1
        ');
        $stmt->execute([':email' => $email]);
        
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Aktualizacja ostatniego logowania
     */
    public function updateLastLogin(int $userId): bool
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE users
            SET updated_at = NOW()
            WHERE id = :id
        ');
        
        return $stmt->execute([':id' => $userId]);

    }
    /**
    * Pobierz główny adres użytkownika (ostatnio dodany)
     */
    public function getUserAddress(int $userId): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, street, building_number, apartment_number, city, postal_code, country
            FROM user_addresses
            WHERE user_id = :user_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($address) {
            // Formatuj adres do wyświetlenia
            $fullStreet = $address['street'] . ' ' . $address['building_number'];
            if ($address['apartment_number']) {
                $fullStreet .= '/' . $address['apartment_number'];
            }
            $address['street'] = $fullStreet;
            return $address;
        }
        
        return null;
    }

    /**
     * Pobierz wszystkie adresy użytkownika
     */
    public function getUserAddresses(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, street, building_number, apartment_number, city, postal_code, country
            FROM user_addresses
            WHERE user_id = :user_id
            ORDER BY id DESC
        ');
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktualizuj dane użytkownika (imię, telefon)
     */
    public function updateUser(int $userId, string $fullName, ?string $phone = null): ?array
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE users
            SET full_name = :full_name, phone = :phone, updated_at = NOW()
            WHERE id = :id
            RETURNING id, full_name, email, phone, created_at
        ');

        $result = $stmt->execute([
            ':full_name' => $fullName,
            ':phone' => $phone,
            ':id' => $userId
        ]);

        if ($result) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return null;
    }

    /**
    * Zapisz nowy adres użytkownika
     */
    public function saveUserAddress(int $userId, string $street, string $city, string $postalCode, ?string $apartmentNumber = null, ?string $country = 'Polska'): bool
    {
        $streetParts = $this->parseStreetAddress($street);

        $stmt = $this->database->connect()->prepare('
            INSERT INTO user_addresses (user_id, street, building_number, apartment_number, city, postal_code, country)
            VALUES (:user_id, :street, :building_number, :apartment_number, :city, :postal_code, :country)
        ');
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':street' => $streetParts['street'],
            ':building_number' => $streetParts['building_number'],
            ':apartment_number' => $apartmentNumber,
            ':city' => $city,
            ':postal_code' => $postalCode,
            ':country' => $country ?? 'Polska'
        ]);
    }
}
