let restaurantId = null;

// Pobierz ID restauracji z URL
document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    restaurantId = urlParams.get('id');
    
    // Sprawdź status zalogowania i synchronizuj koszyk
    try {
        const authResponse = await fetch('/auth/check');
        const authData = await authResponse.json();
        
        if (authData.success && authData.user) {
            // Użytkownik zalogowany - wyświetl menu usera
            const authButtons = document.getElementById('authButtons');
            const userProfile = document.getElementById('userProfile');
            const userName = document.getElementById('userName');
            
            authButtons.style.display = 'none';
            userProfile.style.display = 'flex';
            
            // Wyświetl pierwsze imię
            const firstName = authData.user.full_name.split(' ')[0];
            userName.textContent = firstName;
            
            // Synchronizuj koszyk
            await syncCartWithServer();
        }
        // Dla wylogowanego użytkownika koszyk pozostaje w localStorage (offline mode)
    } catch (error) {
        console.error('Błąd sprawdzenia autentykacji:', error);
    }
    
    updateCartBadge();
    
    if (restaurantId) {
        loadRestaurantData(restaurantId);
        loadRestaurantMenu(restaurantId);
    } else {
        document.getElementById('restaurantInfo').innerHTML = '<p class="restaurant-description">Nie znaleziono restauracji</p>';
    }
});

async function syncCartWithServer() {
    try {
        const response = await fetch('/cart/get');
        const data = await response.json();
        
        if (data.success && data.data) {
            localStorage.setItem('jellyFoodCart', JSON.stringify(data.data));
            updateCartBadge();
        }
    } catch (error) {
        console.error('Błąd synchronizacji koszyka:', error);
    }
}

async function loadRestaurantData(id) {
    try {
        const response = await fetch(`/restaurants/get/${id}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const restaurant = data.data;
            const imageUrl = restaurant.image_url || `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' font-size='24' fill='%23999' text-anchor='middle' dominant-baseline='middle'%3E${encodeURIComponent(restaurant.name.substring(0, 3))}</text%3E%3C/svg%3E`;
            
            // Aktualizuj tytuł
            document.querySelector('.restaurant-title').textContent = restaurant.name;
            document.title = `JellyFood - ${restaurant.name}`;

            // Aktualizuj zdjęcie w bannerze i logo
            const bannerImage = document.querySelector('.restaurant-banner img');
            if (bannerImage) {
                bannerImage.src = imageUrl;
                bannerImage.alt = `${restaurant.name} Banner`;
            }
            const logoImage = document.querySelector('.restaurant-logo img');
            if (logoImage) {
                logoImage.src = imageUrl;
                logoImage.alt = `${restaurant.name} Logo`;
            }
            
            // Aktualizuj opis
            document.querySelector('.restaurant-description').textContent = restaurant.description || 'Brak opisu';
            
            // Aktualizuj badges
            const badgesContainer = document.getElementById('restaurantBadges');
            badgesContainer.innerHTML = `
                <div class="badge star">
                    <span class="star-icon material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
                    <span class="badge-text">4.${(restaurant.id % 3) + 5}</span>
                    <span class="badge-reviews">(${200 + restaurant.id * 50} opinii)</span>
                </div>
            `;
            
            // Dodaj typy kuchni
            if (restaurant.kitchen_types) {
                const types = Array.isArray(restaurant.kitchen_types) 
                    ? restaurant.kitchen_types 
                    : restaurant.kitchen_types.toString().slice(1, -1).split(',');
                
                types.forEach(type => {
                    if (type && type.trim()) {
                        const badge = document.createElement('div');
                        badge.className = 'badge';
                        badge.innerHTML = `<p class="badge-text">${type.trim()}</p>`;
                        badgesContainer.appendChild(badge);
                    }
                });
            }
        }
    } catch (error) {
        console.error('Błąd ładowania restauracji:', error);
    }
}

async function loadRestaurantMenu(id) {
    try {
        const response = await fetch(`/restaurants/menu/${id}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            displayMenu(data.data);
        } else {
            document.getElementById('menuSections').innerHTML = '<p style="padding: 2rem; text-align: center;">Brak menu dla tej restauracji</p>';
        }
    } catch (error) {
        console.error('Błąd ładowania menu:', error);
        document.getElementById('menuSections').innerHTML = '<p style="padding: 2rem; text-align: center;">Błąd ładowania menu</p>';
    }
}

function displayMenu(menuCategories) {
    const menuContainer = document.getElementById('menuSections');
    menuContainer.innerHTML = '';
    
    const images = [
        'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1473093226795-af9932fe5856?auto=format&fit=crop&w=600&q=80'
    ];
    
    menuCategories.forEach(category => {
        if (category.items && category.items.length > 0) {
            const section = document.createElement('section');
            section.className = 'menu-section';
            section.id = `category-${category.id}`;
            
            const title = document.createElement('h2');
            title.className = 'section-title';
            title.textContent = category.name;
            section.appendChild(title);
            
            const grid = document.createElement('div');
            grid.className = 'dishes-grid';
            
            category.items.forEach((item, index) => {
                const card = document.createElement('button');
                card.className = 'dish-card';
                card.setAttribute('data-dish-id', item.id);
                card.setAttribute('data-dish-name', item.name);
                card.setAttribute('data-dish-price', item.price);
                card.setAttribute('data-dish-description', item.description || '');
                
                const imageUrl = item.image || images[index % images.length];
                card.setAttribute('data-dish-image', imageUrl);
                
                card.innerHTML = `
                    <div class="dish-info">
                        <h3 class="dish-name">${item.name}</h3>
                        <p class="dish-description">${item.description || ''}</p>
                        <p class="dish-price">${parseFloat(item.price).toFixed(2)} zł</p>
                    </div>
                    <div class="dish-image-container">
                        <img class="dish-image" src="${imageUrl}" alt="${item.name}" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=600&q=80';"/>
                    </div>
                `;
                
                card.addEventListener('click', () => openDishModal(item, imageUrl));
                
                grid.appendChild(card);
            });
            
            section.appendChild(grid);
            menuContainer.appendChild(section);
        }
    });
    
    if (menuContainer.children.length === 0) {
        menuContainer.innerHTML = '<p style="padding: 2rem; text-align: center;">Brak dań w menu</p>';
    }
}

// Koszyk - localStorage
let cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];

function updateCartBadge() {
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = totalItems;
        badge.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

// Przekierowanie do koszyka
document.getElementById('cartButton').addEventListener('click', () => {
    window.location.href = '/public/views/bin.html';
});

// Modal szczegółów dania
let currentDishPrice = 0;

function openDishModal(item, imageUrl) {
    currentDishPrice = parseFloat(item.price);
    modalQuantity = 1;
    
    const modal = document.createElement('div');
    modal.className = 'dish-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div class="modal-dish-image">
                <img src="${imageUrl}" alt="${item.name}"/>
            </div>
            <div class="modal-dish-details">
                <h2 class="modal-dish-name">${item.name}</h2>
                <p class="modal-dish-description">${item.description || 'Pyszne danie z naszej kuchni'}</p>
                <div class="modal-ingredients">
                    <h3>Składniki:</h3>
                    <p>${item.ingredients || 'Świeże, wysokiej jakości produkty'}</p>
                </div>
                <div class="modal-price-section">
                    <p class="modal-dish-price" id="modalPrice">${currentDishPrice.toFixed(2)} zł</p>
                    <div class="modal-quantity-control">
                        <button class="qty-button" onclick="changeQuantity(-1)">−</button>
                        <span class="quantity" id="modalQuantity">1</span>
                        <button class="qty-button" onclick="changeQuantity(1)">+</button>
                    </div>
                </div>
                <button class="add-to-cart-button" id="addToCartBtn">Dodaj do koszyka</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Event listener dla przycisku dodawania
    document.getElementById('addToCartBtn').addEventListener('click', () => {
        addToCart(item.id, item.name, item.price, imageUrl);
    });
    
    // Zamknij modal po kliknięciu poza contentem
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

function closeModal() {
    const modal = document.querySelector('.dish-modal');
    if (modal) {
        modal.remove();
    }
    modalQuantity = 1;
}

let modalQuantity = 1;

function changeQuantity(delta) {
    modalQuantity = Math.max(1, modalQuantity + delta);
    document.getElementById('modalQuantity').textContent = modalQuantity;
    
    // Aktualizuj cenę
    const totalPrice = currentDishPrice * modalQuantity;
    document.getElementById('modalPrice').textContent = `${totalPrice.toFixed(2)} zł`;
}

function addToCart(id, name, price, image) {
    const existingItem = cart.find(item => item.id === id);
    
    if (existingItem) {
        existingItem.quantity += modalQuantity;
    } else {
        cart.push({
            id,
            name,
            price: parseFloat(price),
            image,
            quantity: modalQuantity,
            restaurantId
        });
    }
    
    localStorage.setItem('jellyFoodCart', JSON.stringify(cart));
    
    // Zapisz na serwerze jeśli użytkownik jest zalogowany
    saveCartToServer();
    
    // Zamknij modal i pokaż powiadomienie
    closeModal();
    
    showNotification(`${name} dodano do koszyka!`);
    updateCartBadge();
}

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
        // Ignoruj błędy jeśli użytkownik nie jest zalogowany
        console.log('Koszyk zapisany tylko lokalnie');
    }
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
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
