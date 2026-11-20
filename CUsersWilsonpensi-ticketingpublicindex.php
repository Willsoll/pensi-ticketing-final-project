<?php
// public/index.php
// Front controller / Router

// Route parsing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// IMMEDIATE DEBUG - Write to file
$debugLog = __DIR__ . '/router_debug.log';
$cwd = getcwd();
$absPath = realpath($debugLog);
$testWrite = file_put_contents($debugLog, date('Y-m-d H:i:s') . " SCRIPT STARTED (cwd: $cwd, debugLog: $debugLog, realpath: $absPath)\n", FILE_APPEND);
if ($testWrite === false) {
    die("DEBUG: Failed to write to $debugLog");
}

file_put_contents($debugLog, date('Y-m-d H:i:s') . " Router INITIAL URI: '$uri'\n", FILE_APPEND);

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '/' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}

// Remove index.php from URI if present (for direct index.php access)
if (str_starts_with($uri, '/index.php/')) {
    $uri = substr($uri, 9); // Remove '/index.php'
}
if ($uri === '') $uri = '/';

file_put_contents($debugLog, date('Y-m-d H:i:s') . " Router FINAL URI: '$uri'\n", FILE_APPEND);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Start session
\App\Security\SessionManager::start();

// Load configs
$appConfig = require __DIR__ . '/../config/app.php';
$securityConfig = require __DIR__ . '/../config/security.php';

// Security headers
foreach ($securityConfig['headers'] as $header => $value) {
    header("$header: $value");
}

// CSP
if (!empty($securityConfig['csp'])) {
    $csp = [];
    foreach ($securityConfig['csp'] as $directive => $values) {
        $csp[] = "$directive " . implode(' ', $values);
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// HSTS
if ($securityConfig['hsts']['enabled']) {
    $hsts = "max-age={$securityConfig['hsts']['max_age']}";
    if ($securityConfig['hsts']['include_subdomains']) $hsts .= '; includeSubDomains';
    if ($securityConfig['hsts']['preload']) $hsts .= '; preload';
    header("Strict-Transport-Security: $hsts");
}

// Rate limiting for auth endpoints
if (in_array($_SERVER['REQUEST_METHOD'], ['POST']) && 
    in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/login', '/register'])) {
    // Rate limiting handled in middleware
}

// Simple router
$routes = [
    // Public
    'GET /' => ['CatalogController', 'index'],
    'GET /search' => ['CatalogController', 'search'],
    'GET /event/{id:\d+}' => ['EventController', 'detail'],
    'POST /event/select-tier' => ['EventController', 'selectTier'],
    
    // Auth
    'GET /login' => ['AuthController', 'showLogin'],
    'POST /login' => ['AuthController', 'login'],
    'GET /register' => ['AuthController', 'showRegister'],
    'POST /register' => ['AuthController', 'register'],
    'POST /logout' => ['AuthController', 'logout'],
    
    // Customer booking
    'GET /booking/identity' => ['BookingController', 'identity'],
    'POST /booking/identity' => ['BookingController', 'processIdentity'],
    'GET /checkout/{id:\d+}' => ['BookingController', 'checkout'],
    'POST /checkout/{id:\d+}/upload' => ['BookingController', 'uploadProof'],
    'GET /my-bookings' => ['BookingController', 'myBookings'],
    
    // Admin
    'GET /admin' => ['AdminController', 'dashboard'],
    'GET /admin/events' => ['AdminController', 'events'],
    'POST /admin/events' => ['AdminController', 'createEvent'],
    'GET /admin/events/{id:\d+}/edit' => ['AdminController', 'editEvent'],
    'POST /admin/events/{id:\d+}/edit' => ['AdminController', 'editEvent'],
    'POST /admin/events/{id:\d+}/delete' => ['AdminController', 'deleteEvent'],
    'GET /admin/tiers' => ['AdminController', 'tiers'],
    'POST /admin/tiers' => ['AdminController', 'createTier'],
    'GET /admin/tiers/{id:\d+}/seats' => ['AdminController', 'seats'],
    'POST /admin/tiers/{id:\d+}/seats' => ['AdminController', 'seats'],
    'GET /admin/vouchers' => ['AdminController', 'vouchers'],
    'POST /admin/vouchers' => ['AdminController', 'createVoucher'],
    'GET /admin/analytics' => ['AdminController', 'analytics'],
    
    // Finance
    'GET /finance' => ['FinanceController', 'index'],
    'POST /finance/verify/{id:\d+}' => ['FinanceController', 'verify'],
    
    // Gate
    'GET /gate' => ['GateController', 'index'],
    'POST /gate/checkin' => ['GateController', 'checkin'],
    'GET /gate/audit' => ['GateController', 'audit'],
    
    // Customer
    'GET /my-bookings' => ['BookingController', 'myBookings'],
];

// Simple route matching
$matched = false;
foreach ($routes as $pattern => $handler) {
    $regex = preg_replace('/\{(\w+)(?::([^}]+))?\}/', '(?P<$1>$2)', $pattern);
    $regex = '#^' . str_replace('/', '\/', $regex) . '$#';
    
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " Router DEBUG: URI='$uri', Pattern='$pattern', Regex='$regex'\n", FILE_APPEND);
    
    if (preg_match($regex, $uri, $matches)) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " Router MATCHED: Pattern='$pattern'\n", FILE_APPEND);
        $matched = true;
        
        // Extract named parameters
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        
        $controllerName = $handler[0];
        $methodName = $handler[1];
        
        $controllerClass = "App\Controllers\$controllerName";
        
        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo "Controller $controllerClass not found";
            exit;
        }
        
        if (!method_exists($controller, $methodName)) {
            http_response_code(500);
            echo "Method $methodName not found in $controllerClass";
            exit;
        }
        
        // Call controller method with parameters
        call_user_func_array([$controller, $methodName], array_values($params));
        exit;
    }
}

// 404
http_response_code(404);
echo "404 - Page not found: " . htmlspecialchars($uri);
