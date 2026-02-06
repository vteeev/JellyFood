document.addEventListener('DOMContentLoaded', async () => {
    await loadUserInfo();
    await loadRestaurants();
});

async function loadUserInfo() {
    try {
        const response = await fetch('/auth/check');
        const data = await response.json();

        if (!data.success || !data.user || data.user.role_id !== 1) {
            window.location.href = '/login';
            return;
        }

        document.getElementById('userProfile').style.display = 'flex';
        const firstName = data.user.full_name.split(' ')[0];
        document.getElementById('userName').textContent = firstName;
        
        await loadCities();
        await loadKitchenTypes();
    } catch (error) {
        console.error('Błąd sprawdzenia autentykacji:', error);
        window.location.href = '/login';
    }
}

async function loadCities() {
    try {
        const response = await fetch('/admin/cities');
        const data = await response.json();

        if (data.success && data.cities) {
            const citySelect = document.getElementById('rest_city');
            data.cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Błąd ładowania miast:', error);
    }
}

async function loadKitchenTypes() {
    try {
        const response = await fetch('/admin/kitchen-types');
        const data = await response.json();

        if (data.success && data.kitchen_types) {
            window.kitchenTypes = data.kitchen_types;
            displayKitchenTypesInForm();
        }
    } catch (error) {
        console.error('Błąd ładowania typów kuchni:', error);
    }
}

function displayKitchenTypesInForm() {
    const container = document.getElementById('kitchenTypesContainer');
    if (!container || !window.kitchenTypes) return;

    container.innerHTML = window.kitchenTypes.map(type => `
        <label class="kitchen-type-checkbox">
            <input type="checkbox" name="kitchen_type" value="${type.id}"/>
            <span>${type.name}</span>
        </label>
    `).join('');
}

async function loadRestaurants() {
    try {
        const response = await fetch('/admin/restaurants');
        const data = await response.json();

        if (data.success && data.restaurants) {
            displayRestaurants(data.restaurants);
        } else {
            document.getElementById('restaurantsGrid').innerHTML = `
                <div class="no-data">
                    <div class="no-data-icon">❌</div>
                    <p>${data.message || 'Błąd ładowania restauracji'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Błąd ładowania restauracji:', error);
        document.getElementById('restaurantsGrid').innerHTML = `
            <div class="no-data">
                <div class="no-data-icon">❌</div>
                <p>Błąd połączenia z serwerem</p>
            </div>
        `;
    }
}

function displayRestaurants(restaurants) {
    if (restaurants.length === 0) {
        document.getElementById('restaurantsGrid').innerHTML = `
            <div class="no-data" style="grid-column: 1/-1;">
                <div class="no-data-icon">🍽️</div>
                <p>Brak restauracji</p>
            </div>
        `;
        return;
    }

    document.getElementById('restaurantsGrid').innerHTML = restaurants.map(rest => `
        <div class="restaurant-card">
            <h3>${rest.name}</h3>
            <p><strong>Miasto:</strong> ${rest.city}</p>
            <p><strong>Ulica:</strong> ${rest.street}, ${rest.building_number}</p>
            <p><strong>Telefon:</strong> ${rest.phone || 'Brak'}</p>
            <div class="restaurant-card-actions">
                <button class="btn-small btn-edit" onclick="openRestaurantModal(${rest.id})">Edytuj</button>
                <button class="btn-small btn-menu" onclick="openMenuManager(${rest.id})">Menu</button>
                <button class="btn-small btn-delete" onclick="deleteRestaurant(${rest.id})">Usuń</button>
            </div>
        </div>
    `).join('');
}

async function openRestaurantModal(restaurantId) {
    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}`);
        const data = await response.json();

        if (data.success && data.restaurant) {
            const rest = data.restaurant;
            const content = document.getElementById('restaurantModalContent');
            content.innerHTML = `
                <div class="modal-header">Edytuj restaurację: ${rest.name}</div>
                <form onsubmit="handleEditRestaurant(event, ${restaurantId})">
                    <div class="form-group">
                        <label>Nazwa</label>
                        <input type="text" value="${rest.name}" id="modal_name" required/>
                    </div>
                    <div class="form-group">
                        <label>Opis</label>
                        <textarea id="modal_desc">${rest.description || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="tel" value="${rest.phone || ''}" id="modal_phone"/>
                    </div>
                    <div class="form-group">
                        <label>Ulica</label>
                        <input type="text" value="${rest.street}" id="modal_street" required/>
                    </div>
                    <div class="form-group">
                        <label>Numer budynku</label>
                        <input type="text" value="${rest.building_number}" id="modal_building" required/>
                    </div>
                    <div class="form-group">
                        <label>Numer mieszkania</label>
                        <input type="text" value="${rest.apartment_number || ''}" id="modal_apartment"/>
                    </div>
                    <div class="form-group">
                        <label>Miasto</label>
                        <select id="modal_city" required>
                            <!-- Wypełnione przez loadCities -->
                        </select>
                        <input type="text" id="modal_city_custom" placeholder="Lub wpisz nowe miasto" style="margin-top: 8px;"/>
                    </div>
                    <div class="form-group">
                        <label>Kod pocztowy</label>
                        <input type="text" value="${rest.postal_code}" id="modal_postal" required/>
                    </div>
                    <div class="form-group">
                        <label>Zdjęcie restauracji</label>
                        <input type="file" id="modal_image_file" accept="image/*"/>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Maksymalny rozmiar: 5MB. Dozwolone formaty: JPG, PNG, GIF, WebP</small>
                        ${rest.image_url ? `<small style="color: #666; font-size: 12px;">Aktualne: <a href="${rest.image_url}" target="_blank">zobacz zdjęcie</a></small>` : ''}
                        <input type="hidden" id="modal_image_current" value="${rest.image_url || ''}" />
                    </div>
                    <div class="form-group">
                        <label>Kategorie kuchni</label>
                        <div id="modal_kitchenTypesContainer" class="kitchen-types-grid">
                            <!-- Wypełnione dynamicznie -->
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Zapisz zmiany</button>
                        <button type="button" class="btn-cancel" onclick="closeRestaurantModal()">Anuluj</button>
                    </div>
                </form>
                
                <div class="staff-list">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #333;">Pracownicy restauracji</h3>
                    
                    <div class="add-staff-form">
                        <form onsubmit="handleAddStaff(event, ${restaurantId})">
                            <div class="form-group">
                                <input type="email" id="staff_email" placeholder="Email pracownika" required/>
                            </div>
                            <div class="form-group">
                                <input type="text" id="staff_name" placeholder="Imię i nazwisko" required/>
                            </div>
                            <div class="form-group">
                                <input type="tel" id="staff_phone" placeholder="Telefon (opcjonalnie)"/>
                            </div>
                            <div class="form-group">
                                <input type="password" id="staff_password" placeholder="Hasło" required/>
                            </div>
                            <button type="submit" class="btn-add-staff" style="width: 100%;">Dodaj pracownika</button>
                        </form>
                    </div>
                    
                    <div id="staffList_${restaurantId}">
                        <!-- Wypełnione przez loadRestaurantStaff -->
                    </div>
                </div>
            `;
            
            // Załaduj pracowników
            await loadRestaurantStaff(restaurantId);
            
            // Wypełnij select miast
            const citiesResponse = await fetch('/admin/cities');
            const citiesData = await citiesResponse.json();
            if (citiesData.success && citiesData.cities) {
                const citySelect = document.getElementById('modal_city');
                citiesData.cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    if (city === rest.city) {
                        option.selected = true;
                    }
                    citySelect.appendChild(option);
                });
            }

            // Wypełnij kitchen types
            if (window.kitchenTypes) {
                const container = document.getElementById('modal_kitchenTypesContainer');
                container.innerHTML = window.kitchenTypes.map(type => {
                    const isChecked = rest.kitchen_types && rest.kitchen_types.includes(type.id.toString()) || rest.kitchen_types && rest.kitchen_types.includes(type.id);
                    return `
                        <label class="kitchen-type-checkbox">
                            <input type="checkbox" name="kitchen_type" value="${type.id}" ${isChecked ? 'checked' : ''}/>
                            <span>${type.name}</span>
                        </label>
                    `;
                }).join('');
            }
            
            document.getElementById('restaurantModal').classList.add('active');
        }
    } catch (error) {
        console.error('Błąd ładowania restauracji:', error);
        alert('Błąd ładowania restauracji');
    }
}

async function openMenuManager(restaurantId) {
    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}/menu`);
        const data = await response.json();

        if (data.success) {
            const rest = data.restaurant;
            const categories = data.categories || [];
            const content = document.getElementById('restaurantModalContent');

            let menuHtml = `
                <div class="modal-header">Menu: ${rest.name}</div>
                <div class="form-wrapper">
                    <h3>Dodaj danie</h3>
                    <form onsubmit="handleAddMenuItem(event, ${restaurantId})">
                        <div class="form-group">
                            <label>Kategoria *</label>
                            <div id="categoryContainer">
                                <select id="menu_category">
                                    <option value="">-- Wybierz kategorię --</option>
                                    ${categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                                </select>
                                <small style="color: #666; margin-top: 5px; display: block;">
                                    Lub <button type="button" onclick="toggleNewCategoryForm()" class="link-btn">utwórz nową kategorię</button>
                                </small>
                            </div>
                            <div id="newCategoryForm" style="display: none; margin-top: 12px; padding: 12px; background: #f9f9f9; border-radius: 6px;">
                                <input type="text" id="newCategoryName" placeholder="Nazwa nowej kategorii" maxlength="100"/>
                                <div style="display: flex; gap: 8px; margin-top: 8px;">
                                    <button type="button" onclick="createNewCategory(${restaurantId})" class="btn-primary" style="flex: 1;">Utwórz</button>
                                    <button type="button" onclick="toggleNewCategoryForm()" style="flex: 1; background: #ddd; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Anuluj</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nazwa dania *</label>
                            <input type="text" id="menu_name" required/>
                        </div>
                        <div class="form-group">
                            <label>Opis</label>
                            <textarea id="menu_desc"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Cena *</label>
                            <input type="number" step="0.01" id="menu_price" required/>
                        </div>
                        <div class="form-group">
                            <label>Zdjęcie dania</label>
                            <input type="file" id="menu_image" accept="image/*"/>
                            <small style="color: #666; font-size: 12px;">Maksymalny rozmiar: 5MB. Dozwolone formaty: JPG, PNG, GIF, WebP</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Dodaj danie</button>
                        </div>
                    </form>
                </div>

                <div class="menu-items-list">
                    <h3>Dania w menu</h3>
                    ${categories.length === 0 ? '<p style="color: #999;">Brak kategorii w menu</p>' : ''}
                    ${categories.map(cat => `
                        <div class="menu-category">
                            <div class="category-title">${cat.name}</div>
                            ${(cat.items || []).map(item => `
                                <div class="menu-item">
                                    <div class="item-info">
                                        <div class="item-name">${item.name}</div>
                                        <div class="item-price">${item.price.toFixed(2)} zł</div>
                                    </div>
                                    <div class="item-actions">
                                        <button class="btn-tiny btn-delete-item" onclick="deleteMenuItem(${item.id}, ${restaurantId})">Usuń</button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `).join('')}
                </div>
            `;

            content.innerHTML = menuHtml;
            document.getElementById('restaurantModal').classList.add('active');
        }
    } catch (error) {
        console.error('Błąd ładowania menu:', error);
        alert('Błąd ładowania menu');
    }
}

function toggleNewCategoryForm() {
    const form = document.getElementById('newCategoryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('newCategoryName').focus();
    }
}

async function createNewCategory(restaurantId) {
    const categoryName = document.getElementById('newCategoryName').value.trim();
    
    if (!categoryName) {
        alert('Wpisz nazwę kategorii');
        return;
    }

    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}/category/add`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ name: categoryName })
        });

        const result = await response.json();

        if (result.success) {
            // Odśwież menu menedżer
            await openMenuManager(restaurantId);
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się utworzyć kategorii'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

async function handleAddRestaurant(event) {
    event.preventDefault();

    let imageUrl = '';
    const imageFile = document.getElementById('rest_image').files[0];
    
    // Jeśli wybrano zdjęcie, najpierw je prześlij
    if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);

        try {
            const uploadResponse = await fetch('/admin/upload-image?type=restaurant', {
                method: 'POST',
                body: formData
            });
            const uploadResult = await uploadResponse.json();
            
            if (uploadResult.success) {
                imageUrl = uploadResult.image_url;
            } else {
                alert('Błąd uploadu zdjęcia: ' + uploadResult.message);
                return;
            }
        } catch (error) {
            console.error('Błąd uploadu:', error);
            alert('Błąd przesyłania zdjęcia');
            return;
        }
    }

    // Użyj customowego miasta jeśli podano
    let city = document.getElementById('rest_city').value;
    const customCity = document.getElementById('rest_city_custom').value.trim();
    if (customCity) {
        city = customCity;
    }

    // Zbierz zaznaczone kitchen types
    const kitchenTypes = Array.from(document.querySelectorAll('#kitchenTypesContainer input[type="checkbox"]:checked'))
        .map(cb => parseInt(cb.value));

    const data = {
        name: document.getElementById('rest_name').value,
        description: document.getElementById('rest_desc').value,
        phone: document.getElementById('rest_phone').value,
        street: document.getElementById('rest_street').value,
        building_number: document.getElementById('rest_building').value,
        apartment_number: document.getElementById('rest_apartment').value,
        city: city,
        postal_code: document.getElementById('rest_postal').value,
        image_url: imageUrl,
        kitchen_types: kitchenTypes
    };

    try {
        const response = await fetch('/admin/restaurant/add', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Restauracja została dodana');
            document.getElementById('restaurantForm').reset();
            switchTab('restaurants');
            await loadRestaurants();
            await loadCities(); // Odśwież listę miast
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się dodać restauracji'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

async function handleEditRestaurant(event, restaurantId) {
    event.preventDefault();

    let imageUrl = document.getElementById('modal_image_current').value;
    const imageFile = document.getElementById('modal_image_file').files[0];
    
    // Jeśli wybrano nowe zdjęcie, najpierw je prześlij
    if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);

        try {
            const uploadResponse = await fetch('/admin/upload-image?type=restaurant', {
                method: 'POST',
                body: formData
            });
            const uploadResult = await uploadResponse.json();
            
            if (uploadResult.success) {
                imageUrl = uploadResult.image_url;
            } else {
                alert('Błąd uploadu zdjęcia: ' + uploadResult.message);
                return;
            }
        } catch (error) {
            console.error('Błąd uploadu:', error);
            alert('Błąd przesyłania zdjęcia');
            return;
        }
    }

    // Użyj customowego miasta jeśli podano
    let city = document.getElementById('modal_city').value;
    const customCity = document.getElementById('modal_city_custom').value.trim();
    if (customCity) {
        city = customCity;
    }

    // Zbierz zaznaczone kitchen types
    const kitchenTypes = Array.from(document.querySelectorAll('#modal_kitchenTypesContainer input[type="checkbox"]:checked'))
        .map(cb => parseInt(cb.value));

    const data = {
        name: document.getElementById('modal_name').value,
        description: document.getElementById('modal_desc').value,
        phone: document.getElementById('modal_phone').value,
        street: document.getElementById('modal_street').value,
        building_number: document.getElementById('modal_building').value,
        apartment_number: document.getElementById('modal_apartment').value,
        city: city,
        postal_code: document.getElementById('modal_postal').value,
        image_url: imageUrl,
        kitchen_types: kitchenTypes
    };

    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        console.log('Response status:', response.status);
        const responseText = await response.text();
        console.log('Response text:', responseText);

        if (!response.ok) {
            alert(`Błąd HTTP ${response.status}: ${responseText.substring(0, 200)}`);
            return;
        }

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', responseText);
            alert('Błąd parsowania odpowiedzi. Sprawdź konsolę.');
            return;
        }

        console.log('Result:', result);

        if (result.success) {
            alert('Restauracja została zaktualizowana');
            closeRestaurantModal();
            await loadRestaurants();
            await loadCities(); // Odśwież listę miast
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się zaktualizować restauracji'));
        }
    } catch (error) {
        console.error('Błąd połączenia:', error);
        alert('Błąd połączenia: ' + error.message);
    }
}

async function deleteRestaurant(restaurantId) {
    if (!confirm('Czy na pewno chcesz usunąć tę restaurację? Operacja jest nieodwracalna.')) return;

    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            alert('Restauracja została usunięta');
            await loadRestaurants();
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się usunąć restauracji'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

async function handleAddMenuItem(event, restaurantId) {
    event.preventDefault();

    let imageUrl = '';
    const imageFile = document.getElementById('menu_image').files[0];
    
    // Jeśli wybrano zdjęcie, najpierw je prześlij
    if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);

        try {
            const uploadResponse = await fetch('/admin/upload-image?type=menu', {
                method: 'POST',
                body: formData
            });
            const uploadResult = await uploadResponse.json();
            
            if (uploadResult.success) {
                imageUrl = uploadResult.image_url;
            } else {
                alert('Błąd uploadu zdjęcia: ' + uploadResult.message);
                return;
            }
        } catch (error) {
            console.error('Błąd uploadu:', error);
            alert('Błąd przesyłania zdjęcia');
            return;
        }
    }

    const data = {
        category_id: parseInt(document.getElementById('menu_category').value),
        name: document.getElementById('menu_name').value,
        description: document.getElementById('menu_desc').value,
        price: parseFloat(document.getElementById('menu_price').value),
        image: imageUrl
    };

    try {
        const response = await fetch(`/admin/restaurant/${restaurantId}/menu/add`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Danie zostało dodane');
            await openMenuManager(restaurantId);
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się dodać dania'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

async function deleteMenuItem(menuItemId, restaurantId) {
    if (!confirm('Czy na pewno chcesz usunąć to danie?')) return;

    try {
        const response = await fetch(`/admin/menu-item/${menuItemId}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            alert('Danie zostało usunięte');
            await openMenuManager(restaurantId);
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się usunąć dania'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tab).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
}

function closeRestaurantModal() {
    document.getElementById('restaurantModal').classList.remove('active');
}

async function loadRestaurantStaff(restaurantId) {
    try {
        const response = await fetch(`/admin/staff/${restaurantId}`);
        const data = await response.json();

        const staffContainer = document.getElementById(`staffList_${restaurantId}`);
        if (!staffContainer) return;

        if (data.success && data.staff && data.staff.length > 0) {
            let html = '';
            data.staff.forEach(member => {
                html += `
                    <div class="staff-item">
                        <div class="staff-info">
                            <div class="staff-name">${member.full_name}</div>
                            <div class="staff-email">${member.email}</div>
                            ${member.phone ? `<div class="staff-phone">${member.phone}</div>` : ''}
                        </div>
                        <div class="staff-actions">
                            <button class="btn-delete-staff" onclick="deleteStaff(${restaurantId}, ${member.id})">
                                Usuń
                            </button>
                        </div>
                    </div>
                `;
            });
            staffContainer.innerHTML = html;
        } else {
            staffContainer.innerHTML = '<p style="color: #999; font-size: 13px;">Brak pracowników</p>';
        }
    } catch (error) {
        console.error('Błąd ładowania pracowników:', error);
    }
}

async function handleAddStaff(event, restaurantId) {
    event.preventDefault();

    const email = document.getElementById('staff_email').value;
    const fullName = document.getElementById('staff_name').value;
    const phone = document.getElementById('staff_phone').value;
    const password = document.getElementById('staff_password').value;

    if (!email || !fullName || !password) {
        alert('Proszę wypełnić wszystkie wymagane pola');
        return;
    }

    try {
        const response = await fetch(`/admin/staff/${restaurantId}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                email,
                full_name: fullName,
                phone: phone || null,
                password
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Pracownik został dodany');
            document.getElementById('staff_email').value = '';
            document.getElementById('staff_name').value = '';
            document.getElementById('staff_phone').value = '';
            document.getElementById('staff_password').value = '';
            await loadRestaurantStaff(restaurantId);
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się dodać pracownika'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
    }
}

async function deleteStaff(restaurantId, userId) {
    if (!confirm('Czy na pewno chcesz usunąć tego pracownika?')) return;

    try {
        const response = await fetch(`/admin/staff/${restaurantId}/${userId}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            alert('Pracownik został usunięty');
            await loadRestaurantStaff(restaurantId);
        } else {
            alert('Błąd: ' + (result.message || 'Nie udało się usunąć pracownika'));
        }
    } catch (error) {
        console.error('Błąd:', error);
        alert('Błąd połączenia');
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
