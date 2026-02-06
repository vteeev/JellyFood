<?php
// Front-controller router for the project.
// - Serves static assets (public, script, styles)
// - Maps simple routes to `public/views/*.html`
// - Dispatches to controllers in `src/controllers/<name>.php` when present
// - Provides `render_view()` and `json_response()` helpers for controllers

// Uruchom sesję na początku każdego żądania
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);
if ($uri !== '/' && substr($uri, -1) === '/') {
	$uri = rtrim($uri, '/');
}

$projectRoot = __DIR__;

$routes = [
	'/' => 'public/views/main_page.html',
	'/index.php' => 'public/views/main_page.html',
	'/login' => 'public/views/login.html',
	'/register' => 'public/views/register.html',
	'/restaurant' => 'public/views/restaurant_dashboard.html',
	'/restaurant-orders' => 'public/views/restaurant-orders-dashboard.html',
	'/admin' => 'public/views/admin-dashboard.html',
	'/complete_order' => 'public/views/complete_order.html',
	'/bin' => 'public/views/bin.html',
];

$staticPrefixes = ['/public/', '/script/', '/styles/', '/assets/', '/css/', '/js/'];

// Serve static files (fast path)
foreach ($staticPrefixes as $p) {
	if (strpos($uri, $p) === 0) {
		$filePath = $projectRoot . $uri;
		if (is_file($filePath)) {
			send_file($filePath);
			exit;
		}
		break;
	}
}

if ($uri === '/favicon.ico') {
	$f = $projectRoot . '/public/favicon.ico';
	if (is_file($f)) {
		send_file($f);
		exit;
	}
}

// Known route -> static view
if (isset($routes[$uri])) {
	$file = $projectRoot . DIRECTORY_SEPARATOR . $routes[$uri];
	if (is_file($file)) {
		send_file($file);
		exit;
	}
}

// Try view file by convention: public/views<uri>.html
// Skip this for routes that have controllers (profile, orders, admin, etc.)
$skipAutoViews = ['/profile', '/orders', '/admin'];
if (!in_array($uri, $skipAutoViews)) {
	$maybe = $projectRoot . '/public/views' . $uri . '.html';
	if (is_file($maybe)) {
		send_file($maybe);
		exit;
	}
}

// Controller dispatch: map first segment to src/controllers/<name>.php
$trimmed = trim($uri, '/');
$parts = $trimmed === '' ? [] : explode('/', $trimmed);
$controllerName = $parts[0] ?? 'home';
$action = $parts[1] ?? 'index';
$controllerFile = $projectRoot . '/src/controllers/' . $controllerName . '.php';

// Helper: render a view from public/views
function render_view($viewName, $data = [])
{
	$base = __DIR__ . '/public/views/';
	$path = $base . $viewName;
	if (pathinfo($path, PATHINFO_EXTENSION) === '') {
		$path .= '.html';
	}
	if (!is_file($path)) {
		header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		echo "404 Not Found";
		exit;
	}
	extract($data, EXTR_SKIP);
	include $path;
}

function json_response($data, $status = 200)
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if (is_file($controllerFile)) {
	$params = array_slice($parts, 2);
	// Make commonly used globals available to controllers
	$_ROUTE = ['controller' => $controllerName, 'action' => $action, 'params' => $params];
	require $controllerFile;
	if (function_exists('handle_request')) {
		$ret = handle_request($action, $_REQUEST, $params);
		if ($ret === null) {
			exit; // controller already handled response
		}
		if (is_array($ret)) {
			json_response($ret);
		}
		if (is_string($ret)) {
			echo $ret;
			exit;
		}
	}
	// If controller didn't send anything, exit
	exit;
}

// Fallback to a 404 view if present
$notFoundView = $projectRoot . '/public/views/404.html';
if (is_file($notFoundView)) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
	send_file($notFoundView);
	exit;
}

// Default text 404
header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
echo "404 Not Found";

function send_file($path)
{
	$mime = mime_type($path);
	if ($mime) {
		header('Content-Type: ' . $mime);
	}
	header('Content-Length: ' . filesize($path));
	readfile($path);
}

function mime_type($path)
{
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	$map = [
		'html' => 'text/html; charset=utf-8',
		'htm' => 'text/html; charset=utf-8',
		'css' => 'text/css; charset=utf-8',
		'js' => 'application/javascript; charset=utf-8',
		'json' => 'application/json',
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif' => 'image/gif',
		'svg' => 'image/svg+xml',
		'ico' => 'image/x-icon',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf' => 'font/ttf',
		'eot' => 'application/vnd.ms-fontobject',
		'map' => 'application/json',
		'txt' => 'text/plain; charset=utf-8',
	];

	if (isset($map[$ext])) {
		return $map[$ext];
	}

	if (function_exists('finfo_open') && is_file($path)) {
		$f = finfo_open(FILEINFO_MIME_TYPE);
		if ($f !== false) {
			$m = finfo_file($f, $path);
			finfo_close($f);
			return $m;
		}
	}

	return 'application/octet-stream';
}

