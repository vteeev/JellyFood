<?php

require_once __DIR__ . '/../repository/RestaurantRepository.php';

class restaurants
{
    private RestaurantRepository $restaurantRepository;

    public function __construct()
    {
        $this->restaurantRepository = new RestaurantRepository();
    }

    /**
     * Obsługuje żądania do /restaurants/*
     */
    public function handle_request(string $action, array $request, array $params): ?string
    {
        return match ($action) {
            'index' => $this->handleIndex($request),
            'get' => $this->handleGet($params),
            'menu' => $this->handleMenu($params),
            'search' => $this->handleSearch($request),
            'kitchen-types' => $this->handleKitchenTypes(),
            'by-kitchen' => $this->handleByKitchenType($request),
            default => null,
        };
    }

    /**
     * GET /restaurants
     * Pobranie wszystkich restauracji
     */
    private function handleIndex(array $request): string
    {
        try {
            $restaurants = $this->restaurantRepository->getAllRestaurants();

            json_response([
                'success' => true,
                'data' => $restaurants,
                'count' => count($restaurants),
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd pobrania restauracji',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /restaurants/get/1
     * Pobranie restauracji po ID
     */
    private function handleGet(array $params): string
    {
        if (empty($params[0])) {
            return json_encode([
                'success' => false,
                'message' => 'Brak ID restauracji',
            ]);
        }

        $id = (int)$params[0];

        try {
            $restaurant = $this->restaurantRepository->getRestaurantById($id);

            if (!$restaurant) {
                json_response([
                    'success' => false,
                    'message' => 'Restauracja nie znaleziona',
                ], 404);
            }

            json_response([
                'success' => true,
                'data' => $restaurant,
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd pobrania restauracji',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /restaurants/menu/1
     * Pobranie menu restauracji po ID
     */
    private function handleMenu(array $params): string
    {
        if (empty($params[0])) {
            return json_encode([
                'success' => false,
                'message' => 'Brak ID restauracji',
            ]);
        }

        $id = (int)$params[0];

        try {
            $menu = $this->restaurantRepository->getRestaurantMenu($id);

            json_response([
                'success' => true,
                'data' => $menu,
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd pobrania menu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /restaurants/search?q=pizza
     * Wyszukiwanie restauracji
     */
    private function handleSearch(array $request): string
    {
        $query = $request['q'] ?? '';

        if (empty($query) || strlen($query) < 2) {
            return json_encode([
                'success' => false,
                'message' => 'Zapytanie musi mieć co najmniej 2 znaki',
            ]);
        }

        try {
            $restaurants = $this->restaurantRepository->searchRestaurants($query);

            json_response([
                'success' => true,
                'data' => $restaurants,
                'count' => count($restaurants),
                'query' => $query,
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd wyszukiwania',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /restaurants/kitchen-types
     * Pobranie wszystkich typów kuchni
     */
    private function handleKitchenTypes(): string
    {
        try {
            $kitchenTypes = $this->restaurantRepository->getAllKitchenTypes();

            json_response([
                'success' => true,
                'data' => $kitchenTypes,
                'count' => count($kitchenTypes),
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd pobrania typów kuchni',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /restaurants/by-kitchen?type=Pizza
     * Pobranie restauracji po typie kuchni
     */
    private function handleByKitchenType(array $request): string
    {
        $type = $request['type'] ?? '';

        if (empty($type)) {
            return json_encode([
                'success' => false,
                'message' => 'Typ kuchni jest wymagany',
            ]);
        }

        try {
            $restaurants = $this->restaurantRepository->getRestaurantsByKitchenType($type);

            json_response([
                'success' => true,
                'data' => $restaurants,
                'count' => count($restaurants),
                'kitchen_type' => $type,
            ]);
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Błąd pobrania restauracji',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

// Inicjalizacja kontrolera
$restaurantsController = new restaurants();

/**
 * Funkcja wymagana przez Routing.php
 */
function handle_request(string $action, array $request, array $params): ?string
{
    global $restaurantsController;
    return $restaurantsController->handle_request($action, $request, $params);
}
