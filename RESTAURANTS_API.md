# API Documentation - Restauracje

## Baza URL
`http://localhost:8080`

---

## Endpoints

### 1. Pobranie wszystkich restauracji
**GET** `/restaurants`

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Pasta Paradise",
      "description": "Tradycyjna włoska kuchnia z najlepszymi składnikami",
      "phone": "123456789",
      "street": "Marszałkowska",
      "building_number": "10",
      "apartment_number": null,
      "city": "Warszawa",
      "postal_code": "00-020",
      "created_at": "2025-11-24 10:30:00",
      "is_active": true,
      "kitchen_types": ["Włoskie", "Pizza"]
    }
  ],
  "count": 6
}
```

---

### 2. Pobranie restauracji po ID
**GET** `/restaurants/get/{id}`

#### Przykład
`GET /restaurants/get/1`

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Pasta Paradise",
    "description": "Tradycyjna włoska kuchnia z najlepszymi składnikami",
    "phone": "123456789",
    "street": "Marszałkowska",
    "building_number": "10",
    "apartment_number": null,
    "city": "Warszawa",
    "postal_code": "00-020",
    "created_at": "2025-11-24 10:30:00",
    "is_active": true,
    "kitchen_types": ["Włoskie", "Pizza"]
  }
}
```

#### Odpowiedź - Błąd (404)
```json
{
  "success": false,
  "message": "Restauracja nie znaleziona"
}
```

---

### 3. Wyszukiwanie restauracji
**GET** `/restaurants/search?q={query}`

#### Parametry
| Parametr | Typ | Wymagane | Opis |
|----------|-----|----------|------|
| q | string | ✓ | Szukany tekst (minimum 2 znaki) |

#### Przykład
`GET /restaurants/search?q=pizza`

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Pasta Paradise",
      "description": "Tradycyjna włoska kuchnia z najlepszymi składnikami",
      "phone": "123456789",
      "street": "Marszałkowska",
      "building_number": "10",
      "city": "Warszawa",
      "postal_code": "00-020",
      "is_active": true,
      "kitchen_types": ["Włoskie", "Pizza"]
    },
    {
      "id": 6,
      "name": "Pizza Firenze",
      "description": "Neapolitańska pizza z pieca na drewno",
      "phone": "123456794",
      "street": "Krakowskie Przedmieście",
      "building_number": "55",
      "city": "Warszawa",
      "postal_code": "00-071",
      "is_active": true,
      "kitchen_types": ["Pizza", "Włoskie"]
    }
  ],
  "count": 2,
  "query": "pizza"
}
```

---

### 4. Pobranie wszystkich typów kuchni
**GET** `/restaurants/kitchen-types`

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "FastFood"
    },
    {
      "id": 2,
      "name": "Pizza"
    },
    {
      "id": 3,
      "name": "Burger"
    },
    {
      "id": 4,
      "name": "Azjatyckie"
    },
    {
      "id": 5,
      "name": "Sushi"
    },
    {
      "id": 6,
      "name": "Włoskie"
    }
  ],
  "count": 6
}
```

---

### 5. Pobranie restauracji po typie kuchni
**GET** `/restaurants/by-kitchen?type={type}`

#### Parametry
| Parametr | Typ | Wymagane | Opis |
|----------|-----|----------|------|
| type | string | ✓ | Nazwa typu kuchni |

#### Przykład
`GET /restaurants/by-kitchen?type=Pizza`

#### Odpowiedź - Sukces (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Pasta Paradise",
      "description": "Tradycyjna włoska kuchnia z najlepszymi składnikami",
      "phone": "123456789",
      "street": "Marszałkowska",
      "building_number": "10",
      "city": "Warszawa",
      "postal_code": "00-020",
      "is_active": true,
      "kitchen_types": ["Włoskie", "Pizza"]
    },
    {
      "id": 6,
      "name": "Pizza Firenze",
      "description": "Neapolitańska pizza z pieca na drewno",
      "phone": "123456794",
      "street": "Krakowskie Przedmieście",
      "building_number": "55",
      "city": "Warszawa",
      "postal_code": "00-071",
      "is_active": true,
      "kitchen_types": ["Pizza", "Włoskie"]
    }
  ],
  "count": 2,
  "kitchen_type": "Pizza"
}
```

---

## Przykłady użycia JavaScript

### Pobranie wszystkich restauracji
```javascript
async function getRestaurants() {
  const response = await fetch('/restaurants');
  const data = await response.json();
  
  if (data.success) {
    console.log('Restauracje:', data.data);
  } else {
    console.error('Błąd:', data.message);
  }
}

getRestaurants();
```

### Wyszukiwanie restauracji
```javascript
async function searchRestaurants(query) {
  const response = await fetch(`/restaurants/search?q=${encodeURIComponent(query)}`);
  const data = await response.json();
  
  if (data.success) {
    console.log(`Znaleziono ${data.count} restauracji dla "${query}"`);
    console.log('Wyniki:', data.data);
  } else {
    console.error('Błąd:', data.message);
  }
}

searchRestaurants('pizza');
```

### Pobranie restauracji po typie kuchni
```javascript
async function getRestaurantsByType(type) {
  const response = await fetch(`/restaurants/by-kitchen?type=${encodeURIComponent(type)}`);
  const data = await response.json();
  
  if (data.success) {
    console.log(`Restauracje typu "${type}":`, data.data);
  } else {
    console.error('Błąd:', data.message);
  }
}

getRestaurantsByType('Pizza');
```

### Pobranie typów kuchni do dropdownu
```javascript
async function loadKitchenTypes() {
  const response = await fetch('/restaurants/kitchen-types');
  const data = await response.json();
  
  if (data.success) {
    const types = data.data;
    const select = document.getElementById('kitchenTypeSelect');
    
    types.forEach(type => {
      const option = document.createElement('option');
      option.value = type.name;
      option.textContent = type.name;
      select.appendChild(option);
    });
  }
}

loadKitchenTypes();
```

---

## Struktura bazy danych

```
restaurants (id, name, description, phone, street, building_number, city, postal_code, is_active)
    ↓
restaurant_kitchen_types (restaurant_id, kitchen_type_id)
    ↓
kitchen_types (id, name)
```

---

## Dostępne typy kuchni
- FastFood
- Pizza
- Burger
- Azjatyckie
- Sushi
- Włoskie

---

## Restauracje w systemie
1. **Pasta Paradise** - Włoskie, Pizza
2. **Thai Palace** - Azjatyckie
3. **Burger House** - Burger
4. **Greek Taverna** - Włoskie
5. **Sushi Express** - Azjatyckie, Sushi
6. **Pizza Firenze** - Pizza, Włoskie
