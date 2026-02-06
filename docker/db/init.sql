-- ============================
-- CZYSZCZENIE STAREJ BAZY
-- ============================
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS deliveries CASCADE;
DROP TABLE IF EXISTS couriers CASCADE;
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS menu_items CASCADE;
DROP TABLE IF EXISTS menu_categories CASCADE;
DROP TABLE IF EXISTS restaurant_kitchen_types CASCADE;
DROP TABLE IF EXISTS restaurant_opening_hours CASCADE;
DROP TABLE IF EXISTS restaurant_system CASCADE;
DROP TABLE IF EXISTS restaurants CASCADE;
DROP TABLE IF EXISTS user_cart CASCADE;
DROP TABLE IF EXISTS user_addresses CASCADE;
DROP TABLE IF EXISTS kitchen_types CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

-- ============================
-- 1. TABELA RÓL
-- ============================
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL CHECK (name IN (
        'admin', 'klient', 'pracownik_restauracji', 'dostawca'
    ))
);

-- Wstawienie domyślnych ról
INSERT INTO roles (name) VALUES ('admin'), ('klient'), ('pracownik_restauracji'), ('dostawca')
ON CONFLICT DO NOTHING;

-- ============================
-- 2. UŻYTKOWNICY
-- ============================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    role_id INT REFERENCES roles(id),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- ============================
-- 3. ADRESY UŻYTKOWNIKÓW
-- ============================
CREATE TABLE IF NOT EXISTS user_addresses (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    street VARCHAR(255) NOT NULL,
    building_number VARCHAR(20) NOT NULL,
    apartment_number VARCHAR(20),
    city VARCHAR(255) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL
);

-- ============================
-- 3.5 KOSZYKI UŻYTKOWNIKÓW
-- ============================
CREATE TABLE IF NOT EXISTS user_cart (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    cart_data JSONB NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_cart_user_id ON user_cart(user_id);

-- ============================
-- 4. RESTAURACJE
-- ============================
CREATE TABLE IF NOT EXISTS restaurants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_url TEXT,
    phone VARCHAR(30),
    street VARCHAR(255) NOT NULL,
    building_number VARCHAR(20) NOT NULL,
    apartment_number VARCHAR(20),
    city VARCHAR(255) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    is_active BOOLEAN DEFAULT TRUE
);

-- ============================
-- 5. GODZINY OTWARCIA RESTAURACJI
-- ============================
CREATE TABLE IF NOT EXISTS restaurant_opening_hours (
    id SERIAL PRIMARY KEY,
    restaurant_id INT REFERENCES restaurants(id) ON DELETE CASCADE,
    weekday SMALLINT NOT NULL CHECK (weekday BETWEEN 1 AND 7),
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    UNIQUE(restaurant_id, weekday)
);

-- ============================
-- 6. PRACOWNICY SYSTEMU RESTAURACJI
-- ============================
CREATE TABLE IF NOT EXISTS restaurant_system (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    restaurant_id INT REFERENCES restaurants(id) ON DELETE CASCADE
);

-- ============================
-- 7. MENU RESTAURACJI
-- ============================
CREATE TABLE IF NOT EXISTS menu_categories (
    id SERIAL PRIMARY KEY,
    restaurant_id INT REFERENCES restaurants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS menu_items (
    id SERIAL PRIMARY KEY,
    category_id INT REFERENCES menu_categories(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price NUMERIC(10, 2) NOT NULL,
    image TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- ============================
-- 8. ZAMÓWIENIA
-- ============================
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES users(id),
    restaurant_id INT REFERENCES restaurants(id),
    address_id INT REFERENCES user_addresses(id),
    status VARCHAR(50) NOT NULL CHECK (status IN (
        'pending', 'accepted', 'preparing', 'ready_for_pickup',
        'picked_up', 'delivered', 'cancelled'
    )),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INT REFERENCES orders(id) ON DELETE CASCADE,
    menu_item_id INT REFERENCES menu_items(id),
    quantity INT NOT NULL CHECK (quantity > 0),
    price NUMERIC(10, 2) NOT NULL
);

-- ============================
-- 9. DOSTAWCY
-- ============================
CREATE TABLE IF NOT EXISTS couriers (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    vehicle_type VARCHAR(50) CHECK (vehicle_type IN ('rower', 'skuter', 'samochód')),
    is_available BOOLEAN DEFAULT TRUE
);

-- ============================
-- 10. DOSTAWY
-- ============================
CREATE TABLE IF NOT EXISTS deliveries (
    id SERIAL PRIMARY KEY,
    order_id INT UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    courier_id INT REFERENCES couriers(id),
    pickup_time TIMESTAMP,
    delivery_time TIMESTAMP,
    status VARCHAR(50) NOT NULL CHECK (status IN (
        'waiting_for_pickup', 'on_the_way', 'delivered', 'cancelled'
    ))
);

-- ============================
-- 11. PŁATNOŚCI
-- ============================
CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    order_id INT UNIQUE REFERENCES orders(id),
    amount NUMERIC(10, 2) NOT NULL,
    payment_method VARCHAR(50) CHECK (payment_method IN ('card', 'blik', 'cash')),
    status VARCHAR(50) CHECK (status IN ('pending', 'paid', 'failed')),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================
-- 12. RECENZJE
-- ============================
CREATE TABLE IF NOT EXISTS reviews (
    id SERIAL PRIMARY KEY,
    order_id INT UNIQUE REFERENCES orders(id),
    customer_id INT REFERENCES users(id),
    restaurant_id INT REFERENCES restaurants(id),
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================
-- 13. ULUBIONE RESTAURACJE
-- ============================
CREATE TABLE IF NOT EXISTS favorites (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    restaurant_id INT REFERENCES restaurants(id) ON DELETE CASCADE,
    UNIQUE(user_id, restaurant_id)
);

-- ============================
-- 14. TYPY KUCHNI
-- ============================
CREATE TABLE IF NOT EXISTS kitchen_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    image_url TEXT
);

-- Predefiniowane typy kuchni
INSERT INTO kitchen_types (name, image_url) VALUES
    ('FastFood', 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=800&q=80'),
    ('Pizza', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&q=80'),
    ('Burger', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&q=80'),
    ('Azjatyckie', 'https://images.unsplash.com/photo-1617093727343-374698b1b08d?w=800&q=80'),
    ('Sushi', 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=800&q=80'),
    ('Włoskie', 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&q=80'),
    ('Polskie', 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=800&q=80'),
    ('Wietnamskie', 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=800&q=80'),
    ('Chińskie', 'https://images.unsplash.com/photo-1525755662778-989d0524087e?w=800&q=80'),
    ('Japońskie', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=800&q=80'),
    ('Ryby', 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?w=800&q=80'),
    ('Tajskie', 'https://images.unsplash.com/photo-1562565652-a0d8f0c59eb4?w=800&q=80'),
    ('Grill', 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=80')
ON CONFLICT DO NOTHING;

-- ============================
-- 15. POWIĄZANIE RESTAURACJI Z TYPAMI KUHNI
-- (wiele do wielu)
-- ============================
CREATE TABLE IF NOT EXISTS restaurant_kitchen_types (
    id SERIAL PRIMARY KEY,
    restaurant_id INT REFERENCES restaurants(id) ON DELETE CASCADE,
    kitchen_type_id INT REFERENCES kitchen_types(id) ON DELETE CASCADE,
    UNIQUE(restaurant_id, kitchen_type_id)
);

-- ============================
-- TESTOWE DANE
-- ============================

-- RESTAURACJE
INSERT INTO restaurants (name, description, image_url, phone, street, building_number, city, postal_code, is_active)
VALUES
    -- Warszawa - 10 restauracji
    ('Burger King Warszawa', 'Najlepsze burgery w mieście, świeże składniki', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&q=80', '2211234567', 'ul. Marszałkowska', '50', 'Warszawa', '00-001', true),
    ('La Dolce Vita', 'Autentyczna włoska kuchnia - makaron i pizza pieczona w drewnie', 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&q=80', '2211234568', 'ul. Nowy Świat', '35', 'Warszawa', '00-002', true),
    ('Tokyo Sushi Bar', 'Świeże sushi i azjatyckie potrawy', 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=800&q=80', '2211234569', 'ul. Złota', '44', 'Warszawa', '00-003', true),
    ('Dragon Palace', 'Chińska kuchnia - chow mein, fried rice i wiele więcej', 'https://images.unsplash.com/photo-1525755662778-989d0524087e?w=800&q=80', '2211234570', 'Rynek Starego Miasta', '12', 'Warszawa', '00-004', true),
    ('Polska Kuchnia', 'Tradycyjne polskie potrawy - bigos, żurek, pierogi', 'https://images.unsplash.com/photo-1625937286074-9ca519d5d9df?w=800&q=80', '2211234571', 'ul. Krakowskie Przedmieście', '15', 'Warszawa', '00-005', true),
    ('Grill Master', 'Mięsa grillowane na drzewnie, szaszłyki i kebaby', 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=80', '2211234572', 'ul. Mokotowska', '30', 'Warszawa', '00-006', true),
    ('Pizzeria Napoli', 'Włoskie pizze z Neapolu - tradycyjne receptury', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800&q=80', '2211234573', 'ul. Piusa XI', '25', 'Warszawa', '00-007', true),
    ('Pho Vietnam', 'Wietnamska zupa pho i wietnamskie dania', 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=800&q=80', '2211234574', 'ul. Rutkowskiego', '20', 'Warszawa', '00-008', true),
    ('Sushi Paradise', 'Premium sushi rolls i sashimi', 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=800&q=80', '2211234575', 'al. Jerozolimskie', '65', 'Warszawa', '00-009', true),
    ('Pasta Fresca', 'Świeżo robiona pasta, risotto i włoskie specjały', 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=800&q=80', '2211234576', 'ul. Chmielna', '28', 'Warszawa', '00-010', true),
    
    -- Kraków
    ('Krakowski Smak', 'Tradycyjna kuchnia małopolska', 'https://images.unsplash.com/photo-1625937286074-9ca519d5d9df?w=800&q=80', '123111111', 'ul. Floriańska', '12', 'Kraków', '31-019', true),
    ('Wawel Sushi', 'Najlepsze sushi w Krakowie', 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=800&q=80', '123222222', 'ul. Starowiślna', '18', 'Kraków', '31-038', true),
    ('Kazimierz Pizza', 'Pizza w sercu Kazimierza', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=800&q=80', '123333333', 'ul. Szeroka', '5', 'Kraków', '31-053', true),
    
    -- Rzeszów
    ('Podkarpackie Smaki', 'Regionalna kuchnia podkarpacka', 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=80', '178111111', 'ul. 3 Maja', '14', 'Rzeszów', '35-030', true),
    ('Azja w Rzeszowie', 'Kuchnia azjatycka', 'https://images.unsplash.com/photo-1617093727343-374698b1b08d?w=800&q=80', '178222222', 'ul. Mickiewicza', '20', 'Rzeszów', '35-064', true),
    
    -- Lublin
    ('Lubelska Pizzeria', 'Pizze na cienkim cieście', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800&q=80', '814111111', 'ul. Krakowskie Przedmieście', '30', 'Lublin', '20-002', true),
    ('Burger Lublin', 'Burgery i fastfood', 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&q=80', '814222222', 'ul. Lipowa', '15', 'Lublin', '20-020', true),
    
    -- Wrocław
    ('Wrocławski Grill', 'Mięsa z grilla', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=800&q=80', '713111111', 'Rynek', '10', 'Wrocław', '50-106', true),
    ('Sushi Wrocław', 'Japońska kuchnia', 'https://images.unsplash.com/photo-1564489563601-c53cfc451e93?w=800&q=80', '713222222', 'ul. Świdnicka', '25', 'Wrocław', '50-066', true),
    
    -- Szczecin
    ('Morskie Smaki', 'Ryby i owoce morza', 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?w=800&q=80', '914111111', 'al. Niepodległości', '40', 'Szczecin', '70-404', true),
    ('Pizza Szczecin', 'Włoska pizza', 'https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=800&q=80', '914222222', 'ul. Bogusława', '12', 'Szczecin', '70-440', true),
    
    -- Poznań
    ('Poznańska Pyra', 'Kuchnia wielkopolska', 'https://images.unsplash.com/photo-1606787620819-8bdf0c44c293?w=800&q=80', '618111111', 'Stary Rynek', '5', 'Poznań', '61-772', true),
    ('Thai Garden Poznań', 'Tajska kuchnia', 'https://images.unsplash.com/photo-1562565652-a0d8f0c59eb4?w=800&q=80', '618222222', 'ul. Święty Marcin', '30', 'Poznań', '61-808', true),
    
    -- Gdańsk
    ('Gdańskie Pierogi', 'Pierogi w różnych smakach', 'https://images.unsplash.com/photo-1618449840665-9ed506d73a34?w=800&q=80', '583111111', 'ul. Długa', '20', 'Gdańsk', '80-827', true),
    ('Bałtycka Pizza', 'Pizza nad morzem', 'https://images.unsplash.com/photo-1590534247854-e973c54adf4f?w=800&q=80', '583222222', 'ul. Szeroka', '8', 'Gdańsk', '80-835', true),
    
    -- Tomaszów Lubelski
    ('Tomaszowskie Jadło', 'Domowa kuchnia polska', 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=800&q=80', '846111111', 'ul. Lwowska', '50', 'Tomaszów Lubelski', '22-600', true),
    ('Burger Tomaszów', 'Szybkie burgery', 'https://images.unsplash.com/photo-1586816001966-79b736744398?w=800&q=80', '846222222', 'Rynek', '3', 'Tomaszów Lubelski', '22-600', true)
ON CONFLICT DO NOTHING;

-- POWIĄZANIA RESTAURACJI Z TYPAMI KUHNI
INSERT INTO restaurant_kitchen_types (restaurant_id, kitchen_type_id)
SELECT r.id, k.id FROM restaurants r, kitchen_types k
WHERE (r.name = 'Burger King Warszawa' AND k.name = 'Burger')
   OR (r.name = 'La Dolce Vita' AND k.name IN ('Włoskie', 'Pizza'))
   OR (r.name = 'Tokyo Sushi Bar' AND k.name IN ('Azjatyckie', 'Sushi'))
   OR (r.name = 'Dragon Palace' AND k.name = 'Chińskie')
   OR (r.name = 'Polska Kuchnia' AND k.name = 'Polskie')
   OR (r.name = 'Grill Master' AND k.name = 'Grill')
   OR (r.name = 'Pizzeria Napoli' AND k.name IN ('Pizza', 'Włoskie'))
   OR (r.name = 'Pho Vietnam' AND k.name = 'Wietnamskie')
   OR (r.name = 'Sushi Paradise' AND k.name IN ('Japońskie', 'Sushi'))
   OR (r.name = 'Pasta Fresca' AND k.name = 'Włoskie')
   OR (r.name = 'Krakowski Smak' AND k.name = 'Polskie')
   OR (r.name = 'Wawel Sushi' AND k.name IN ('Japońskie', 'Sushi'))
   OR (r.name = 'Kazimierz Pizza' AND k.name = 'Pizza')
   OR (r.name = 'Podkarpackie Smaki' AND k.name = 'Polskie')
   OR (r.name = 'Azja w Rzeszowie' AND k.name = 'Azjatyckie')
   OR (r.name = 'Lubelska Pizzeria' AND k.name = 'Pizza')
   OR (r.name = 'Burger Lublin' AND k.name = 'Burger')
   OR (r.name = 'Wrocławski Grill' AND k.name = 'Grill')
   OR (r.name = 'Sushi Wrocław' AND k.name IN ('Japońskie', 'Sushi'))
   OR (r.name = 'Morskie Smaki' AND k.name = 'Ryby')
   OR (r.name = 'Pizza Szczecin' AND k.name = 'Pizza')
   OR (r.name = 'Poznańska Pyra' AND k.name = 'Polskie')
   OR (r.name = 'Thai Garden Poznań' AND k.name = 'Tajskie')
   OR (r.name = 'Gdańskie Pierogi' AND k.name = 'Polskie')
   OR (r.name = 'Bałtycka Pizza' AND k.name = 'Pizza')
   OR (r.name = 'Tomaszowskie Jadło' AND k.name = 'Polskie')
   OR (r.name = 'Burger Tomaszów' AND k.name = 'Burger')
ON CONFLICT DO NOTHING;

-- GODZINY OTWARCIA RESTAURACJI
INSERT INTO restaurant_opening_hours (restaurant_id, weekday, opens_at, closes_at)
SELECT r.id, w.day, '11:00', '22:00'
FROM restaurants r
CROSS JOIN (VALUES (1), (2), (3), (4), (5), (6), (7)) AS w(day)
WHERE r.name IN ('Burger King Warszawa', 'La Dolce Vita', 'Tokyo Sushi Bar', 'Dragon Palace', 'Polska Kuchnia',
                 'Grill Master', 'Pizzeria Napoli', 'Pho Vietnam', 'Sushi Paradise', 'Pasta Fresca',
                 'Krakowski Smak', 'Wawel Sushi', 'Kazimierz Pizza', 'Podkarpackie Smaki', 'Azja w Rzeszowie',
                 'Lubelska Pizzeria', 'Burger Lublin', 'Wrocławski Grill', 'Sushi Wrocław',
                 'Morskie Smaki', 'Pizza Szczecin', 'Poznańska Pyra', 'Thai Garden Poznań',
                 'Gdańskie Pierogi', 'Bałtycka Pizza', 'Tomaszowskie Jadło', 'Burger Tomaszów')
ON CONFLICT DO NOTHING;

-- UŻYTKOWNICY (hasło dla wszystkich: "Password*123")
-- Hash wygenerowany przez password_hash('Password*123', PASSWORD_BCRYPT)
INSERT INTO users (role_id, email, password_hash, full_name, phone)
VALUES
    -- Admin
    (1, 'admin@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Jan Kowalski', '500100200'),
    
    -- Klienci
    (2, 'anna.nowak@gmail.com', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Anna Nowak', '501234567'),
    (2, 'piotr.wisniewski@gmail.com', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Piotr Wiśniewski', '502345678'),
    (2, 'maria.kowalczyk@gmail.com', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Maria Kowalczyk', '503456789'),
    (2, 'tomasz.kaminski@gmail.com', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Tomasz Kamiński', '504567890'),
    (2, 'zofia.lewandowska@gmail.com', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Zofia Lewandowska', '505678901'),
    
    -- Pracownicy restauracji
    (3, 'manager.pasta@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Marco Rossi', '510111222'),
    (3, 'manager.thai@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Somchai Patel', '510222333'),
    (3, 'manager.burger@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'John Smith', '510333444'),
    (3, 'manager.greek@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Nikos Papadopoulos', '510444555'),
    (3, 'manager.sushi@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Takeshi Yamamoto', '510555666'),
    (3, 'manager.pizza@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Luigi Ferrari', '510666777'),
    (3, 'manager.burger.warszawa@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Tom Wilson', '510777888'),
    (3, 'manager.dolce.vita@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Antonio Bianchi', '510888999'),
    (3, 'manager.tokyo@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Kenji Tanaka', '510999111'),
    (3, 'manager.dragon@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Wei Chen', '511000222'),
    (3, 'manager.polska@warszawa@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Barbara Kowalska', '511111333'),
    (3, 'manager.grill.master@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Mehmet Özcan', '511222444'),
    (3, 'manager.pizzeria.napoli@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Giuseppe Moretti', '511333555'),
    (3, 'manager.pho@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Linh Nguyen', '511444666'),
    (3, 'manager.sushi.paradise@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Yuki Tanaka', '511555777'),
    (3, 'manager.pasta.fresca@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Rosa Benedetti', '511666888'),
    (3, 'manager.krakow.smak@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Marek Lewandowski', '511777999'),
    (3, 'manager.wawel.sushi@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Michał Sasiadek', '511888000'),
    (3, 'manager.kazimierz.pizza@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Piotr Adamski', '512000111'),
    (3, 'manager.podkarpackie@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Danuta Szymczak', '512111222'),
    (3, 'manager.azja.rzeszow@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Supachai Tiwari', '512222333'),
    (3, 'manager.lubelska.pizza@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Krzysztof Wajda', '512333444'),
    (3, 'manager.burger.lublin@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Artur Mazur', '512444555'),
    (3, 'manager.wroclaw.grill@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Tomasz Kucharski', '512555666'),
    (3, 'manager.sushi.wroclaw@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Hiroshi Yamada', '512666777'),
    (3, 'manager.morskie.smaki@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Mateusz Wójcik', '512777888'),
    (3, 'manager.pizza.szczecin@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Giulio Rossi', '512888999'),
    (3, 'manager.poznan.pyra@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Stanisław Nowak', '512999111'),
    (3, 'manager.thai.poznan@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Pakorn Saichon', '513000222'),
    (3, 'manager.gdansk.pierogi@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Urszula Drabik', '513111333'),
    (3, 'manager.baltycka.pizza@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Edoardo Carlini', '513222444'),
    (3, 'manager.tomaszowskie@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Helena Adamczyk', '513333555'),
    (3, 'manager.burger.tomaszow@jellyfood.pl', '$2y$12$8WnySrWbVtxpyiO3TxmrnueDAmYFZr5b0wWx2oLZLOz/bQb7iTVJW', 'Robert Kwiatkowski', '513444666'),
    
    -- Dostawcy
    (4, 'kurier1@jellyfood.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michał Zieliński', '520111222'),
    (4, 'kurier2@jellyfood.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Paweł Woźniak', '520222333'),
    (4, 'kurier3@jellyfood.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Krzysztof Dąbrowski', '520333444'),
    (4, 'kurier4@jellyfood.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Andrzej Kozłowski', '520444555')
ON CONFLICT DO NOTHING;

-- ADRESY UŻYTKOWNIKÓW (dla klientów)
INSERT INTO user_addresses (user_id, street, building_number, apartment_number, city, postal_code, country)
SELECT u.id, 'ul. Nowa', '12', '5', 'Warszawa', '00-123', 'Polska'
FROM users u WHERE u.email = 'anna.nowak@gmail.com'
UNION ALL
SELECT u.id, 'ul. Długa', '45', '10', 'Warszawa', '00-234', 'Polska'
FROM users u WHERE u.email = 'piotr.wisniewski@gmail.com'
UNION ALL
SELECT u.id, 'ul. Krótka', '7', NULL, 'Warszawa', '00-345', 'Polska'
FROM users u WHERE u.email = 'maria.kowalczyk@gmail.com'
UNION ALL
SELECT u.id, 'ul. Polna', '88', '3', 'Warszawa', '00-456', 'Polska'
FROM users u WHERE u.email = 'tomasz.kaminski@gmail.com'
UNION ALL
SELECT u.id, 'ul. Leśna', '33', '7', 'Warszawa', '00-567', 'Polska'
FROM users u WHERE u.email = 'zofia.lewandowska@gmail.com';

-- PRZYPISANIE PRACOWNIKÓW DO RESTAURACJI
INSERT INTO restaurant_system (user_id, restaurant_id)
SELECT u.id, r.id
FROM users u, restaurants r
WHERE (u.email = 'manager.burger.warszawa@jellyfood.pl' AND r.name = 'Burger King Warszawa')
   OR (u.email = 'manager.dolce.vita@jellyfood.pl' AND r.name = 'La Dolce Vita')
   OR (u.email = 'manager.tokyo@jellyfood.pl' AND r.name = 'Tokyo Sushi Bar')
   OR (u.email = 'manager.dragon@jellyfood.pl' AND r.name = 'Dragon Palace')
   OR (u.email = 'manager.polska@warszawa@jellyfood.pl' AND r.name = 'Polska Kuchnia')
   OR (u.email = 'manager.grill.master@jellyfood.pl' AND r.name = 'Grill Master')
   OR (u.email = 'manager.pizzeria.napoli@jellyfood.pl' AND r.name = 'Pizzeria Napoli')
   OR (u.email = 'manager.pho@jellyfood.pl' AND r.name = 'Pho Vietnam')
   OR (u.email = 'manager.sushi.paradise@jellyfood.pl' AND r.name = 'Sushi Paradise')
   OR (u.email = 'manager.pasta.fresca@jellyfood.pl' AND r.name = 'Pasta Fresca')
   OR (u.email = 'manager.krakow.smak@jellyfood.pl' AND r.name = 'Krakowski Smak')
   OR (u.email = 'manager.wawel.sushi@jellyfood.pl' AND r.name = 'Wawel Sushi')
   OR (u.email = 'manager.kazimierz.pizza@jellyfood.pl' AND r.name = 'Kazimierz Pizza')
   OR (u.email = 'manager.podkarpackie@jellyfood.pl' AND r.name = 'Podkarpackie Smaki')
   OR (u.email = 'manager.azja.rzeszow@jellyfood.pl' AND r.name = 'Azja w Rzeszowie')
   OR (u.email = 'manager.lubelska.pizza@jellyfood.pl' AND r.name = 'Lubelska Pizzeria')
   OR (u.email = 'manager.burger.lublin@jellyfood.pl' AND r.name = 'Burger Lublin')
   OR (u.email = 'manager.wroclaw.grill@jellyfood.pl' AND r.name = 'Wrocławski Grill')
   OR (u.email = 'manager.sushi.wroclaw@jellyfood.pl' AND r.name = 'Sushi Wrocław')
   OR (u.email = 'manager.morskie.smaki@jellyfood.pl' AND r.name = 'Morskie Smaki')
   OR (u.email = 'manager.pizza.szczecin@jellyfood.pl' AND r.name = 'Pizza Szczecin')
   OR (u.email = 'manager.poznan.pyra@jellyfood.pl' AND r.name = 'Poznańska Pyra')
   OR (u.email = 'manager.thai.poznan@jellyfood.pl' AND r.name = 'Thai Garden Poznań')
   OR (u.email = 'manager.gdansk.pierogi@jellyfood.pl' AND r.name = 'Gdańskie Pierogi')
   OR (u.email = 'manager.baltycka.pizza@jellyfood.pl' AND r.name = 'Bałtycka Pizza')
   OR (u.email = 'manager.tomaszowskie@jellyfood.pl' AND r.name = 'Tomaszowskie Jadło')
   OR (u.email = 'manager.burger.tomaszow@jellyfood.pl' AND r.name = 'Burger Tomaszów')
   OR (u.email = 'manager.pasta@jellyfood.pl' AND r.name = 'Pasta Paradise')
   OR (u.email = 'manager.thai@jellyfood.pl' AND r.name = 'Thai Palace')
   OR (u.email = 'manager.burger@jellyfood.pl' AND r.name = 'Burger House')
   OR (u.email = 'manager.greek@jellyfood.pl' AND r.name = 'Greek Taverna')
   OR (u.email = 'manager.sushi@jellyfood.pl' AND r.name = 'Sushi Express')
   OR (u.email = 'manager.pizza@jellyfood.pl' AND r.name = 'Pizza Firenze')
ON CONFLICT DO NOTHING;

-- KURIERZY
INSERT INTO couriers (user_id, vehicle_type, is_available)
SELECT u.id, 'rower', true FROM users u WHERE u.email = 'kurier1@jellyfood.pl'
UNION ALL
SELECT u.id, 'skuter', true FROM users u WHERE u.email = 'kurier2@jellyfood.pl'
UNION ALL
SELECT u.id, 'samochód', true FROM users u WHERE u.email = 'kurier3@jellyfood.pl'
UNION ALL
SELECT u.id, 'rower', false FROM users u WHERE u.email = 'kurier4@jellyfood.pl';

-- KATEGORIE MENU I POZYCJE MENU

-- Burger King Warszawa
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Burgery' FROM restaurants WHERE name = 'Burger King Warszawa'
UNION ALL
SELECT id, 'Dodatki' FROM restaurants WHERE name = 'Burger King Warszawa'
UNION ALL
SELECT id, 'Napoje' FROM restaurants WHERE name = 'Burger King Warszawa';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Classic Burger', 'Juicy beef burger z sałatą, pomidorem i sosem', 28.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Burgery'
UNION ALL
SELECT c.id, 'Bacon Cheeseburger', 'Burger z bekonem, dwoma plastrami sera i sosem barbecue', 35.00, 'https://images.unsplash.com/photo-1572802419224-7b93d6c6b7d4?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Burgery'
UNION ALL
SELECT c.id, 'Double Beef Burger', 'Dwaj policzki wołowiny, ser, cebula', 42.00, 'https://images.unsplash.com/photo-1553979459-d2229ba7433b?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Burgery'
UNION ALL
SELECT c.id, 'Fries', 'Złote, chrupiące frytki', 12.00, 'https://images.unsplash.com/photo-1599599810694-b5ac4dd37e5b?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Dodatki'
UNION ALL
SELECT c.id, 'Onion Rings', 'Pierścienie cebuli z chrupiącą panierkę', 14.00, 'https://images.unsplash.com/photo-1598065046519-c476b4917e1d?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Dodatki'
UNION ALL
SELECT c.id, 'Coca Cola', 'Chłodząca cola w wyborze rozmiarów', 8.00, 'https://images.unsplash.com/photo-1527960471264-932f39eb5846?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Burger King Warszawa' AND c.name = 'Napoje';

-- La Dolce Vita
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Pasta' FROM restaurants WHERE name = 'La Dolce Vita'
UNION ALL
SELECT id, 'Pizza' FROM restaurants WHERE name = 'La Dolce Vita'
UNION ALL
SELECT id, 'Desery' FROM restaurants WHERE name = 'La Dolce Vita';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Spaghetti Carbonara', 'Klasyka włoska z boczkiem, jajkiem i parmezanem', 38.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Penne Bolognese', 'Makaron z mięsnym sosem pomidorowym', 36.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Ravioli ai Funghi', 'Pierożki z grzybami leśnymi w śmietankowym sosie', 42.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Pizza Margherita', 'Sos, mozzarella, bazylia - przepis z Neapolu', 28.00, 'https://images.unsplash.com/photo-1548365328-8b849e6f2bd7?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pizza'
UNION ALL
SELECT c.id, 'Pizza Quattro Formaggi', 'Pizza z czterema serami', 38.00, 'https://images.unsplash.com/photo-1541745537411-b8046dc6d66c?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pizza'
UNION ALL
SELECT c.id, 'Pizza Prosciutto e Rucola', 'Pizza z szynką i rukolą', 35.00, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Pizza'
UNION ALL
SELECT c.id, 'Panna Cotta', 'Włoska krówka waniliowa z berriami', 16.00, 'https://images.unsplash.com/photo-1488477181946-85a2893f3e9f?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'La Dolce Vita' AND c.name = 'Desery';

-- Tokyo Sushi Bar
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Nigiri' FROM restaurants WHERE name = 'Tokyo Sushi Bar'
UNION ALL
SELECT id, 'Rolls' FROM restaurants WHERE name = 'Tokyo Sushi Bar'
UNION ALL
SELECT id, 'Zestawy' FROM restaurants WHERE name = 'Tokyo Sushi Bar';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Salmon Nigiri', '6 sztuk nigiri z łososiem', 26.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Nigiri'
UNION ALL
SELECT c.id, 'Tuna Nigiri', '6 sztuk nigiri z tuńczykiem', 28.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Nigiri'
UNION ALL
SELECT c.id, 'California Roll', 'Wkład: krab, awokado, ogórek', 22.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Rolls'
UNION ALL
SELECT c.id, 'Spicy Tuna Roll', 'Gorący roll z tuńczykiem', 24.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Rolls'
UNION ALL
SELECT c.id, 'Premium Zestaw', '30 sztuk sushi - mieszanka najlepszych', 95.00, 'https://images.unsplash.com/photo-1553621042-f06b0442b8a7?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Zestawy'
UNION ALL
SELECT c.id, 'Casual Zestaw', '18 sztuk sushi do wypróbowania', 65.00, 'https://images.unsplash.com/photo-1553621042-f06b0442b8a7?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Tokyo Sushi Bar' AND c.name = 'Zestawy';

-- Dragon Palace
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Makarony' FROM restaurants WHERE name = 'Dragon Palace'
UNION ALL
SELECT id, 'Dania Mięsne' FROM restaurants WHERE name = 'Dragon Palace'
UNION ALL
SELECT id, 'Zupy' FROM restaurants WHERE name = 'Dragon Palace';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Chow Mein Kurczak', 'Makaron smażony z kurczakiem i warzywami', 32.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Makarony'
UNION ALL
SELECT c.id, 'Fried Rice Wołowina', 'Ryż smażony z wołowiną i jajkiem', 34.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Makarony'
UNION ALL
SELECT c.id, 'Kung Pao Chicken', 'Kurczak z orzeszkami ziemnymi w sosie', 38.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Dania Mięsne'
UNION ALL
SELECT c.id, 'Peking Duck', 'Kaczka pekińska w sosie hoisin', 48.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Dania Mięsne'
UNION ALL
SELECT c.id, 'Tom Yum Shrimp', 'Azjatycka ostra zupa z krewetkami', 28.00, 'https://images.unsplash.com/photo-1596040995857-50cb0c3422ba?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Zupy'
UNION ALL
SELECT c.id, 'Egg Drop Soup', 'Zupa z jajkiem i kurzym bulionem', 16.00, 'https://images.unsplash.com/photo-1596040995857-50cb0c3422ba?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Dragon Palace' AND c.name = 'Zupy';

-- Polska Kuchnia
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Główne Dania' FROM restaurants WHERE name = 'Polska Kuchnia'
UNION ALL
SELECT id, 'Pierogi' FROM restaurants WHERE name = 'Polska Kuchnia'
UNION ALL
SELECT id, 'Dodatki' FROM restaurants WHERE name = 'Polska Kuchnia';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Bigos', 'Tradycyjny bigos z kilkoma mięsami', 32.00, 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Główne Dania'
UNION ALL
SELECT c.id, 'Żurek ze Śmietaną', 'Tradycyjna żytnia zupa z żurem i białą kiełbasą', 26.00, 'https://images.unsplash.com/photo-1596040995857-50cb0c3422ba?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Główne Dania'
UNION ALL
SELECT c.id, 'Kotlety Mielone', 'Kotlety mielone z żurem i ziemniakami', 28.00, 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Główne Dania'
UNION ALL
SELECT c.id, 'Pierogi z Kurczakiem', '12 pierogów z kurczakiem i kapustą', 24.00, 'https://images.unsplash.com/photo-1498625046594-f47f2387a45f?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Pierogi'
UNION ALL
SELECT c.id, 'Pierogi Ziemniaczane', '12 pierogów ze śmietaną i cebulką', 18.00, 'https://images.unsplash.com/photo-1498625046594-f47f2387a45f?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Pierogi'
UNION ALL
SELECT c.id, 'Mizeria', 'Chłodna sałatka z ogórka i śmietany', 12.00, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Polska Kuchnia' AND c.name = 'Dodatki';

-- Grill Master
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Szaszłyki' FROM restaurants WHERE name = 'Grill Master'
UNION ALL
SELECT id, 'Mięsa Grillowane' FROM restaurants WHERE name = 'Grill Master'
UNION ALL
SELECT id, 'Dodatki' FROM restaurants WHERE name = 'Grill Master';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Szaszłyk Mięso Mielone', 'Tradycyjny szaszłyk z mięsa mielonego', 28.00, 'https://images.unsplash.com/photo-1619521261578-641268d8fbdb?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Szaszłyki'
UNION ALL
SELECT c.id, 'Szaszłyk Drób', 'Szaszłyk z miękkiego kurczaka', 32.00, 'https://images.unsplash.com/photo-1619521261578-641268d8fbdb?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Szaszłyki'
UNION ALL
SELECT c.id, 'Steak Grillowany', 'Soczysty stek z grilla podawany ze smakami', 45.00, 'https://images.unsplash.com/photo-1432139555190-58524dae6a55?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Mięsa Grillowane'
UNION ALL
SELECT c.id, 'Kurczak Marango', 'Całe kurczę z grilla', 38.00, 'https://images.unsplash.com/photo-1432139555190-58524dae6a55?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Mięsa Grillowane'
UNION ALL
SELECT c.id, 'Chleb z Czosnkiem', 'Świeżo pieczony chleb z czosnkiem i oliwą', 10.00, 'https://images.unsplash.com/photo-1586985289688-cacf91ca6d48?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Dodatki'
UNION ALL
SELECT c.id, 'Ryż Pilaf', 'Ryż pachnący z warzywami', 12.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Grill Master' AND c.name = 'Dodatki';

-- Pizzeria Napoli
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Pizza Klasyczne' FROM restaurants WHERE name = 'Pizzeria Napoli'
UNION ALL
SELECT id, 'Pizza Specjalne' FROM restaurants WHERE name = 'Pizzeria Napoli'
UNION ALL
SELECT id, 'Napoje' FROM restaurants WHERE name = 'Pizzeria Napoli';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Margherita', 'Sos, mozzarella, bazylia świeża', 26.00, 'https://images.unsplash.com/photo-1604068549290-dea0e4a305ca?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Pizza Klasyczne'
UNION ALL
SELECT c.id, 'Pepperoni', 'Mozzarella, pepperoni, sos pomidorowy', 32.00, 'https://images.unsplash.com/photo-1601924582970-9238bcb495d9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Pizza Klasyczne'
UNION ALL
SELECT c.id, 'Quattro Formaggi', 'Pizza z czterema rodzajami sera', 38.00, 'https://images.unsplash.com/photo-1628840042765-356cda07f4ee?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Pizza Specjalne'
UNION ALL
SELECT c.id, 'Prosciutto e Rucola', 'Szynka i świeża rucola', 36.00, 'https://images.unsplash.com/photo-1628840042765-356cda07f4ee?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Pizza Specjalne'
UNION ALL
SELECT c.id, 'Wino Chianti', 'Włoskie wino czerwone', 35.00, 'https://images.unsplash.com/photo-1510627498534-cf7e9002facc?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Napoje'
UNION ALL
SELECT c.id, 'San Pellegrino', 'Włoska woda gazowana', 8.00, 'https://images.unsplash.com/photo-1502741126161-b048400d6854?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pizzeria Napoli' AND c.name = 'Napoje';

-- Pho Vietnam
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Zupy' FROM restaurants WHERE name = 'Pho Vietnam'
UNION ALL
SELECT id, 'Dania Mięsne' FROM restaurants WHERE name = 'Pho Vietnam'
UNION ALL
SELECT id, 'Spring Rolls' FROM restaurants WHERE name = 'Pho Vietnam';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Pho Bo', 'Tradycyjna wietnamska zupa z wołowiną', 32.00, 'https://images.unsplash.com/photo-1596040995857-50cb0c3422ba?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pho Vietnam' AND c.name = 'Zupy'
UNION ALL
SELECT c.id, 'Pho Ga', 'Zupa pho z kurczakiem', 28.00, 'https://images.unsplash.com/photo-1596040995857-50cb0c3422ba?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pho Vietnam' AND c.name = 'Zupy'
UNION ALL
SELECT c.id, 'Banh Mi Thit Nuong', 'Wietnamska kanapka z kurczakiem', 24.00, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pho Vietnam' AND c.name = 'Dania Mięsne'
UNION ALL
SELECT c.id, 'Nem Cuon', 'Świeże wiosenne roladki', 16.00, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pho Vietnam' AND c.name = 'Spring Rolls'
UNION ALL
SELECT c.id, 'Nem Ran', 'Usmażone wiosenne roladki', 18.00, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pho Vietnam' AND c.name = 'Spring Rolls';

-- Sushi Paradise
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Nigiri Sushi' FROM restaurants WHERE name = 'Sushi Paradise'
UNION ALL
SELECT id, 'Maki Rolls' FROM restaurants WHERE name = 'Sushi Paradise'
UNION ALL
SELECT id, 'Zestawy Premium' FROM restaurants WHERE name = 'Sushi Paradise';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Salmon Sashimi', '8 sztuk łososia', 32.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Nigiri Sushi'
UNION ALL
SELECT c.id, 'Tuna Sashimi', '8 sztuk tuńczyka', 34.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Nigiri Sushi'
UNION ALL
SELECT c.id, 'Dragon Roll', 'Awokado, ogórek, krab pseudocrab', 26.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Maki Rolls'
UNION ALL
SELECT c.id, 'Philadelphia Roll', 'Łosoś, śmietana, ogórek', 28.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Maki Rolls'
UNION ALL
SELECT c.id, 'Zestaw Luksus', '50 sztuk premium sushi selection', 150.00, 'https://images.unsplash.com/photo-1553621042-f06b0442b8a7?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Zestawy Premium'
UNION ALL
SELECT c.id, 'Zestaw Elegancki', '32 sztuki sushi mix', 110.00, 'https://images.unsplash.com/photo-1553621042-f06b0442b8a7?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Sushi Paradise' AND c.name = 'Zestawy Premium';

-- Pasta Fresca
INSERT INTO menu_categories (restaurant_id, name)
SELECT id, 'Pasta Świeża' FROM restaurants WHERE name = 'Pasta Fresca'
UNION ALL
SELECT id, 'Risotto' FROM restaurants WHERE name = 'Pasta Fresca'
UNION ALL
SELECT id, 'Desery' FROM restaurants WHERE name = 'Pasta Fresca';

INSERT INTO menu_items (category_id, name, description, price, image, is_active)
SELECT c.id, 'Tagliatelle al Funghi', 'Świeża tagliatelle z grzybami leśnymi', 42.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Pasta Świeża'
UNION ALL
SELECT c.id, 'Fettuccine Alfredo', 'Makaron ze śmietankowym sosem parmezanu', 38.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Pasta Świeża'
UNION ALL
SELECT c.id, 'Ravioli Ricotta', 'Pierożki z ricottą i szpinakiem', 40.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Pasta Świeża'
UNION ALL
SELECT c.id, 'Risotto al Tartufo', 'Risotto z truflami czarnymi', 48.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Risotto'
UNION ALL
SELECT c.id, 'Risotto ai Funghi', 'Risotto z grzybami porcini', 42.00, 'https://images.unsplash.com/photo-1609501676725-7186f017a4b0?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Risotto'
UNION ALL
SELECT c.id, 'Tiramisu', 'Włoskie tiramisu z maskaroponem', 18.00, 'https://images.unsplash.com/photo-1571115177098-24ec42ed204d?auto=format&fit=crop&w=800&q=80', true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Fresca' AND c.name = 'Desery';


SELECT c.id, 'Carpaccio', 'Cienkie plastry wołowiny z rukolą i parmezanem', 32.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Przystawki'
UNION ALL
SELECT c.id, 'Spaghetti Carbonara', 'Klasyczny przepis z boczkiem, jajkiem i parmezanem', 38.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Penne Arrabbiata', 'Ostre penne z sosem pomidorowym', 34.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Tagliatelle al Funghi', 'Makaron z grzybami leśnymi', 42.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Pasta'
UNION ALL
SELECT c.id, 'Pizza Margherita', 'Sos pomidorowy, mozzarella, bazylia', 28.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Pizza'
UNION ALL
SELECT c.id, 'Tiramisu', 'Klasyczny włoski deser', 22.00, true
FROM menu_categories c JOIN restaurants r ON c.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND c.name = 'Desery';

-- ============================
-- TESTOWE ZAMÓWIENIA (ZAKOMENTOWANE - odwołują się do starych restauracji)
-- ============================
/*
-- ZAMÓWIENIA Z RÓŻNYMI STATUSAMI
INSERT INTO orders (customer_id, restaurant_id, address_id, status, created_at, updated_at)
SELECT 
    u.id,
    r.id,
    a.id,
    'delivered',
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '3 days'
FROM users u
JOIN user_addresses a ON u.id = a.user_id
JOIN restaurants r ON r.name = 'Pasta Paradise'
WHERE u.email = 'anna.nowak@gmail.com'
UNION ALL
SELECT 
    u.id,
    r.id,
    a.id,
    'delivered',
    NOW() - INTERVAL '2 days',
    NOW() - INTERVAL '2 days'
FROM users u
JOIN user_addresses a ON u.id = a.user_id
JOIN restaurants r ON r.name = 'Thai Palace'
WHERE u.email = 'piotr.wisniewski@gmail.com'
UNION ALL
SELECT 
    u.id,
    r.id,
    a.id,
    'picked_up',
    NOW() - INTERVAL '1 hour',
    NOW() - INTERVAL '30 minutes'
FROM users u
JOIN user_addresses a ON u.id = a.user_id
JOIN restaurants r ON r.name = 'Burger House'
WHERE u.email = 'maria.kowalczyk@gmail.com'
UNION ALL
SELECT 
    u.id,
    r.id,
    a.id,
    'preparing',
    NOW() - INTERVAL '30 minutes',
    NOW() - INTERVAL '15 minutes'
FROM users u
JOIN user_addresses a ON u.id = a.user_id
JOIN restaurants r ON r.name = 'Sushi Express'
WHERE u.email = 'tomasz.kaminski@gmail.com'
UNION ALL
SELECT 
    u.id,
    r.id,
    a.id,
    'pending',
    NOW() - INTERVAL '10 minutes',
    NOW() - INTERVAL '10 minutes'
FROM users u
JOIN user_addresses a ON u.id = a.user_id
JOIN restaurants r ON r.name = 'Pizza Firenze'
WHERE u.email = 'zofia.lewandowska@gmail.com';

-- POZYCJE ZAMÓWIEŃ
-- Zamówienie 1 (Anna - Pasta Paradise)
INSERT INTO order_items (order_id, menu_item_id, quantity, price)
SELECT 1, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND mi.name = 'Bruschetta'
UNION ALL
SELECT 1, mi.id, 2, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Pasta Paradise' AND mi.name = 'Spaghetti Carbonara';

-- Zamówienie 2 (Piotr - Thai Palace)
INSERT INTO order_items (order_id, menu_item_id, quantity, price)
SELECT 2, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Thai Palace' AND mi.name = 'Tom Yum'
UNION ALL
SELECT 2, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Thai Palace' AND mi.name = 'Pad Thai';

-- Zamówienie 3 (Maria - Burger House)
INSERT INTO order_items (order_id, menu_item_id, quantity, price)
SELECT 3, mi.id, 2, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Burger House' AND mi.name = 'Cheese Burger'
UNION ALL
SELECT 3, mi.id, 2, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Burger House' AND mi.name = 'Frytki';

-- Zamówienie 4 (Tomasz - Sushi Express)
INSERT INTO order_items (order_id, menu_item_id, quantity, price)
SELECT 4, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Sushi Express' AND mi.name = 'Zestaw Mix';

-- Zamówienie 5 (Zofia - Pizza Firenze)
INSERT INTO order_items (order_id, menu_item_id, quantity, price)
SELECT 5, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Pizza Firenze' AND mi.name = 'Pepperoni'
UNION ALL
SELECT 5, mi.id, 1, mi.price
FROM menu_items mi
JOIN menu_categories mc ON mi.category_id = mc.id
JOIN restaurants r ON mc.restaurant_id = r.id
WHERE r.name = 'Pizza Firenze' AND mi.name = 'Quattro Stagioni';

-- PŁATNOŚCI
INSERT INTO payments (order_id, amount, payment_method, status, created_at)
VALUES
    (1, 94.00, 'card', 'paid', NOW() - INTERVAL '3 days'),
    (2, 70.00, 'blik', 'paid', NOW() - INTERVAL '2 days'),
    (3, 100.00, 'card', 'paid', NOW() - INTERVAL '1 hour'),
    (4, 95.00, 'cash', 'pending', NOW() - INTERVAL '30 minutes'),
    (5, 70.00, 'blik', 'pending', NOW() - INTERVAL '10 minutes');

-- DOSTAWY
INSERT INTO deliveries (order_id, courier_id, pickup_time, delivery_time, status)
SELECT 1, c.id, NOW() - INTERVAL '3 days' + INTERVAL '20 minutes', NOW() - INTERVAL '3 days' + INTERVAL '45 minutes', 'delivered'
FROM couriers c JOIN users u ON c.user_id = u.id WHERE u.email = 'kurier1@jellyfood.pl'
UNION ALL
SELECT 2, c.id, NOW() - INTERVAL '2 days' + INTERVAL '15 minutes', NOW() - INTERVAL '2 days' + INTERVAL '40 minutes', 'delivered'
FROM couriers c JOIN users u ON c.user_id = u.id WHERE u.email = 'kurier2@jellyfood.pl'
UNION ALL
SELECT 3, c.id, NOW() - INTERVAL '50 minutes', NULL, 'waiting_for_pickup'
FROM couriers c JOIN users u ON c.user_id = u.id WHERE u.email = 'kurier3@jellyfood.pl';

-- RECENZJE
INSERT INTO reviews (order_id, customer_id, restaurant_id, rating, comment, created_at)
SELECT 
    1,
    u.id,
    r.id,
    5,
    'Wspaniałe jedzenie! Carbonara była wyśmienita, a bruschetta świeża i aromatyczna.',
    NOW() - INTERVAL '3 days' + INTERVAL '2 hours'
FROM users u
JOIN restaurants r ON r.name = 'Pasta Paradise'
WHERE u.email = 'anna.nowak@gmail.com'
UNION ALL
SELECT 
    2,
    u.id,
    r.id,
    4,
    'Bardzo dobre tajskie jedzenie, Pad Thai był autentyczny. Jedynie czas dostawy mógł być krótszy.',
    NOW() - INTERVAL '2 days' + INTERVAL '1 hour'
FROM users u
JOIN restaurants r ON r.name = 'Thai Palace'
WHERE u.email = 'piotr.wisniewski@gmail.com';

-- ULUBIONE RESTAURACJE
INSERT INTO favorites (user_id, restaurant_id)
SELECT u.id, r.id
FROM users u, restaurants r
WHERE u.email = 'anna.nowak@gmail.com' AND r.name IN ('Pasta Paradise', 'Pizza Firenze')
UNION ALL
SELECT u.id, r.id
FROM users u, restaurants r
WHERE u.email = 'piotr.wisniewski@gmail.com' AND r.name = 'Thai Palace'
UNION ALL
SELECT u.id, r.id
FROM users u, restaurants r
WHERE u.email = 'maria.kowalczyk@gmail.com' AND r.name IN ('Burger House', 'Pizza Firenze')
UNION ALL
SELECT u.id, r.id
FROM users u, restaurants r
WHERE u.email = 'tomasz.kaminski@gmail.com' AND r.name = 'Sushi Express'
UNION ALL
SELECT u.id, r.id
FROM users u, restaurants r
WHERE u.email = 'zofia.lewandowska@gmail.com' AND r.name IN ('Pizza Firenze', 'Pasta Paradise', 'Thai Palace');
*/
