<?php

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        // Remove surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        // Do not override existing environment variables
        if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}

load_env_file(__DIR__ . '/.env');

class Database
{
    private ?PDO $connection = null;

    public function __construct()
    {
        // Zmiennych środowiskowych używamy dopiero przy połączeniu
    }

    /**
     * Tworzy i zwraca połączenie PDO
     */
    public function connect(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $host = $_ENV['DB_HOST'] ?? 'db';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $db   = $_ENV['DB_DATABASE'] ?? 'db';
        $user = $_ENV['DB_USERNAME'] ?? 'docker';
        $pass = $_ENV['DB_PASSWORD'] ?? 'docker';
        $ssl  = $_ENV['DB_SSLMODE'] ?? 'prefer';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

        try {
            $this->connection = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            return $this->connection;
        } catch (PDOException $e) {
            // Loguj błąd
            error_log("Database connection error: " . $e->getMessage());
            // Nie wypisuj nic - rzuć wyjątek który zostanie obsłużony w kontrolerze
            throw new Exception("Błąd połączenia z bazą danych");
        }
    }

    /**
     * Zamyka połączenie z bazą
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }
}
