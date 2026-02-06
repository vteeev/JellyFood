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
            loadProfile();

            const authButtons = document.getElementById('authButtons');
            const userProfile = document.getElementById('userProfile');
            const userName = document.getElementById('userName');

            if (userProfile) {
                userProfile.style.display = 'flex';
                const firstName = data.user.full_name.split(' ')[0];
                userName.textContent = firstName;
            }
        } else {
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Błąd sprawdzenia autentykacji:', error);
        window.location.href = '/login';
    }

    updateCartBadge();
});

async function loadProfile() {
    try {
        const response = await fetch(`/profile`, {
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (data.success) {
            const user = data.user;
            const address = data.address;

            // Aktualizuj profil header
            document.getElementById('profileName').textContent = user.full_name;
            document.getElementById('profileEmail').textContent = user.email;
            document.getElementById('profilePhone').textContent = user.phone || 'Nie podano';
            const createdDate = new Date(user.created_at).toLocaleDateString('pl-PL');
            document.getElementById('joinDate').textContent = `Dołączył: ${createdDate}`;

            // Wypełnij formularz profilu
            document.getElementById('fullName').value = user.full_name || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('phone').value = user.phone || '';

            // Wypełnij formularz adresu
            if (address) {
                const streetParts = address.street ? address.street.split('/')[0].trim() : '';
                const apartmentParts = address.street ? address.street.split('/')[1] : '';

                document.getElementById('street').value = streetParts || '';
                document.getElementById('apartment').value = apartmentParts || address.apartment_number || '';
                document.getElementById('city').value = address.city || '';
                document.getElementById('postalCode').value = address.postal_code || '';
                document.getElementById('country').value = address.country || 'Polska';
            }
        } else {
            showError('Nie udało się załadować profilu');
        }
    } catch (error) {
        console.error('Błąd ładowania profilu:', error);
        showError('Błąd podczas ładowania profilu');
    }
}

// Obsługa formularza profilu
document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = {
        full_name: document.getElementById('fullName').value,
        phone: document.getElementById('phone').value
    };

    try {
        const response = await fetch('/profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Profil zaktualizowany pomyślnie');
            currentUser = data.user;
            document.getElementById('profileName').textContent = currentUser.full_name;
            document.getElementById('profilePhone').textContent = currentUser.phone || 'Nie podano';
        } else {
            showError(data.message || 'Błąd podczas aktualizacji profilu');
        }
    } catch (error) {
        console.error('Błąd:', error);
        showError('Błąd podczas aktualizacji profilu');
    }
});

// Obsługa formularza adresu
document.getElementById('addressForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = {
        street: document.getElementById('street').value,
        city: document.getElementById('city').value,
        postal_code: document.getElementById('postalCode').value,
        apartment_number: document.getElementById('apartment').value,
        country: document.getElementById('country').value
    };

    try {
        const response = await fetch('/profile/address', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Adres zaktualizowany pomyślnie');
        } else {
            showError(data.message || 'Błąd podczas aktualizacji adresu');
        }
    } catch (error) {
        console.error('Błąd:', error);
        showError('Błąd podczas aktualizacji adresu');
    }
});

function showSuccess(message) {
    const el = document.getElementById('successMessage');
    el.textContent = message;
    el.style.display = 'block';
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

function showError(message) {
    const el = document.getElementById('errorMessage');
    el.textContent = message;
    el.style.display = 'block';
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
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

updateCartBadge();
