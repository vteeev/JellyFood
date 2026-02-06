# 🚀 JellyFood - Pełna Konfiguracja i Setup

## ✅ Co Zostało Zrobione

### 1. Baza Danych - 15 Tabel
```
✅ roles
✅ users (z hashowaniem bcrypt)
✅ user_addresses
✅ restaurants
✅ restaurant_opening_hours
✅ restaurant_system
✅ menu_categories
✅ menu_items
✅ orders
✅ order_items
✅ couriers
✅ deliveries
✅ payments
✅ reviews
✅ favorites
✅ kitchen_types (NOWE)
✅ restaurant_kitchen_types (NOWE)
```

### 2. System Autoryzacji
```
✅ Rejestracja (z walidacją)
✅ Logowanie (z bcrypt)
✅ Wylogowanie
✅ Sprawdzenie statusu (/auth/check)
✅ Sesje PHP
✅ Dynamiczny header (ikona zamiast przycisków)
```

### 3. API REST Restauracji
```
✅ GET /restaurants - wszystkie
✅ GET /restaurants/get/{id} - po ID
✅ GET /restaurants/search?q=... - wyszukiwanie
✅ GET /restaurants/kitchen-types - typy kuchni
✅ GET /restaurants/by-kitchen?type=... - po typie
```

### 4. Frontend
```
✅ Dynamiczne ładowanie restauracji
✅ Wyszukiwanie w real-time
✅ Filtrowanie po typie kuchni
✅ Menu użytkownika
✅ Responsywny design
```

---

## 🎯 Szybki Start

### 1. Uruchamianie
```bash
cd C:\Users\piotr\OneDrive\Code\JellyFood
docker compose up -d
```

### 2. Odwiedzenie Aplikacji
```
http://localhost:8080
```

### 3. Testowanie
- **Rejestracja:** http://localhost:8080/register
- **Logowanie:** http://localhost:8080/login
- **Strona główna:** http://localhost:8080

---

## 📍 Lokalne Adresy

| Usługa | URL | Credentials |
|--------|-----|-------------|
| JellyFood | http://localhost:8080 | - |
| Pgadmin | http://localhost:5050 | admin@example.com / admin |
| Baza PostgreSQL | localhost:5433 | docker / docker |

---

## 🔍 Testowanie API za Pomocą Curl

### Restauracje
```bash
# Wszystkie restauracje
curl http://localhost:8080/restaurants

# Wyszukiwanie
curl "http://localhost:8080/restaurants/search?q=pizza"

# Typy kuchni
curl http://localhost:8080/restaurants/kitchen-types

# Po typie
curl "http://localhost:8080/restaurants/by-kitchen?type=Pizza"
```

### Autoryzacja
```bash
# Rejestracja
curl -X POST http://localhost:8080/auth/register \
  -d "email=test@example.com&password=Password123&password_confirm=Password123&full_name=Test User"

# Logowanie
curl -X POST http://localhost:8080/auth/login \
  -d "email=test@example.com&password=Password123" \
  -c cookies.txt

# Status
curl http://localhost:8080/auth/check -b cookies.txt

# Wylogowanie
curl http://localhost:8080/auth/logout -b cookies.txt
```

---

## 📊 Baza Danych

### Dostęp via Pgadmin
1. Wejdź na http://localhost:5050
2. Zaloguj: admin@example.com / admin
3. Dodaj serwer:
   - Host: db
   - Port: 5432
   - Database: db
   - Username: docker
   - Password: docker

### Dostęp via CLI
```bash
docker exec jellyfood-db-1 psql -U docker -d db

# W psql:
\dt              # Pokaż tabele
SELECT * FROM users;
SELECT * FROM restaurants;
SELECT * FROM kitchen_types;
```

---

## 🗂️ ファイル Struktura

### PHP Pliki
```
src/
├── controllers/
│   ├── auth.php           ✅ API autoryzacji (POST register/login, GET logout/check)
│   ├── restaurants.php    ✅ API restauracji (GET all/search/kitchen-types/by-kitchen)
│   └── home.php           ✅ Strona główna
├── services/
│   └── AuthService.php    ✅ Logika autentykacji (register, login, logout, check)
└── repository/
    ├── Repository.php              ✅ Klasa bazowa
    ├── UserRepository.php          ✅ Operacje na users
    └── RestaurantRepository.php    ✅ Operacje na restauracjach
```

### HTML Pliki
```
public/views/
├── main_page.html    ✅ Dynamiczne ładowanie restauracji + menu użytkownika
├── register.html     ✅ Formularz rejestracji
└── login.html        ✅ Formularz logowania
```

### CSS Pliki
```
public/styles/
├── main_page.css     ✅ + style dla profilu i filtrów
├── register.css      ✅ + style dla błędów
└── login.css         ✅ + style dla błędów
```

---

## 🔐 Bezpieczeństwo

✅ **Hasła:** bcrypt (cost=12)  
✅ **SQL:** Prepared statements (PDO)  
✅ **XSS:** HTML escaping (htmlspecialchars)  
✅ **CSRF:** (do implementacji)  
✅ **Walidacja:** Email, hasło (8+ znaków), imię (2-255)  
✅ **Sesje:** PHP SESSION (server-side storage)

---

## 🧪 Testowe Dane

### Dostępne Typy Kuchni
```
1. FastFood
2. Pizza
3. Burger
4. Azjatyckie
5. Sushi
6. Włoskie
```

### Restauracje w Systemie
```
1. Pasta Paradise (Włoskie, Pizza)
2. Thai Palace (Azjatyckie)
3. Burger House (Burger)
4. Greek Taverna (Włoskie)
5. Sushi Express (Azjatyckie, Sushi)
6. Pizza Firenze (Pizza, Włoskie)
```

---

## 🛠️ Docker Polecenia

```bash
# Start
docker compose up -d

# Rebuild
docker compose up -d --build

# Stop
docker compose down

# Logs (live)
docker compose logs -f web

# Baza danych
docker exec jellyfood-db-1 psql -U docker -d db

# Restart kontenera
docker compose restart web

# Wejdź do kontenera
docker exec -it jellyfood-web-1 sh
```

---

## 🔧 Troubleshooting

### 403 Forbidden
```bash
# Sprawdź czy pliki istnieją
docker exec jellyfood-web-1 ls -la /app/public/

# Sprawdź nginx config
docker exec jellyfood-web-1 cat /etc/nginx/conf.d/default.conf
```

### Baza nie ma tabel
```bash
# Wejdź do bazy i sprawdź
docker exec jellyfood-db-1 psql -U docker -d db -c "\dt"

# Jeśli brakuje, uruchom init
docker cp docker/db/init.sql jellyfood-db-1:/init.sql
docker exec jellyfood-db-1 psql -U docker -d db -f /init.sql
```

### API nie zwraca danych
```bash
# Sprawdź czy kontroler istnieje
docker exec jellyfood-web-1 ls -la /app/src/controllers/

# Sprawdź pliki PHP
docker exec jellyfood-web-1 php -l /app/src/controllers/restaurants.php
```

---

## 📝 Notatki Ważne

1. **Email musi być unikalny** - Nie można się zarejestrować tym samym emailem 2x
2. **Hasło minimum 8 znaków** - Walidacja po stronie frontend i backend
3. **Rola "klient" automatycznie** - Nowi użytkownicy zawsze mają tę rolę
4. **Sesje trwają do zamknięcia przeglądarki** - Lub do wylogowania
5. **API zwraca JSON** - Wszystkie odpowiedzi to application/json
6. **Динамickie restauracje** - Ładowane z API, nie hardkodowane

---

## 🚀 Kolejne Kroki

### Krótko (następne sesje)
- [ ] Koszyk (cart)
- [ ] Zamawianie
- [ ] Historia zamówień

### Średnio
- [ ] Śledzenie zamówienia
- [ ] System recenzji
- [ ] Ulubione restauracje

### Długo
- [ ] Płatności
- [ ] Admin panel
- [ ] Mobile app
- [ ] Notyfikacje email

---

## 📞 Wsparcie

Jeśli coś nie działa:

1. Sprawdź Docker logs
```bash
docker compose logs -f web
docker compose logs -f php
docker compose logs -f db
```

2. Sprawdź czy kontenery działają
```bash
docker compose ps
```

3. Restart wszystko
```bash
docker compose down
docker compose up -d --build
```

---

**Status: ✅ GOTOWE DO TESTOWANIA**

Data: 24 listopada 2025
