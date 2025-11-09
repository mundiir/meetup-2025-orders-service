<?php

declare(strict_types=1);

use App\Application\UseCase\CreateOrder\CreateOrderHandler;
use App\Application\UseCase\GetOrder\GetOrderHandler;
use App\Presentation\Http\OrderController;

// Autoload via Composer if available, otherwise use a tiny PSR-4 fallback for App\
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $path = __DIR__ . '/../src/' . str_replace('App\\', '', $class);
            $path = str_replace('\\', '/', $path) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });
}

$container = require __DIR__ . '/../src/Infrastructure/DI/container.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Basic CORS for local dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

try {
    $controller = new OrderController(
        $container->get(CreateOrderHandler::class),
        $container->get(GetOrderHandler::class)
    );

    if ($method === 'POST' && $path === '/orders') {
        [$status, $payload] = $controller->create();
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/orders/([A-Za-z0-9\-]+)$#', $path, $m)) {
        [$status, $payload] = $controller->show($m[1]);
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}
