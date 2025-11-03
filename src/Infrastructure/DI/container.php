<?php

declare(strict_types=1);

use App\Application\Port\PaymentGateway;
use App\Application\UseCase\CreateOrder\CreateOrderHandler;
use App\Domain\Repository\OrderRepository;
use App\Infrastructure\Http\GuzzlePaymentGateway;
use App\Infrastructure\Persistence\PdoOrderRepository;
use App\Infrastructure\Persistence\InMemoryOrderRepository;
use App\Application\Port\FxConverter;
use App\Application\Port\PromoService;
use App\Application\Port\RiskChecker;
use App\Infrastructure\Service\SimpleFxConverter;
use App\Infrastructure\Service\SimplePromoService;
use App\Infrastructure\Service\SimpleRiskChecker;

// simple, minimal container
return new class {
    private array $services = [];

    public function get(string $id): mixed
    {
        return $this->services[$id] ??= $this->make($id);
    }

    private function make(string $id): mixed
    {
        return match ($id) {
            \PDO::class => $this->createPdo(),
            OrderRepository::class => $this->createOrderRepository(),
            PaymentGateway::class => $this->createPaymentGateway(),
            FxConverter::class => new SimpleFxConverter(),
            PromoService::class => new SimplePromoService(),
            RiskChecker::class => new SimpleRiskChecker(),
            CreateOrderHandler::class => new CreateOrderHandler(
                $this->get(OrderRepository::class),
                $this->get(PaymentGateway::class),
                $this->get(FxConverter::class),
                $this->get(PromoService::class),
                $this->get(RiskChecker::class)
            ),
            default => throw new \RuntimeException("Unknown service: {$id}"),
        };
    }

    private function createPaymentGateway(): PaymentGateway
    {
        $baseUri = getenv('PAYMENT_BASE_URI') ?: null;
        return new GuzzlePaymentGateway($baseUri);
    }

    private function createOrderRepository(): OrderRepository
    {
        try {
            return new PdoOrderRepository($this->get(\PDO::class));
        } catch (\Throwable $e) {
            // Fallback for environments without pdo_sqlite
            return new InMemoryOrderRepository();
        }
    }

    private function createPdo(): \PDO
    {
        $dbDir = __DIR__ . '/../../../var';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $dsn = 'sqlite:' . $dbDir . '/orders.sqlite';
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 2000');
        return $pdo;
    }
};
