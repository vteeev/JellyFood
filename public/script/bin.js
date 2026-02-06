let cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];

document.addEventListener('DOMContentLoaded', async () => {
    // Sprawdź status zalogowania i synchronizuj koszyk
    try {
        const authResponse = await fetch('/auth/check');
        const authData = await authResponse.json();
        
        if (authData.success && authData.user) {
            // Jeśli pracownik restauracji (role_id = 3), przekieruj na panel restauracji
            if (authData.user.role_id === 3) {
                window.location.href = '/orders/restaurant-dashboard';
                return;
            }
            
            // Użytkownik zalogowany (klient) - wyświetl menu usera
            const authButtons = document.getElementById('authButtons');
            const userProfile = document.getElementById('userProfile');
            const userName = document.getElementById('userName');
            
            authButtons.style.display = 'none';
            userProfile.style.display = 'flex';
            
            // Wyświetl pierwsze imię
            const firstName = authData.user.full_name.split(' ')[0];
            userName.textContent = firstName;
            
            // Synchronizuj koszyk z serwera
            const response = await fetch('/cart/get');
            const data = await response.json();
            
            if (data.success && data.data) {
                cart = data.data;
                localStorage.setItem('jellyFoodCart', JSON.stringify(cart));
            }
        }
        // Dla wylogowanego użytkownika koszyk pozostaje w localStorage (offline mode)
    } catch (error) {
        console.error('Błąd sprawdzenia autentykacji:', error);
    }
    
    renderCart();
});

function renderCart() {
    const cartContainer = document.getElementById('cartItemsList');
    
    if (cart.length === 0) {
        cartContainer.innerHTML = `
            <div style="text-align: center; padding: 3rem;">
                <span class="material-symbols-outlined" style="font-size: 4rem; color: var(--gray-400);">shopping_cart</span>
                <p style="font-size: 1.25rem; margin-top: 1rem; color: var(--gray-500);">Twój koszyk jest pusty</p>
                <button onclick="window.history.back()" style="margin-top: 1.5rem; padding: 0.75rem 1.5rem; background-color: var(--primary); border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer;">Wróć do zakupów</button>
            </div>
        `;
        updateSummary();
        return;
    }
    
    cartContainer.innerHTML = cart.map((item, index) => `
        <div class="cart-item">
          <div class="item-info">
            <div class="item-image" style="background-image: url('${item.image}');"></div>
            <div class="item-details">
              <p class="item-name">${item.name}</p>
              <p class="item-price">${item.price.toFixed(2)} zł</p>
            </div>
          </div>
          <div class="item-controls">
            <div class="quantity-control">
              <button class="qty-btn" onclick="updateQuantity(${index}, -1)">−</button>
              <span class="qty-display">${item.quantity}</span>
              <button class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
            </div>
            <p class="item-total">${(item.price * item.quantity).toFixed(2)} zł</p>
            <button class="remove-btn" onclick="removeItem(${index})" title="Usuń">
                <span class="material-symbols-outlined">delete</span>
            </button>
          </div>
        </div>
    `).join('');
    
    updateSummary();
}

function updateQuantity(index, delta) {
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    localStorage.setItem('jellyFoodCart', JSON.stringify(cart));
    saveCartToServer();
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    localStorage.setItem('jellyFoodCart', JSON.stringify(cart));
    saveCartToServer();
    renderCart();
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

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const delivery = cart.length > 0 ? 9.99 : 0;
    const total = subtotal + delivery;
    
    document.getElementById('subtotalAmount').textContent = `${subtotal.toFixed(2)} zł`;
    document.getElementById('totalAmount').textContent = `${total.toFixed(2)} zł`;
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

document.getElementById('checkoutBtn').addEventListener('click', () => {
    if (cart.length === 0) {
        alert('Twój koszyk jest pusty!');
        return;
    }
    
    // Przekierowanie do complete_order.html
    window.location.href = '/public/views/complete_order.html';
});
