let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', async () => {
    await loadUserInfo();
    await loadActiveOrders();
});

async function loadUserInfo() {
    try {
        const response = await fetch('/auth/check');
        
        if (!response.ok) {
            window.location.href = '/login';
            return;
        }
        
        const data = await response.json();
        
        if (data.authenticated && data.user) {
            document.getElementById('userProfile').style.display = 'block';
            document.getElementById('userName').textContent = data.user.full_name.split(' ')[0];
        } else {
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Błąd ładowania informacji użytkownika:', error);
        window.location.href = '/login';
    }
}

function switchTab(tab) {
    // Ukryj wszystkie sekcje
    document.querySelectorAll('.orders-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Usuń active z przycisków
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Pokaż wybraną sekcję
    document.getElementById(tab).classList.add('active');
    
    // Dodaj active do przycisku
    event.target.closest('.tab-button').classList.add('active');
    
    if (tab === 'history') {
        loadHistoryOrders(1);
    } else {
        loadActiveOrders();
    }
}

async function loadActiveOrders() {
    try {
        const response = await fetch('/orders/restaurant-dashboard');
        const data = await response.json();

        if (data.success) {
            displayActiveOrders(data.orders);
        } else {
            document.getElementById('activeOrdersList').innerHTML = `
                <div class="no-orders">
                    <div class="no-orders-icon">❌</div>
                    <p>${data.message || 'Błąd ładowania zamówień'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Błąd ładowania zamówień:', error);
        document.getElementById('activeOrdersList').innerHTML = '<p style="color: #e74c3c; text-align: center;">Błąd połączenia z serwerem</p>';
    }
}

function displayActiveOrders(orders) {
    const container = document.getElementById('activeOrdersList');

    if (orders.length === 0) {
        container.innerHTML = `
            <div class="no-orders">
                <div class="no-orders-icon">✅</div>
                <p>Brak aktywnych zamówień</p>
            </div>
        `;
        return;
    }

    container.innerHTML = orders.map(order => `
        <div class="order-card">
            <div class="order-card-header">
                <div>
                    <div class="order-id">Zamówienie #${order.id}</div>
                    <div class="customer-info">
                        ${order.customer_name}
                        <a href="tel:${order.customer_phone}" class="customer-phone">${order.customer_phone || 'Brak numeru'}</a>
                    </div>
                </div>
                <span class="order-status ${order.status}">${order.status_label}</span>
            </div>

            <div class="order-details-grid">
                <div class="detail-item">
                    <span class="detail-label">Liczba pozycji</span>
                    <span class="detail-value">${order.items_count}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Adres dostawy</span>
                    <span class="detail-value">${order.street || ''}, ${order.city || 'Brak adresu'}</span>
                </div>
            </div>

            <div class="items-list">
                <strong>Artykuły:</strong><br>
                ${order.items_list || 'Brak artykułów'}
            </div>

            <div class="order-footer">
                <span class="order-amount">${order.total_amount.toFixed(2)} zł</span>
                <div class="order-actions">
                    <select class="status-dropdown" id="status-${order.id}">
                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Oczekiwanie</option>
                        <option value="accepted" ${order.status === 'accepted' ? 'selected' : ''}>Zaakceptowane</option>
                        <option value="preparing" ${order.status === 'preparing' ? 'selected' : ''}>Przygotowanie</option>
                        <option value="ready_for_pickup" ${order.status === 'ready_for_pickup' ? 'selected' : ''}>Gotowe do odebrania</option>
                        <option value="picked_up" ${order.status === 'picked_up' ? 'selected' : ''}>Wydane kurierowi</option>
                    </select>
                    <button class="btn-update" onclick="updateOrderStatus(${order.id})">Zaktualizuj</button>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadHistoryOrders(page = 1) {
    try {
        const response = await fetch(`/orders/restaurant-history?page=${page}`);
        const data = await response.json();

        if (data.success) {
            currentPage = page;
            totalPages = data.pagination.pages;
            displayHistoryOrders(data.orders);
            displayPagination(data.pagination);
        } else {
            document.getElementById('historyOrdersList').innerHTML = `
                <div class="no-orders">
                    <div class="no-orders-icon">❌</div>
                    <p>${data.message || 'Błąd ładowania historii'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Błąd ładowania historii:', error);
        document.getElementById('historyOrdersList').innerHTML = '<p style="color: #e74c3c; text-align: center;">Błąd połączenia z serwerem</p>';
    }
}

function displayHistoryOrders(orders) {
    const container = document.getElementById('historyOrdersList');

    if (orders.length === 0) {
        container.innerHTML = `
            <div class="no-orders">
                <div class="no-orders-icon">📋</div>
                <p>Brak historii zamówień</p>
            </div>
        `;
        return;
    }

    container.innerHTML = orders.map(order => `
        <div class="order-card">
            <div class="order-card-header">
                <div>
                    <div class="order-id">Zamówienie #${order.id}</div>
                    <div class="customer-info">${order.customer_name}</div>
                </div>
                <span class="order-status ${order.status}">${order.status_label}</span>
            </div>

            <div class="order-details-grid">
                <div class="detail-item">
                    <span class="detail-label">Liczba pozycji</span>
                    <span class="detail-value">${order.items_count}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Data</span>
                    <span class="detail-value">${order.created_at_formatted}</span>
                </div>
            </div>

            <div class="order-footer">
                <span class="order-amount">${order.total_amount.toFixed(2)} zł</span>
            </div>
        </div>
    `).join('');
}

function displayPagination(pagination) {
    const container = document.getElementById('historyPagination');

    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    if (pagination.page > 1) {
        html += `<button onclick="loadHistoryOrders(${pagination.page - 1})">← Poprzednia</button>`;
    }

    for (let i = 1; i <= pagination.pages; i++) {
        const activeClass = i === pagination.page ? 'active' : '';
        html += `<button class="${activeClass}" onclick="loadHistoryOrders(${i})">${i}</button>`;
    }

    if (pagination.page < pagination.pages) {
        html += `<button onclick="loadHistoryOrders(${pagination.page + 1})">Następna →</button>`;
    }

    container.innerHTML = html;
}

async function updateOrderStatus(orderId) {
    const statusSelect = document.getElementById(`status-${orderId}`);
    const newStatus = statusSelect.value;
    const button = statusSelect.nextElementSibling;

    console.log('Rozpoczynam aktualizację zamówienia:', orderId, 'na status:', newStatus);
    
    button.disabled = true;
    button.textContent = 'Aktualizowanie...';

    try {
        console.log('Wysyłam request do /orders/update-status');
        const response = await fetch('/orders/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                order_id: parseInt(orderId),
                status: newStatus
            })
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);
        
        const responseText = await response.text();
        console.log('Response text (pierwsze 500 znaków):', responseText.substring(0, 500));
        
        let result;
        try {
            result = JSON.parse(responseText);
            console.log('Sparsowany JSON:', result);
        } catch (e) {
            console.error('Błąd parsowania JSON:', e);
            console.error('Pełna odpowiedź:', responseText);
            alert('BŁĄD PARSOWANIA JSON!\n\nOdpowiedź serwera:\n' + responseText.substring(0, 1000) + '\n\n(Sprawdź konsolę F12 aby zobaczyć pełną odpowiedź)');
            throw new Error('Odpowiedź serwera nie jest prawidłowym JSON.');
        }

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}, message: ${result.message || responseText}`);
        }

        if (result.success) {
            alert('Status zamówienia został zaktualizowany');
            await loadActiveOrders();
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się zaktualizować statusu'));
        }
    } catch (error) {
        console.error('Błąd aktualizacji:', error);
        alert('Błąd połączenia z serwerem: ' + error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Zaktualizuj';
    }
}

function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', (e) => {
    const userProfile = document.getElementById('userProfile');
    if (userProfile && !userProfile.contains(e.target)) {
        document.getElementById('userDropdown').style.display = 'none';
    }
});

async function logout() {
    try {
        await fetch('/auth/logout');
        window.location.href = '/';
    } catch (error) {
        console.error('Błąd wylogowania:', error);
    }
}
