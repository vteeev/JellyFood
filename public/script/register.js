function togglePassword(event, id) {
  event.preventDefault();
  const input = document.getElementById(id);
  const btn = event.target.closest('.password-toggle-btn');
  const icon = btn.querySelector('.material-symbols-outlined');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.textContent = 'visibility_off';
  } else {
    input.type = 'password';
    icon.textContent = 'visibility';
  }
}

document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const form = e.target;
  const submitBtn = document.getElementById('submitBtn');
  const errorDiv = document.getElementById('errorMessage');
  
  // Zbiór danych
  const formData = {
    full_name: form.full_name.value,
    email: form.email.value,
    phone: form.phone.value || null,
    password: form.password.value,
    password_confirm: form.password_confirm.value,
    street: form.street.value,
    apartment_number: form.apartment_number.value || null,
    city: form.city.value,
    postal_code: form.postal_code.value,
    country: 'Polska'
  };
  
  // Walidacja hasła
  if (formData.password !== formData.password_confirm) {
    errorDiv.textContent = 'Hasła się nie zgadzają!';
    errorDiv.style.display = 'block';
    return;
  }
  
  if (formData.password.length < 8) {
    errorDiv.textContent = 'Hasło musi mieć co najmniej 8 znaków!';
    errorDiv.style.display = 'block';
    return;
  }
  
  if (!form.terms.checked) {
    errorDiv.textContent = 'Musisz zaakceptować regulamin i politykę prywatności!';
    errorDiv.style.display = 'block';
    return;
  }
  
  // Wyłączenie przycisku
  submitBtn.disabled = true;
  submitBtn.textContent = 'Rejestrowanie...';
  errorDiv.style.display = 'none';
  
  try {
    const response = await fetch('/auth/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams(formData),
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Redirect do logowania
      alert('Rejestracja pomyślna! Zaloguj się teraz.');
      window.location.href = '/login';
    } else {
      errorDiv.textContent = data.message || 'Błąd podczas rejestracji!';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    errorDiv.textContent = 'Błąd sieci: ' + error.message;
    errorDiv.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Zarejestruj się';
  }
});
