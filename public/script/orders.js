let currentPage = 1;
let totalPages = 1;
let currentUser = null;

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
            
            currentUser = data.user;

            const userProfile = document.getElementById('userProfile');
            const userName = document.getElementById('userName');

            if (userProfile) {
                userProfile.style.display = 'flex';
                const firstName = data.user.full_name.split(' ')[0];
                userName.textContent = firstName;
            }

            loadOrdersAndStats();
        } else {
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Błąd sprawdzenia autentykacji:', error);
        window.location.href = '/login';
    }

    updateCartBadge();
});

async function loadOrdersAndStats() {
    try {
        // Załaduj statystyki
        const statsResponse = await fetch('/orders/stats', {
            headers: {'Accept': 'application/json'}
        });
        const statsData = await statsResponse.json();

        if (statsData.success) {
            document.getElementById('totalOrders').textContent = statsData.stats.total_orders;
            document.getElementById('totalSpent').textContent = statsData.stats.total_spent.toFixed(2) + ' zł';
            document.getElementById('averageValue').textContent = statsData.stats.average_order_value.toFixed(2) + ' zł';
        }

        // Załaduj zamówienia
        loadOrders(1);
    } catch (error) {
        console.error('Błąd ładowania danych:', error);
    }
}

async function loadOrders(page = 1) {
    try {
        const response = await fetch(`/orders?page=${page}`, {
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (data.success) {
            currentPage = page;
            totalPages = data.pagination.pages;
            displayOrders(data.orders);
            displayPagination(data.pagination);
        } else {
            document.getElementById('ordersList').innerHTML = `
                <div class="no-orders">
                    <div class="no-orders-icon">📭</div>
                    <p>Brak zamówień</p>
                    <p style="font-size: 12px;">Zaloguj się i złóż swoje pierwsze zamówienie!</p>
                    <a href="/">Powrót do głównej</a>
                </div>
            `;
        }
    } catch (error) {
        console.error('Błąd ładowania zamówień:', error);
        document.getElementById('ordersList').innerHTML = '<p style="color: #e74c3c; text-align: center;">Błąd podczas ładowania zamówień</p>';
    }
}

function displayOrders(orders) {
    const container = document.getElementById('ordersList');

    if (orders.length === 0) {
        container.innerHTML = `
            <div class="no-orders">
                <div class="no-orders-icon">📭</div>
                <p>Brak zamówień</p>
                <p style="font-size: 12px;">Zaloguj się i złóż swoje pierwsze zamówienie!</p>
                <a href="/">Powrót do głównej</a>
            </div>
        `;
        return;
    }

    container.innerHTML = orders.map(order => `
        <div class="order-card" onclick="viewOrderDetails(${order.id})">
            <div class="order-header">
                <div>
                    <p class="order-title">${order.restaurant_name || 'Restauracja'}</p>
                    <p class="order-date">${order.created_at_formatted}</p>
                </div>
                <span class="order-status status-${getStatusClass(order.status)}">${order.status_label}</span>
            </div>

            <div class="order-details">
                <div class="detail-item">
                    <span class="detail-label">Liczba pozycji</span>
                    <span class="detail-value">${order.items_count}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Metoda płatności</span>
                    <span class="detail-value">${order.payment_method_label || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status płatności</span>
                    <span class="detail-value">
                        <span class="payment-badge">${order.payment_status_label}</span>
                    </span>
                </div>
            </div>

            <div class="order-footer">
                <span class="order-amount">${order.total_amount ? parseFloat(order.total_amount).toFixed(2) : (order.amount ? parseFloat(order.amount).toFixed(2) : '0.00')} zł</span>
                <button class="btn-details" onclick="event.stopPropagation(); viewOrderDetails(${order.id})">Szczegóły</button>
            </div>
        </div>
    `).join('');
}

function displayPagination(pagination) {
    const container = document.getElementById('pagination');

    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // Przycisk poprzedniej strony
    if (pagination.page > 1) {
        html += `<button onclick="loadOrders(${pagination.page - 1})">← Poprzednia</button>`;
    }

    // Numery stron
    for (let i = 1; i <= pagination.pages; i++) {
        const activeClass = i === pagination.page ? 'active' : '';
        html += `<button class="${activeClass}" onclick="loadOrders(${i})">${i}</button>`;
    }

    // Przycisk następnej strony
    if (pagination.page < pagination.pages) {
        html += `<button onclick="loadOrders(${pagination.page + 1})">Następna →</button>`;
    }

    container.innerHTML = html;
}

async function viewOrderDetails(orderId) {
    try {
        const response = await fetch(`/orders/get/${orderId}`, {
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (data.success) {
            const order = data.order;
            let itemsHtml = '';

            if (order.items && order.items.length > 0) {
                itemsHtml = order.items.map(item => `
                    <div class="order-item">
                        <span class="item-name">${item.name || 'Artykuł'}</span>
                        <span class="item-qty">x${item.quantity}</span>
                        <span class="item-price">${(item.price * item.quantity).toFixed(2)} zł</span>
                    </div>
                `).join('');
            }

            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-item" style="margin-bottom: 15px;">
                    <span class="detail-label">Restauracja</span>
                    <span class="detail-value">${order.restaurant_name}</span>
                </div>

                <div class="detail-item" style="margin-bottom: 15px;">
                    <span class="detail-label">Status zamówienia</span>
                    <span class="order-status status-${getStatusClass(order.status)}">${order.status_label}</span>
                </div>

                <div class="detail-item" style="margin-bottom: 15px;">
                    <span class="detail-label">Status płatności</span>
                    <span class="payment-badge">${order.payment_status_label}</span>
                </div>

                <div class="detail-item" style="margin-bottom: 15px;">
                    <span class="detail-label">Metoda płatności</span>
                    <span class="detail-value">${order.payment_method_label || '-'}</span>
                </div>

                <div style="margin: 20px 0; padding: 15px 0; border-top: 1px solid #f0f0f0;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">Artykuły</h3>
                    <div class="order-items">
                        ${itemsHtml || '<p style="color: #999;">Brak artykułów</p>'}
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <div>
                        <span class="detail-label">Razem</span>
                        <span class="detail-value" style="font-size: 20px; color: #667eea;">${order.total_amount ? parseFloat(order.total_amount).toFixed(2) : (order.amount ? parseFloat(order.amount).toFixed(2) : '0.00')} zł</span>
                    </div>
                    <div>
                        <span class="detail-label">Data zamówienia</span>
                        <span class="detail-value">${order.created_at_formatted}</span>
                    </div>
                </div>
            `;

            document.getElementById('orderModal').style.display = 'block';
        }
    } catch (error) {
        console.error('Błąd ładowania szczegółów:', error);
    }
}

function closeOrderModal() {
    document.getElementById('orderModal').style.display = 'none';
}

function getStatusClass(status) {
    const classMap = {
        'pending': 'pending',
        'accepted': 'accepted',
        'preparing': 'preparing',
        'ready_for_pickup': 'ready',
        'picked_up': 'ready',
        'delivered': 'delivered',
        'cancelled': 'cancelled'
    };
    return classMap[status] || 'pending';
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
        const cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
        if (cart.length > 0) {
            await fetch('/cart/save', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ cart: cart })
            });
        }
        await fetch('/auth/logout');
        localStorage.removeItem('jellyFoodCart');
        window.location.href = '/';
    } catch (error) {
        console.error('Błąd wylogowania:', error);
    }
}

function updateCartBadge() {
    const cart = JSON.parse(localStorage.getItem('jellyFoodCart')) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = totalItems;
        badge.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

// Zamknij modal po kliknieciu poza nim
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

updateCartBadge();
