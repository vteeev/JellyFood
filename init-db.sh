#!/bin/bash

# Skrypt do inicjalizacji bazy danych JellyFood
# Uruchamia SQL z pgAdmin lub bezpośrednio z docker-compose

echo "Inicjalizacja bazy danych JellyFood..."

# SQL do stworzenia ról
docker exec -it jellyfood-db-1 psql -U docker -d db << EOF
psql mydatabase


EOF

echo "Baza danych została zainicjalizowana!"
