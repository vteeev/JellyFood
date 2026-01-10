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
    public function register(string $email, string $passwordHash, string $fullName, string $phone = null): ?int
    {
        try {
            $pdo = $this->database->connect();
            
            // Pobranie ID roli "klient"
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :role LIMIT 1');
            $roleStmt->execute([':role' => 'klient']);
            $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$role) {
                // Jeśli rola nie istnieje, tworzysz ją (shouldn't happen, ale na wypadek)
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
                return (int)$insertedId['id'];
            }
            
            return null;
        } catch (PDOException $e) {
            return null;
        }
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
}