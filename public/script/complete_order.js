let cart = [];
let userAddress = null;

// Załaduj dane przy starcie
document.addEventListener('DOMContentLoaded', async () => {
    await loadUserAddress();
    loadCartItems();
});

// Załaduj adres użytkownika z bazy
async function loadUserAddress() {
    try {
        const response = await fetch('/auth/check');
        
        if (!response.ok) {
            console.error('Błąd odpowiedzi serwera:', response.status);
            window.location.href = '/login';
            return;
        }
        
        const data = await response.json();
        
        if (data.authenticated && data.user) {
            const userId = data.user.id;
            
            // Pobierz adres użytkownika
            const addressResponse = await fetch(`/users/${userId}/address`);
            
            if (!addressResponse.ok) {
                console.error('Błąd pobierania adresu:', addressResponse.status);
                document.getElementById('deliveryAddress').textContent = 'Brak zapisanego adresu';
                document.getElementById('addAddressBtn').style.display = 'block';
                return;
            }
            
            const addressData = await addressResponse.json();
            
            if (addressData.success && addressData.address) {
                userAddress = addressData.address;
                document.getElementById('deliveryAddress').textContent = 
                    `${userAddress.street}, ${userAddress.postal_code} ${userAddress.city}`;
                document.getElementById('changeAddressBtn').style.display = 'block';
            } else {
                document.getElementById('deliveryAddress').textContent = 'Brak zapisanego adresu';
                document.getElementById('addAddressBtn').style.display = 'block';
            }
        } else {
            console.log('Użytkownik nie jest zalogowany');
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Błąd ładowania adresu:', error);
        // Przy błędzie sieci nie przekierowuj automatycznie
        alert('Błąd połączenia z serwerem. Sprawdź połączenie internetowe.');
    }
}

// Załaduj produkty z koszyka
function loadCartItems() {
    cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
    
    if (cart.length === 0) {
        alert('Twój koszyk jest pusty!');
        window.location.href = '/';
        return;
    }
    
    const itemsContainer = document.getElementById('orderItemsList');
    itemsContainer.innerHTML = cart.map(item => `
        <div class="summary-item">
            <p class="summary-item-name">${item.quantity}x ${item.name}</p>
            <p class="summary-item-price">${(item.price * item.quantity).toFixed(2)} zł</p>
        </div>
    `).join('');
    
    // Oblicz sumę
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const delivery = 9.99;
    const total = subtotal + delivery;
    
    document.getElementById('subtotalPrice').textContent = `${subtotal.toFixed(2)} zł`;
    document.getElementById('totalPrice').textContent = `${total.toFixed(2)} zł`;
    document.getElementById('payButton').textContent = `Zamów i zapłać ${total.toFixed(2)} zł`;
}

// Modal adresu
function openAddressModal() {
    const modal = document.getElementById('addressModal');
    modal.style.display = 'flex';
    
    if (userAddress) {
        document.getElementById('street').value = userAddress.street || '';
        document.getElementById('city').value = userAddress.city || '';
        document.getElementById('postalCode').value = userAddress.postal_code || '';
    }
}

function closeAddressModal() {
    document.getElementById('addressModal').style.display = 'none';
}

// Zapisz adres
async function saveAddress(event) {
    event.preventDefault();
    
    const formData = {
        street: document.getElementById('street').value,
        city: document.getElementById('city').value,
        postal_code: document.getElementById('postalCode').value
    };
    
    try {
        const response = await fetch('/users/address', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadUserAddress();
            closeAddressModal();
        } else {
            alert('Błąd zapisu adresu: ' + (data.message || 'Nieznany błąd'));
        }
    } catch (error) {
        console.error('Błąd zapisywania adresu:', error);
        alert('Błąd połączenia z serwerem');
    }
}

// Przetwarzanie płatności (Stripe)
async function processPayment() {
    if (!userAddress) {
        alert('Proszę dodać adres dostawy przed złożeniem zamówienia');
        return;
    }
    
    if (cart.length === 0) {
        alert('Twój koszyk jest pusty!');
        return;
    }
    
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const delivery = 9.99;
    const total = subtotal + delivery;
    const notes = document.getElementById('notes').value;
    
    try {
        document.getElementById('payButton').disabled = true;
        document.getElementById('payButton').textContent = 'Przetwarzanie...';
        
        // Przygotuj dane zamówienia
        const orderData = {
            items: cart.map(item => ({
                id: item.id,
                restaurant_id: item.restaurantId,
                name: item.name,
                price: item.price,
                quantity: item.quantity
            })),
            address: userAddress,
            notes: notes,
            subtotal: subtotal,
            delivery: delivery,
            total: total
        };
        
        // Wyślij zamówienie na serwer
        const response = await fetch('/orders/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Wyczyść koszyk
            localStorage.removeItem('jellyFoodCart');
            alert('Zamówienie zostało złożone pomyślnie!');
            window.location.href = '/orders';
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się złożyć zamówienia'));
            document.getElementById('payButton').disabled = false;
            loadCartItems();
        }
        
    } catch (error) {
        console.error('Błąd przetwarzania zamówienia:', error);
        alert('Błąd połączenia z serwerem');
        document.getElementById('payButton').disabled = false;
        loadCartItems();
    }
}

// Zamknij modal klikając poza nim
window.onclick = function(event) {
    const modal = document.getElementById('addressModal');
    if (event.target === modal) {
        closeAddressModal();
    }
}
