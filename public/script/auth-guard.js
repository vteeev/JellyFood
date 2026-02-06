/**
 * Funkcja sprawdzająca rolę użytkownika i chroniąca strony
 * role: 1 = admin, 2 = customer, 3 = pracownik_restauracji
 */

async function checkUserRole(allowedRoles) {
    try {
        const response = await fetch('/auth/check');
        const data = await response.json();
        
        if (!data.success || !data.user) {
            // Użytkownik nie jest zalogowany
            window.location.href = '/login';
            return null;
        }
        
        const userRole = data.user.role_id;
        
        // Sprawdź czy użytkownik ma dostęp do tej strony
        if (!allowedRoles.includes(userRole)) {
            // Redirectuj na właściwą stronę
            switch(userRole) {
                case 1: // admin
                    window.location.href = '/admin';
                    break;
                case 3: // pracownik restauracji
                    window.location.href = '/public/views/restaurant-orders-dashboard.html';
                    break;
                case 2: // customer
                default:
                    window.location.href = '/';
                    break;
            }
            return null;
        }
        
        return data.user;
    } catch (error) {
        console.error('Błąd sprawdzenia roli:', error);
        window.location.href = '/login';
        return null;
    }
}

/**
 * Chroni stronę admina
 */
async function protectAdminPage() {
    return checkUserRole([1]); // Tylko admin (role_id = 1)
}

/**
 * Chroni stronę pracownika restauracji
 */
async function protectRestaurantStaffPage() {
    return checkUserRole([3]); // Tylko pracownik restauracji (role_id = 3)
}

/**
 * Chroni stronę klienta
 */
async function protectCustomerPage() {
    return checkUserRole([2]); // Tylko customer (role_id = 2)
}

/**
 * Chroni strony które mogą być dostępne dla zalogowanych użytkowników bez względu na rolę
 */
async function protectLoggedInPage() {
    return checkUserRole([1, 2, 3]); // Wszyscy zalogowani
}
