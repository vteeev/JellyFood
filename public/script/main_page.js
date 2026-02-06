let allRestaurants = [];
let filteredRestaurants = [];
let selectedKitchenType = null;
let selectedCity = 'Warszawa';

// Sprawdzenie statusu autentykacji
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const response = await fetch('/auth/check');
    const data = await response.json();
    
    if (data.success && data.user) {
      // Jeśli pracownik restauracji (role_id = 3), przekieruj na panel restauracji
      if (data.user.role_id === 3) {
        window.location.href = '/orders/restaurant-dashboard';
        return;
      }
      
      // Użytkownik jest zalogowany (klient)
      const authButtons = document.getElementById('authButtons');
      const userProfile = document.getElementById('userProfile');
      const userName = document.getElementById('userName');
      
      authButtons.style.display = 'none';
      userProfile.style.display = 'flex';
      
      // Wyświetl pierwsze imię
      const firstName = data.user.full_name.split(' ')[0];
      userName.textContent = firstName;
      
      // Synchronizuj koszyk z serwerem
      await syncCartWithServer();
    }
  } catch (error) {
    console.error('Błąd sprawdzenia autentykacji:', error);
  }

  // Zaktualizuj licznik koszyka
  updateCartBadge();
  
  // Załaduj restauracje i typy kuchni
  loadRestaurants();
  loadKitchenTypes();
});

async function loadRestaurants() {
  try {
    let url = '/restaurants';
    if (selectedCity) {
      url = `/restaurants/by-city?city=${encodeURIComponent(selectedCity)}`;
    }
    
    const response = await fetch(url);
    const data = await response.json();
    
    if (data.success) {
      allRestaurants = data.data;
      filteredRestaurants = allRestaurants;
      displayRestaurants(filteredRestaurants);
    }
  } catch (error) {
    console.error('Błąd ładowania restauracji:', error);
  }
}

async function loadKitchenTypes() {
  try {
    const response = await fetch('/restaurants/kitchen-types');
    const data = await response.json();
    
    if (data.success) {
      const kitchenTypesGrid = document.getElementById('kitchenTypesGrid');
      kitchenTypesGrid.innerHTML = '';
      
      // Ikony dla różnych typów kuchni
      const kitchenIcons = {
        'Włoska': '🍝',
        'Amerykańska': '🍔',
        'Azjatycka': '🍜',
        'Polska': '🥟',
        'Chińska': '🥡',
        'Japońska': '🍱',
        'Meksykańska': '🌮',
        'Indyjska': '🍛',
        'Wegetariańska': '🥗',
        'Pizza': '🍕',
        'Sushi': '🍣',
        'Burger': '🍔',
        'Kebab': '🥙'
      };
      
      // Dodaj przycisk "Wszystkie"
      const allButton = document.createElement('div');
      allButton.className = 'kitchen-type-card active';
      allButton.innerHTML = `
        <div class="kitchen-type-icon" style="background-image: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=200'); background-size: cover; background-position: center; width: 60px; height: 60px; border-radius: 50%;"></div>
        <span class="kitchen-type-name">Wszystkie</span>
      `;
      allButton.onclick = () => filterByKitchenType(null);
      kitchenTypesGrid.appendChild(allButton);
      
      // Dodaj pozostałe typy kuchni
      data.data.forEach(type => {
        const card = document.createElement('div');
        card.className = 'kitchen-type-card';
        card.setAttribute('data-kitchen-type', type.name);
        
        // Użyj image_url z API lub domyślny SVG placeholder
        const imageUrl = type.image_url || `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' font-size='40' fill='%23999' text-anchor='middle' dominant-baseline='middle'%3E🍴%3C/text%3E%3C/svg%3E`;
        
        card.innerHTML = `
          <div class="kitchen-type-icon" style="background-image: url('${imageUrl}'); background-size: cover; background-position: center; width: 60px; height: 60px; border-radius: 50%;"></div>
          <span class="kitchen-type-name">${type.name}</span>
        `;
        card.onclick = () => filterByKitchenType(type.name);
        kitchenTypesGrid.appendChild(card);
      });
    }
  } catch (error) {
    console.error('Błąd ładowania typów kuchni:', error);
  }
}

function displayRestaurants(restaurants) {
  const grid = document.getElementById('restaurantsGrid');
  grid.innerHTML = '';
  
  if (restaurants.length === 0) {
    grid.innerHTML = '<p class="no-results">Nie znaleziono restauracji</p>';
    return;
  }
  
  restaurants.forEach(restaurant => {
    const card = document.createElement('div');
    card.className = 'restaurant-card';
    
    // Użyj image_url z API lub domyślny SVG placeholder
    const imageUrl = restaurant.image_url || `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' font-size='24' fill='%23999' text-anchor='middle' dominant-baseline='middle'%3E${encodeURIComponent(restaurant.name.substring(0, 3))}</text%3E%3C/svg%3E`;
    
    const kitchenTypes = Array.isArray(restaurant.kitchen_types) 
      ? restaurant.kitchen_types.filter(kt => kt !== null).join(', ') 
      : (restaurant.kitchen_types && restaurant.kitchen_types.toString().includes('{') 
         ? restaurant.kitchen_types.toString().slice(1, -1).replaceAll(',', ', ')
         : '');
    
    card.innerHTML = `
      <div class="restaurant-card-image" style="background-image: url('${imageUrl}'); background-size: cover; background-position: center;"></div>
      <div class="restaurant-card-content">
        <p class="restaurant-name">${restaurant.name}</p>
        <p class="restaurant-description" style="font-size: 0.85rem; color: #A0AEC0; margin: 4px 0;">${restaurant.description}</p>
        <p class="restaurant-info">${kitchenTypes || 'Różne'} • 30-40 min • 15 zł</p>
        <div class="restaurant-rating">
          <span class="rating-star">★</span>
          <span>4.${(restaurant.id % 3) + 5} (${200 + restaurant.id * 50} opinii)</span>
        </div>
      </div>
    `;
    
    card.addEventListener('click', () => {
      window.location.href = `/restaurant?id=${restaurant.id}`;
    });
    
    grid.appendChild(card);
  });
}

async function searchRestaurants() {
  const query = document.getElementById('searchInput').value;
  
  if (query.length < 2) {
    filteredRestaurants = allRestaurants;
    displayRestaurants(filteredRestaurants);
    return;
  }
  
  try {
    const response = await fetch(`/restaurants/search?q=${encodeURIComponent(query)}`);
    const data = await response.json();
    
    if (data.success) {
      filteredRestaurants = data.data;
      displayRestaurants(filteredRestaurants);
    }
  } catch (error) {
    console.error('Błąd wyszukiwania:', error);
  }
}

async function filterByCity() {
  const citySelect = document.getElementById('citySelect');
  selectedCity = citySelect.value;
  
  // Załaduj restauracje dla wybranego miasta
  await loadRestaurants();
  
  // Zresetuj filtr typu kuchni
  selectedKitchenType = null;
  document.querySelectorAll('.kitchen-type-card').forEach(card => {
    card.classList.remove('active');
  });
  document.querySelectorAll('.kitchen-type-card')[0]?.classList.add('active');
}

function filterByKitchenType(type) {
  selectedKitchenType = type;
  
  // Aktualizuj aktywną kartę
  document.querySelectorAll('.kitchen-type-card').forEach(card => {
    card.classList.remove('active');
  });
  
  if (type === null) {
    filteredRestaurants = allRestaurants;
    document.querySelectorAll('.kitchen-type-card')[0].classList.add('active');
  } else {
    filteredRestaurants = allRestaurants.filter(r => 
      Array.isArray(r.kitchen_types) 
        ? r.kitchen_types.includes(type)
        : r.kitchen_types.toString().includes(type)
    );
    const activeCard = document.querySelector(`[data-kitchen-type="${type}"]`);
    if (activeCard) activeCard.classList.add('active');
  }
  
  displayRestaurants(filteredRestaurants);
}

function toggleUserMenu() {
  const dropdown = document.getElementById('userDropdown');
  dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Zamknięcie menu użytkownika gdy klikniesz gdzie indziej
document.addEventListener('click', (e) => {
  const userProfile = document.getElementById('userProfile');
  if (userProfile && !userProfile.contains(e.target)) {
    document.getElementById('userDropdown').style.display = 'none';
  }
});

async function logout() {
  try {
    // Zapisz koszyk przed wylogowaniem
    const cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
    if (cart.length > 0) {
      await fetch('/cart/save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ cart: cart })
      });
    }
    
    // Wyloguj
    await fetch('/auth/logout');
    
    // Wyczyść koszyk lokalny (tylko po wylogowaniu)
    localStorage.removeItem('jellyFoodCart');
    
    // Przeniesienie na stronę główną
    window.location.href = '/';
  } catch (error) {
    console.error('Błąd wylogowania:', error);
  }
}

// Funkcja synchronizacji koszyka z serwerem
async function syncCartWithServer() {
  try {
    const response = await fetch('/cart/get');
    const data = await response.json();
    
    if (data.success && data.data) {
      // Zaktualizuj lokalny koszyk danymi z serwera
      localStorage.setItem('jellyFoodCart', JSON.stringify(data.data));
      updateCartBadge();
    }
  } catch (error) {
    console.error('Błąd synchronizacji koszyka:', error);
  }
}

// Funkcja zapisywania koszyka na serwerze (wywoływana przy zmianach)
async function saveCartToServer() {
  try {
    const cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
    
    await fetch('/cart/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ cart: cart })
    });
  } catch (error) {
    console.error('Błąd zapisu koszyka:', error);
  }
}

// Funkcja aktualizacji licznika koszyka
function updateCartBadge() {
  const cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const badge = document.getElementById('cartBadge');
  if (badge) {
    badge.textContent = totalItems;
    badge.style.display = totalItems > 0 ? 'flex' : 'none';
  }
}

// Aktualizuj licznik koszyka przy ładowaniu strony
updateCartBadge();
