# meetup-2025-orders-service

A tiny PHP microservice in Clean Architecture style with a single Use Case: Create Order.

## Structure
- **Domain**: Entities, Value Objects, Repository interface, Domain Events
- **Application**: UseCase (Command + Handler), Ports (PaymentGateway)
- **Infrastructure**: Adapters (PDO repository, HTTP payment gateway stub), DI container
- **Interfaces**: HTTP controller (REST)
- **public**: Minimal front controller (POST /orders)
- **docs/diagrams**: PlantUML component, sequence, and ER diagrams

## Requirements
- PHP >= 8.1
- ext-pdo (pdo_sqlite recommended). If SQLite is not available, an in-memory repository is used.

## Install
- Optional (for Composer autoload):
  - `composer install`

## Run (HTTP server)
- `php -S 127.0.0.1:8080 -t public`

## API
- **POST /orders**
  - Body JSON: `{ "amountCents": 1999, "currency": "USD" }`
  - Response 201 JSON: `{ "orderId": "...", "amountCents": 1999, "currency": "USD", "occurredAt": "..." }`

## CLI smoke
- `php scripts/smoke.php`

## Notes
- public/index.php includes a PSR-4 autoload fallback so the service works even without Composer.
- The PaymentGateway adapter is a stub that simulates latency and always succeeds.
- Persistence defaults to SQLite (var/orders.sqlite). If unavailable, it falls back to an in-memory repository.
