<?php

declare(strict_types=1);

use App\Application\UseCase\CreateOrder\CreateOrderCommand;
use App\Application\UseCase\CreateOrder\CreateOrderHandler;

// Autoload fallback (works without composer install)
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

$handler = $container->get(CreateOrderHandler::class);
$event = $handler(new CreateOrderCommand(1999, 'USD'));

$result = [
    'orderId' => (string)$event->orderId,
    'amountCents' => $event->amountCents,
    'currency' => $event->currency,
    'occurredAt' => $event->occurredAt->format(DATE_ATOM),
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

