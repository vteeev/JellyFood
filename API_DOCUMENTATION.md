# API Documentation - JellyFood Authentication

## Autoryzacja - Rejestracja i Logowanie

### Baza URL
`http://localhost:8080`

---

## Endpoints

### 1. Rejestracja
**POST** `/auth/register`

#### Parametry (form-data lub x-www-form-urlencoded)
| Parametr | Typ | Wymagane | Opis |
|----------|-----|----------|------|
| email | string | ✓ | Adres email użytkownika (musi być unikalny) |
| password | string | ✓ | Hasło (minimum 8 znaków) |
| password_confirm | string | ✓ | Potwierdzenie hasła (musi być identyczne) |
| full_name | string | ✓ | Imię i nazwisko (2-255 znaków) |
| phone | string | ✗ | Numer telefonu |

#### Przykład żądania
```javascript
const response = await fetch('http://localhost:8080/auth/register', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    email: 'user@example.com',
    password: 'SecurePassword123',
    password_confirm: 'SecurePassword123',
    full_name: 'Jan Kowalski',
    phone: '123456789'
  }),
});

const data = await response.json();
```

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "message": "Rejestracja pomyślna",
  "user_id": 1
}
```

#### Odpowiedź - Błąd (200)
```json
{
  "success": false,
  "message": "Email już istnieje w systemie",
  "user_id": null
}
```

#### Możliwe błędy
- `"Niepoprawny format emailu"` - Email nie spełnia formatu
- `"Hasło musi mieć co najmniej 8 znaków"` - Hasło zbyt krótkie
- `"Imię i nazwisko musi mieć 2-255 znaków"` - Błędna długość nazwy
- `"Email już istnieje w systemie"` - Email już zarejestrowany
- `"Błąd podczas rejestracji"` - Błąd bazy danych

---

### 2. Logowanie
**POST** `/auth/login`

#### Parametry (form-data lub x-www-form-urlencoded)
| Parametr | Typ | Wymagane | Opis |
|----------|-----|----------|------|
| email | string | ✓ | Adres email użytkownika |
| password | string | ✓ | Hasło użytkownika |

#### Przykład żądania
```javascript
const response = await fetch('http://localhost:8080/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    email: 'user@example.com',
    password: 'SecurePassword123'
  }),
});

const data = await response.json();

if (data.success) {
  console.log('Zalogowano:', data.user);
}
```

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "message": "Logowanie pomyślne",
  "user": {
    "id": 1,
    "role_id": 2,
    "email": "user@example.com",
    "full_name": "Jan Kowalski",
    "phone": "123456789",
    "created_at": "2025-11-24 10:30:00",
    "role_name": "klient"
  }
}
```

#### Odpowiedź - Błąd (200)
```json
{
  "success": false,
  "message": "Niepoprawny email lub hasło",
  "user": null
}
```

#### Możliwe błędy
- `"Email i hasło są wymagane"` - Brakuje parametrów
- `"Niepoprawny email lub hasło"` - Błędne dane logowania

---

### 3. Wylogowanie
**GET** `/auth/logout`

#### Przykład żądania
```javascript
const response = await fetch('http://localhost:8080/auth/logout');
const data = await response.json();
```

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "message": "Wylogowanie pomyślne"
}
```

---

### 4. Sprawdzenie statusu logowania
**GET** `/auth/check`

Sprawdza czy aktualny użytkownik jest zalogowany i zwraca jego dane.

#### Przykład żądania
```javascript
const response = await fetch('http://localhost:8080/auth/check');
const data = await response.json();

if (data.success) {
  console.log('Zalogowany użytkownik:', data.user);
} else {
  console.log('Użytkownik nie jest zalogowany');
}
```

#### Odpowiedź - Zalogowany (200)
```json
{
  "success": true,
  "user": {
    "id": 1,
    "role_id": 2,
    "email": "user@example.com",
    "full_name": "Jan Kowalski",
    "phone": "123456789",
    "created_at": "2025-11-24 10:30:00",
    "role_name": "klient"
  }
}
```

#### Odpowiedź - Niezalogowany (200)
```json
{
  "success": false,
  "message": "Użytkownik nie jest zalogowany"
}
```

---

## Sesje i Ciasteczka

Po pomyślnym logowaniu, użytkownik otrzymuje sesję PHP. Sesja przechowuje:
- `user_id` - ID użytkownika
- `user_email` - Email użytkownika
- `user_role` - Rola użytkownika (klient, admin, itd.)
- `user_name` - Imię i nazwisko

Sesja jest przechowywana na serwerze i wysyłana jako `PHPSESSID` w ciasteczku.

---

## Bezpieczeństwo

### Hashowanie haseł
- Hasła są hashowane za pomocą **bcrypt** z `cost=12`
- Hasła nigdy nie są przechowywane w postaci tekstowej
- Hasła nie są zwracane w API (usuwane z odpowiedzi)

### Walidacja
- Email musi być prawidłowy (filter_var)
- Hasło musi mieć minimum 8 znaków
- Imię i nazwisko: 2-255 znaków
- HTML escaping dla wszystkich danych wejściowych

### SQL Injection Protection
- Wszystkie zapytania używają prepared statements (PDO)
- Parametry są bindowane (`:param`)

---

## Kody błędów HTTP

| Kod | Znaczenie |
|-----|-----------|
| 200 | OK - Żądanie powiodło się |
| 405 | Method Not Allowed - Zła metoda HTTP |
| 500 | Server Error - Błąd serwera |

---

## Przykład integracji frontend

```html
<!-- Rejestracja -->
<form id="registerForm">
  <input type="email" name="email" required />
  <input type="text" name="full_name" required />
  <input type="password" name="password" required />
  <input type="password" name="password_confirm" required />
  <button type="submit">Zarejestruj</button>
</form>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const response = await fetch('/auth/register', {
    method: 'POST',
    body: formData,
  });
  
  const result = await response.json();
  if (result.success) {
    alert('Rejestracja pomyślna!');
    window.location.href = '/login';
  } else {
    alert('Błąd: ' + result.message);
  }
});
</script>
```

---

## Zmienne środowiskowe (.env)

```env
DB_HOST=db
DB_PORT=5432
DB_DATABASE=jellyfood
DB_USERNAME=docker
DB_PASSWORD=docker
DB_SSLMODE=prefer
```

---

## Struktura plików

```
src/
├── controllers/
│   ├── auth.php           # Kontroler autoryzacji
│   └── home.php           # Kontroler główny
├── services/
│   └── AuthService.php    # Logika autoryzacji
└── repository/
    ├── Repository.php     # Klasa bazowa
    └── UserRepository.php # Repozytorium użytkowników
```
