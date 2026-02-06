# JellyFood - System Autoryzacji

## ✅ Ukończone

### Struktura bazy danych
- ✅ Tabela `roles` - Role użytkowników (admin, klient, pracownik_restauracji, dostawca)
- ✅ Tabela `users` - Użytkownicy z hashowanymi hasłami
- ✅ 13 tabel powiązanych (adresy, restauracje, zamówienia, itp.)

### Backend
- ✅ `UserRepository.php` - Repozytorium do operacji na użytkownikach
- ✅ `AuthService.php` - Serwis autoryzacji z logowaniem i rejestracją
- ✅ `auth.php` - Kontroler REST API

### Frontend
- ✅ `/register` - Strona rejestracji z walidacją
- ✅ `/login` - Strona logowania
- ✅ Responsywny design
- ✅ Pokazywanie/ukrywanie hasła
- ✅ Wyświetlanie błędów

### Bezpieczeństwo
- ✅ Hasła hashowane z bcrypt (cost=12)
- ✅ SQL Injection prevention - prepared statements
- ✅ XSS protection - HTML escaping
- ✅ Walidacja email, hasła, nazwy
- ✅ Sesje PHP

---

## 🔐 Funkcjonalności

### Rejestracja
```
POST /auth/register
```
- Email unikalny w systemie
- Hasło minimum 8 znaków
- Potwierdzenie hasła
- Automatyczne przypisanie roli "klient"

### Logowanie
```
POST /auth/login
```
- Weryfikacja hasła
- Vytowarzenie sesji
- Zwrócenie danych użytkownika

### Wylogowanie
```
GET /auth/logout
```
- Zniszczenie sesji

### Sprawdzenie statusu
```
GET /auth/check
```
- Sprawdzenie czy użytkownik zalogowany
- Zwrócenie danych zalogowanego użytkownika

---

## 🧪 Testowanie

### Rejestracja
1. Wejdź na http://localhost:8080/register
2. Wypełnij formularz
3. Hasło musi mieć minimum 8 znaków
4. Hasła muszą się zgadzać
5. Zaznacz regulamin

### Logowanie
1. Wejdź na http://localhost:8080/login
2. Wpisz email i hasło
3. Jeśli dane poprawne - zostaniesz zalogowany

### API Testing (curl/Postman)

```bash
# Rejestracja
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=test@example.com&password=TestPass123&password_confirm=TestPass123&full_name=Jan Kowalski&phone=123456789"

# Logowanie
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=test@example.com&password=TestPass123" \
  -c cookies.txt

# Sprawdzenie statusu
curl http://localhost:8080/auth/check \
  -b cookies.txt

# Wylogowanie
curl http://localhost:8080/auth/logout \
  -b cookies.txt
```

---

## 📁 Struktura plików

```
JellyFood/
├── src/
│   ├── controllers/
│   │   ├── auth.php           ← Kontroler autoryzacji
│   │   └── home.php
│   ├── services/
│   │   └── AuthService.php    ← Serwis autoryzacji
│   └── repository/
│       ├── Repository.php
│       └── UserRepository.php ← Repozytorium użytkowników
├── public/
│   ├── views/
│   │   ├── register.html      ← Strona rejestracji
│   │   ├── login.html         ← Strona logowania
│   │   └── index.php
│   └── styles/
│       ├── register.css
│       └── login.css
├── docker/
│   ├── db/
│   │   ├── Dockerfile
│   │   └── init.sql           ← SQL do utworzenia tabel
│   ├── php/
│   │   └── Dockerfile
│   └── nginx/
│       ├── Dockerfile
│       └── nginx.conf
├── Database.php               ← Klasa połączenia z bazą
├── Routing.php                ← Routing aplikacji
├── docker-compose.yml
└── API_DOCUMENTATION.md       ← Dokumentacja API
```

---

## 🔧 Konfiguracja (.env)

```env
DB_HOST=db
DB_PORT=5432
DB_DATABASE=jellyfood
DB_USERNAME=docker
DB_PASSWORD=docker
DB_SSLMODE=prefer
```

---

## 📊 Schema bazy danych

```
roles (id, name)
  ↓
users (id, role_id, email, password_hash, full_name, phone, created_at)
  ├─ user_addresses
  ├─ orders → order_items → menu_items
  ├─ reviews
  ├─ favorites
  ├─ restaurant_system → restaurants
  ├─ couriers → deliveries
  └─ payments
```

---

## ⚙️ Docker Commands

```bash
# Start kontenery
docker compose up -d

# Rebuild
docker compose up -d --build

# Stop
docker compose down

# Logs
docker compose logs -f web
docker compose logs -f php
docker compose logs -f db

# Połączenie z bazą
docker exec jellyfood-db-1 psql -U docker -d db
```

---

## 🚀 Kolejne kroki

### Szybkie poprawki
1. ✅ Reset hasła
2. ✅ 2FA/MFA
3. ✅ OAuth (Google, Facebook)
4. ✅ Email confirmation

### API Endpoints
1. Profil użytkownika (GET, PUT)
2. Zmiana hasła
3. Usunięcie konta
4. Adresy dostawy

### Frontend
1. Nawigacja/Menu na stronie głównej
2. Dashboard użytkownika
3. Historia zamówień
4. Ulubione restauracje

---

## 🐛 Troubleshooting

### Błąd: "Cannot GET /auth/register"
- Sprawdź czy nginx ma prawidłowy `root /app/public/`
- Sprawdź czy `public/index.php` istnieje

### Błąd: "SQLSTATE[HY000]"
- Sprawdź zmienne w `.env`
- Sprawdź czy baza danych się uruchomiła: `docker logs jellyfood-db-1`

### Błąd: "Class not found"
- Sprawdź czy pliki PHP są w kontenerze: `docker exec jellyfood-web-1 ls -la /app/src/`
- Sprawdź `require_once` ścieżki

### Błąd: "Email już istnieje"
- To jest poprawny błąd - email musi być unikalny
- Spróbuj innego emailu

---

## 📝 Notatki

- Hasła są hashowane z `PASSWORD_BCRYPT` i `cost=12`
- Nie są przechowywane w logach
- Sessje są przechowywane na serwerze (PHP_SESSION)
- Email musi być unikalny
- Rola "klient" jest automatycznie przydzielana przy rejestracji

---

**Data utworzenia:** 24 listopada 2025  
**Autor:** Piotr  
**Status:** ✅ Gotowy do testowania
