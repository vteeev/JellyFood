function togglePassword(event) {
  event.preventDefault();
  const input = document.getElementById('password-input');
  const icon = document.getElementById('password-icon');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.textContent = 'visibility_off';
  } else {
    input.type = 'password';
    icon.textContent = 'visibility';
  }
}

async function syncCartAfterLogin() {
  try {
    const response = await fetch('/cart/get');
    const data = await response.json();
    
    if (data.success && data.data) {
      localStorage.setItem('jellyFoodCart', JSON.stringify(data.data));
    }
  } catch (error) {
    console.error('Błąd synchronizacji koszyka:', error);
  }
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const form = e.target;
  const submitBtn = document.getElementById('submitBtn');
  const errorDiv = document.getElementById('errorMessage');
  
  const email = form.email.value;
  const password = form.password.value;
  
  // Walidacja
  if (!email || !password) {
    errorDiv.textContent = 'Email i hasło są wymagane!';
    errorDiv.style.display = 'block';
    return;
  }
  
  // Wyłączenie przycisku
  submitBtn.disabled = true;
  submitBtn.textContent = 'Logowanie...';
  errorDiv.style.display = 'none';
  
  try {
    const response = await fetch('/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({ email, password }),
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Synchronizuj koszyk przed przekierowaniem
      await syncCartAfterLogin();
      
      // Sprawdź rolę użytkownika i przekieruj odpowiednio
      if (data.user && data.user.role_name === 'admin') {
        window.location.href = '/admin';
      } else if (data.user && data.user.role_name === 'pracownik_restauracji') {
        window.location.href = '/public/views/restaurant-orders-dashboard.html';
      } else {
        window.location.href = '/';
      }
    } else {
      errorDiv.textContent = data.message || 'Błąd podczas logowania!';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    errorDiv.textContent = 'Błąd sieci: ' + error.message;
    errorDiv.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Zaloguj się';
  }
});
