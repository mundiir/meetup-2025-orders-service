# Copilot Coding Agent Onboarding

Trust these instructions first. Only search the repo if information is missing or appears incorrect.

## 1. Purpose & Summary
A microservice (PHP 8.1+) in Clean Architecture style exposing. 
It demonstrates domain-driven layering (Domain, Application, Infrastructure, Presentation) and simple integration patterns: payment gateway (stub / external), FX conversion, promo discounts, risk checks, persistence (SQLite or in‑memory), and auto‑generated PlantUML architecture & sequence diagrams.

## 2. Tech & Runtime
- Language: PHP (tested with 8.2; requires >=8.1).
- Dependencies: Very small; prod requires only ext-pdo (for SQLite). Dev adds phpunit.
- No framework; custom DI container and routing using built‑in PHP server.
- Diagrams: PlantUML (optional CLI) processed in CI via `Timmy/plantuml-action`.
- No formal linter config present; rely on `php -l` (syntax) + tests.

Approximate size: Small (<150 source PHP files). Fast test cycle (<1s).

## 3. Key Runtime Behavior & Domain Rules
- Supported currencies: USD, EUR, UAH.
- Discount applied first (0–100%). FX conversion to charge currency USD. Risk rejection if charged > 100000 cents (USD) or > 1_500_000 cents (UAH). FREE100 promo yields zero charged amount (skips payment gateway).
- Payment retry: up to 3 attempts on transient failures with exponential backoff (≈50ms, 100ms).
- Persistence: Attempts SQLite (var/orders.sqlite with WAL + busy timeout). Falls back to in‑memory repository if PDO/SQLite unavailable. Always safe to run without DB.
- Environment variable: `PAYMENT_BASE_URI` (configure real Payment service). Absent ⇒ local stub mode.

## 4. Project Layout (Important Paths)
- `public/index.php` – Entry point, HTTP controller wiring, autoload fallback.
- `public/router.php` – Built‑in server router.
- `src/Infrastructure/DI/container.php` – Minimal service factory mapping (treat as composition root).
- `src/Application/UseCase/CreateOrder/*` – Command & Handler.
- `src/Application/UseCase/GetOrder/*` – Query & Handler.
- `src/Presentation/Http/OrderController.php` – HTTP adapter (create/show).
- `src/Domain/Entity/Order.php` – Aggregate root.
- `src/Domain/Event/OrderCreated.php` – Use case output event.
- `src/Domain/Repository/OrderRepository.php` & implementations in `src/Infrastructure/Persistence/*`.
- `scripts/smoke.php` – CLI quick functional test (creates one order, prints JSON).
- `scripts/generate-usecase-diagrams.php` – Auto‑generates PlantUML sequence `.puml` per UseCase (non‑destructive; skips existing files) and updates `docs/c4-component.puml` links.
- `generate.sh` – Optional local batch rendering of all `.puml` to `.svg` if PlantUML CLI installed.
- `phpunit.xml.dist` – Test configuration (boots vendor/autoload.php).
- `composer.json` – PSR‑4: `App\` maps to `src/`; scripts: `test`, `serve`.
- `docs/*.puml|*.svg` – Architecture & sequence diagrams.

## 5. Build, Bootstrap, Run, Test, Diagrams
No compile build. Steps are deterministic:

Always run `composer install` before tests, adding dependencies, or after editing composer.json.

Bootstrap (fresh clone):
1. Ensure PHP ≥8.1 and ext-pdo (optional; absence ⇒ in‑memory repository). Command check: `php -v`.
2. Install dev deps: `composer install --no-interaction --prefer-dist` (≈seconds; creates vendor/). Works even without SQLite.

Serve (HTTP API):
```
php -S 127.0.0.1:8080 -t public public/router.php
```
- Use `public/router.php` so static assets served automatically. Test with: `curl -i -X POST http://127.0.0.1:8080/orders -d '{"amountCents":1999,"currency":"USD"}' -H 'Content-Type: application/json'`.

Run Smoke (CLI quick validation):
```
php scripts/smoke.php
```
Expected: JSON with orderId, amountCents, currency, occurredAt.

Tests:
```
composer test
```
(Internally `phpunit --colors=always`). Observed run: 5 tests, ~0.17s, 14 assertions, all green. Direct alternative: `vendor/bin/phpunit -c phpunit.xml.dist`.
Preconditions: vendor/autoload.php must exist ⇒ run composer install first.

Syntax (optional fast lint):
```
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

Diagrams (local manual):
1. Generate new UseCase sequence templates:
```
php scripts/generate-usecase-diagrams.php
```
2. Render all PlantUML to SVG (requires `plantuml` CLI):
```
./generate.sh
```
If plantuml absent, script prints advisory but is otherwise non‑fatal.

## 6. CI / Workflows
Two GitHub Actions workflows:
- `generate-diagrams-pr.yml` (pull_request): For changes in UseCase or docs paths – runs PHP 8.1, executes `php scripts/generate-usecase-diagrams.php`, renders diagrams with PlantUML action, auto‑commits any new/updated `.puml`/`.svg` back to PR branch.
- `diagrams.yml` (push to main/master): Renders all `.puml` to `.svg` and publishes `docs/` via GitHub Pages.

No test workflow currently present ⇒ Agents must run tests locally before proposing changes.
Concurrency group used for pages deployment prevents overlapping publishes.

## 7. Making Changes Safely
Follow this order:
1. Implement code (e.g. new UseCase directory under `src/Application/UseCase/YourName`). Provide `YourNameCommand.php` + `YourNameHandler.php` (constructor injection similar to existing handlers).
2. Wire in DI: add match arm in `container.php` for new handler and any new services.
3. Expose HTTP: extend `OrderController` or add a new controller file + route logic in `public/index.php` (keep JSON output; handle errors with proper HTTP codes). Maintain autoload path mapping.
4. Run: `composer install` (if dependencies changed), then `composer test`, then `php scripts/smoke.php` or curl the endpoint.
5. Generate diagrams: `php scripts/generate-usecase-diagrams.php` then `./generate.sh` (optional). Commit updated docs.
6. Re-run tests to ensure green before pushing.

## 8. Error Handling & Edge Cases
- Missing ext-pdo/SQLite: container falls back silently to InMemoryOrderRepository (acceptable for tests & smoke).
- Invalid currency or amount ≤0: Exceptions surfaced to HTTP with 4xx codes.
- Transient payment failures: Handler retries automatically; ensure mocks simulate exceptions to test retry logic.
- Free order (100% discount): Payment gateway NOT invoked; expect `transactionId` starting `free_`.

## 9. Dependencies & Adding Libraries
- Add new libs by editing `composer.json` require / require-dev, then `composer update <package>` (commit composer.lock). Keep PHP constraint `>=8.1` unless raising deliberately.
- PSR-4: Keep `App\` root; do not change unless adjusting autoload discovery.

## 10. Validation Checklist Before PR
Always:
- composer install
- composer test (all green)
- php scripts/smoke.php (valid JSON output)
- Optional: curl HTTP endpoint
- If UseCases changed: regenerate diagrams
- If diagrams changed: commit updated `.puml` + `.svg`

## 11. Root Files Quick List
Important root files: `README.md`, `composer.json`, `composer.lock`, `phpunit.xml.dist`, `public/index.php`, `public/router.php`, `src/**`, `scripts/*`, `docs/*`, `var/orders.sqlite` (created at runtime), `.github/workflows/*.yml`.

## 12. When to Search
Search only if:
- Adding a new concept not documented here (e.g., additional infrastructure adapter).
- Instructions conflict with observed errors.
Otherwise trust this document to minimize redundant exploration.

## 13. Common Commands (Copy/Paste)
```
# Full local setup
composer install
composer test
php scripts/smoke.php
php -S 127.0.0.1:8080 -t public public/router.php

# Diagrams
php scripts/generate-usecase-diagrams.php
./generate.sh
```

## 14. Performance Notes
- Tests: <0.2s typical.
- Diagram generation: fast (string processing only); PlantUML render speed depends on CLI (~1–3s for all diagrams).

## 15. Non-Goals
- No authentication/authorization.
- No queueing or external persistence other than SQLite.
- No framework (avoid adding heavy dependencies unless justified).

Proceed with confidence; keep changes minimal, verified, and documented in PR descriptions when altering architecture.

## 16. Sequence Diagram Rules (Standardized & Abstract)
Purpose
- Communicate behavior to stakeholders without requiring source code reading.
- Favor architectural patterns while retaining real code names where meaningful.

New Requirements (additive)
- Exception Handling: Show all meaningful exceptions explicitly (UnsupportedCurrencyException, OrderRejectedException, TransientPaymentException, OrderNotFoundException). Use alt blocks named with the exception. Application arrows carry DOMAIN errors only (class name / semantic), never HTTP codes.
- Application↔Domain Interaction: Always depict when the Application layer invokes domain creation or significant domain logic (e.g., Order::create). If Domain only supplies trivial getters with no transformation, you may omit the Domain participant (legend still lists Domain color).
- HTTP Protocol: HTTP verbs/paths appear only in the Presentation layer messages (Client → Controller). HTTP status codes are produced ONLY by the Presentation layer. Application layer does NOT emit HTTP errors.
- Persistence Classification: Persistence (Repository + Data Store) is part of Infrastructure. Use Infrastructure color for repository and external storage; no separate Persistence layer color.
- External Services: PaymentGateway (Infrastructure color) and PromoService (External blue) reside outside the service box. Ports inside call these external participants.
- Ports depiction (required): Show Application → Port (inside) then Port/Adapter → External service (outside). Messages labeled with port operations.
- 3rd‑party Errors: Errors from external services are converted into Domain (or Domain-relevant) exceptions at the Application layer before reaching Presentation. Diagrams show external failure returning into port, port returning error to Application, Application raising Domain exception to Presentation, then Presentation mapping to HTTP code.

Participants (via color legend; no layer words in labels)
- Presentation: #C7F9CC (Controllers, AMQP listeners, other entry points)
- Application: #FFF3B0 (UseCases, Handlers)
- Domain: #E8EAED (Entities, Aggregates) – OPTIONAL
- Infrastructure (Adapters/Persistence): #FFD6A5 (Ports, Repositories, internal adapters)
- External Actor/System: #AEE3FF (Client, 3rd‑party, other external systems and microservices)

Style
- Use activations.
- Alternative flows for each exception branch (Domain Exceptions).
- Retry loops show max attempts (up to 3) and final domain exception conversion.
- Application never generates HTTP status codes to its messages—Presentation maps domain exception to HTTP status.

Legend (colored cells)
  legend left
    | Layer | Sample |
    | Presentation | <#C7F9CC> |
    | Application | <#FFF3B0> |
    | Domain | <#E8EAED> |
    | Infrastructure (Adapters/Persistence) | <#FFD6A5> |
    | External Actor/System | <#AEE3FF> |
  endlegend

Example (Create Order excerpt – domain vs HTTP separation)

PlantUML illustrative subset:
```
@startuml
autonumber
actor Client #AEE3FF

box "Orders Service" #DDDDDD
  participant OrderController #C7F9CC
  participant CreateOrderHandler #FFF3B0
  participant Order #E8EAED
  participant OrderRepository #FFD6A5
  participant FxConverter #FFD6A5
  participant RiskChecker #FFD6A5
end box

' External services / actors (outside service box)
participant PromoService #AEE3FF
participant PaymentGateway #FFD6A5
participant "Promo 3rd‑party" as PromoAPI #AEE3FF
participant "Payment Service" as PaymentAPI #AEE3FF
participant "Data Store" as DataStore #FFD6A5

Client -> OrderController: POST /orders (Create Order)
OrderController -> CreateOrderHandler: Execute
activate CreateOrderHandler

CreateOrderHandler -> Order: create(amount, currency)
alt UnsupportedCurrencyException
  CreateOrderHandler -[#FFF3B0]-> OrderController: UnsupportedCurrencyException
  OrderController --> Client: 400 Unsupported currency
else Valid currency
  CreateOrderHandler -> PromoService: getDiscountPercent (0–100%)
  PromoService -> PromoAPI: request
  PromoAPI --> PromoService: percent
  PromoService --> CreateOrderHandler: discountPercent
  CreateOrderHandler -> FxConverter: convertToUSD(amountAfterDiscount)
  FxConverter --> CreateOrderHandler: chargedAmountUSD
  CreateOrderHandler -> RiskChecker: assess(chargedAmountUSD)
  RiskChecker --> CreateOrderHandler: allowed?
  alt OrderRejectedException
    CreateOrderHandler -[#FFF3B0]-> OrderController: OrderRejectedException
    OrderController --> Client: 422 Order rejected
  else Accepted
    alt Free order (chargedAmountUSD == 0)
      CreateOrderHandler -> CreateOrderHandler: transactionId = free_* (skip payment)
    else Paid order
      loop Retry up to 3
        CreateOrderHandler -> PaymentGateway: charge(chargedAmountUSD)
        PaymentGateway -> PaymentAPI: charge
        alt TransientPaymentException (attempt < 3)
          PaymentAPI --> PaymentGateway: transient error
          PaymentGateway --> CreateOrderHandler: transient failure
        else Success
          PaymentAPI --> PaymentGateway: transactionId
          PaymentGateway --> CreateOrderHandler: transactionId
          break
        end
      end
      alt TransientPaymentException (after 3 attempts)
        CreateOrderHandler -[#FFF3B0]-> OrderController: TransientPaymentException
        OrderController --> Client: 503 Payment failed
      else Charged
        CreateOrderHandler -> OrderRepository: save(order)
        OrderRepository -> DataStore: write
        DataStore --> OrderRepository: ok
        OrderRepository --> CreateOrderHandler: ok
      end
    end
    CreateOrderHandler -[#FFF3B0]-> OrderController: OrderCreated (domain event)
    OrderController --> Client: 201 Created
  end
end
@enduml
```

Note:
- Exception alt blocks named with Domain exception classes; Application emits domain exception to Presentation; Presentation maps to HTTP.
- Colored exception arrows use source layer color (#FFF3B0 for Application).
- External services (PromoService, PaymentGateway, PaymentAPI, PromoAPI) are outside the service box; ports are implied by handler calling them.
- Domain event emission is separate from HTTP response.

legend left
  | Layer | Sample |
  | Presentation | <#C7F9CC> |
  | Application | <#FFF3B0> |
  | Domain | <#E8EAED> |
  | Infrastructure (Adapters/Persistence) | <#FFD6A5> |
  | External Actor/System | <#AEE3FF> |
endlegend

Quality & Evolution
- Keep domain errors distinct from HTTP responses.
- Introduce new domain exceptions as needed and update diagrams; Presentation layer alone maps them to HTTP.

## 17. C4 Component Diagram Rules (Standardized)
Purpose
- Show internal components and their adapters/ports, and how they interact with external systems/containers.
- Provide navigation between C4 levels and related diagrams.

Requirements
- Footer: Component diagram includes a footer link back to the C4 Container diagram.
  - Footer target: https://mundiir.github.io/meetup-2025-landscape/c4-containers.svg
- External dependencies: Place external systems around the Orders Service boundary (outside System_Boundary).
  - Examples: Payment Service, Promo Service API, SQLite (as external DB container for persistence).
- Adapters communicate to external containers: model relations from internal ports/adapters to those external containers (HTTP or SQL labels as appropriate).
- UseCases link to sequence diagrams: internal UseCase components must have $link attributes pointing to their sequence diagram SVGs (e.g., sequence-create-order.svg).
- Sequence diagrams return link: each sequence diagram includes a footer link back to the C4 Component diagram (docs/c4-component.svg).
- Data store to ERD: the external data store component (SQLite) must include a $link to er.svg so viewers can navigate to the Entity-Relationship diagram.

Implementation Hints (PlantUML C4)
- Use System_Boundary(...) for the service; define Component(...) inside for UseCases and adapters/ports.
- Define external systems with System_Ext(...) and SystemDb_Ext(...), placed outside the boundary.
- Use Rel(source, target, "label") to show adapter→external relations.
- Use $link="<svg>" on Component/System elements to wire navigation (UseCases→sequence, SQLite→er.svg).
- Keep the footer backlink to the container diagram in c4-component.puml.

Quality & Evolution
- Keep the diagram navigable: ensure links resolve after CI publishes docs/ to Pages.
- When new UseCases or adapters are added, update components, external relations, and links.
