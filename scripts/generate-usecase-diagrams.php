#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script to automatically generate PlantUML sequence diagrams for each UseCase
 * in the Application layer.
 *
 * This script:
 * 1. Scans src/Application/UseCase for all UseCases
 * 2. Generates a PlantUML sequence diagram for each UseCase (non-destructive)
 *    using real code names with Clean Architecture layers conveyed by colors + legend
 * 3. Updates the c4-component.puml to link to the generated diagrams
 */

$projectRoot = dirname(__DIR__);
$useCaseDir = $projectRoot . '/src/Application/UseCase';
$docsDir = $projectRoot . '/docs';
$c4ComponentFile = $docsDir . '/c4-component.puml';

// Find all UseCase directories
$useCases = [];
if (is_dir($useCaseDir)) {
    $dirs = scandir($useCaseDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        $path = $useCaseDir . '/' . $dir;
        if (is_dir($path)) {
            $useCases[] = $dir;
        }
    }
}

if (empty($useCases)) {
    echo "No UseCases found in {$useCaseDir}\n";
    exit(0);
}

echo "Found " . count($useCases) . " UseCase(s): " . implode(', ', $useCases) . "\n";

// Generate sequence diagram for each UseCase (non-destructive: do not overwrite existing files)
foreach ($useCases as $useCase) {
    $useCasePath = $useCaseDir . '/' . $useCase;
    $handlerFile = $useCasePath . '/' . $useCase . 'Handler.php';

    if (!file_exists($handlerFile)) {
        echo "Warning: Handler file not found for {$useCase}, skipping.\n";
        continue;
    }

    $handlerContent = file_get_contents($handlerFile);

    preg_match('/class\s+(\w+Handler)/', $handlerContent, $classMatches);
    $handlerClass = $classMatches[1] ?? $useCase . 'Handler';

    $dependencies = [];
    if (preg_match('/public\s+function\s+__construct\s*\((.*?)\)/s', $handlerContent, $constructorMatches)) {
        $constructorParams = $constructorMatches[1];
        preg_match_all('/private\s+readonly\s+(\\?\w+(?:\\\\\w+)*)\s+\$(\w+)/s', $constructorParams, $paramMatches, PREG_SET_ORDER);
        foreach ($paramMatches as $match) {
            $type = preg_replace('/^.*\\\\/', '', $match[1]); // short class name
            $name = $match[2];
            $dependencies[] = ['type' => $type, 'name' => $name];
        }
    }

    $diagramFile = $docsDir . '/sequence-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $useCase)) . '.puml';

    if (!file_exists($diagramFile)) {
        $puml = generateRealNamesDiagram($useCase, $handlerClass, $dependencies);
        file_put_contents($diagramFile, $puml);
        echo "Generated: {$diagramFile}\n";
    } else {
        echo "Skipped (already exists): {$diagramFile}\n";
    }
}

updateC4Component($c4ComponentFile, $useCases);

echo "Done!\n";

/**
 * Generate a sequence diagram with real code names and layer colors + legend
 */
function generateRealNamesDiagram(string $useCase, string $handlerClass, array $dependencies): string
{
    $useCaseReadable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $useCase);
    $showDomain = !preg_match('/^(Get|List)/i', $useCase);

    $infraInside = [];
    $repoInside = [];
    $paymentOutside = false;
    $promoOutside = false;
    foreach ($dependencies as $dep) {
        $type = $dep['type'];
        if (stripos($type, 'Repository') !== false) {
            if (!in_array('OrderRepository', $repoInside, true)) {
                $repoInside[] = $type;
            }
            continue;
        }
        if (strcasecmp($type, 'PaymentGateway') === 0) {
            $paymentOutside = true;
            // don't add to $infraInside here; we'll add as a port explicitly inside the box
            continue;
        }
        if (strcasecmp($type, 'PromoService') === 0) {
            $promoOutside = true;
            // don't add to $infraInside here; we'll add as a port explicitly inside the box
            continue;
        }
        if (!in_array($type, $infraInside, true)) {
            $infraInside[] = $type; // FxConverter, RiskChecker, etc.
        }
    }

    $puml = "@startuml\n";
    $puml .= "' {$useCaseReadable} — Sequence (real code names; ports + two-hop; colored legend)\n";
    $puml .= "autonumber\n\n";
    $puml .= "actor Client #AEE3FF\n\n";

    $puml .= "box \"Orders Service\" #DDDDDD\n";
    $puml .= "  participant OrderController #C7F9CC\n";
    $puml .= "  participant {$handlerClass} #FFF3B0\n";
    if ($showDomain && stripos($useCase, 'Order') !== false) {
      $puml .= "  participant Order #E8EAED\n";
    }
    if (!empty($repoInside)) {
      $puml .= "  participant OrderRepository #FFD6A5\n";
    }
    foreach ($infraInside as $svc) {
      $puml .= "  participant {$svc} #FFD6A5\n";
    }
    if ($promoOutside) {
      $puml .= "  participant PromoService #FFD6A5\n"; // Port inside
    }
    if ($paymentOutside) {
      $puml .= "  participant PaymentGateway #FFD6A5\n"; // Port inside
    }
    $puml .= "end box\n\n";

    if ($promoOutside) {
      $puml .= "participant \"Promo 3rd‑party\" as PromoAPI #AEE3FF\n";
    }
    if (!empty($repoInside)) {
      $puml .= "participant \"Data Store\" as DataStore #FFD6A5\n";
    }
    if ($paymentOutside) {
      $puml .= "participant \"Payment Service\" as PaySvc #FFD6A5\n\n";
    } else {
      $puml .= "\n";
    }

    // Entry message with endpoint hint
    $endpoint = (strcasecmp($useCase, 'CreateOrder') === 0) ? 'POST /orders' : ((strcasecmp($useCase, 'GetOrder') === 0) ? 'GET /orders/{id}' : '/endpoint');
    $action = (strcasecmp($useCase, 'CreateOrder') === 0) ? 'Create Order' : ((strcasecmp($useCase, 'GetOrder') === 0) ? 'Retrieve Order' : $useCaseReadable);

    $puml .= "Client -> OrderController: {$endpoint} ({$action})\n";
    $puml .= "OrderController -> {$handlerClass}: Execute\n";
    $puml .= "activate {$handlerClass}\n\n";

    $puml .= "{$handlerClass} -> {$handlerClass}: Validate input\n";
    if (strcasecmp($useCase, 'CreateOrder') === 0) {
        $puml .= "alt UnsupportedCurrencyException\n";
        $puml .= "  {$handlerClass} -[#FFF3B0]-> OrderController: 400 Unsupported currency\n";
        $puml .= "  deactivate {$handlerClass}\n";
        $puml .= "  return\n";
        $puml .= "else Valid currency\n";
        if ($showDomain) {
          $puml .= "  {$handlerClass} -> Order: create(amount, currency)\n";
        }
        $puml .= "end\n\n";
    } else {
        $puml .= "alt Invalid input (4xx)\n";
        $puml .= "  {$handlerClass} -[#FFF3B0]-> OrderController: Error response\n";
        $puml .= "  deactivate {$handlerClass}\n";
        $puml .= "  return\n";
        $puml .= "end\n\n";
    }

    if (strcasecmp($useCase, 'CreateOrder') === 0) {
        if ($promoOutside) {
          $puml .= "{$handlerClass} -> PromoService: getDiscountPercent (0–100%)\n";
          $puml .= "activate PromoService\n";
          $puml .= "PromoService -> PromoAPI: request\n";
          $puml .= "PromoAPI --> PromoService: percent\n";
          $puml .= "PromoService --> {$handlerClass}: discountPercent\n";
          $puml .= "deactivate PromoService\n";
          $puml .= "{$handlerClass} -> {$handlerClass}: apply discount\n\n";
        }
        if (in_array('FxConverter', $infraInside, true)) {
          $puml .= "{$handlerClass} -> FxConverter: convert amount\n";
          $puml .= "activate FxConverter\n";
          $puml .= "FxConverter --> {$handlerClass}: convertedAmount\n";
          $puml .= "deactivate FxConverter\n\n";
        }
        if (in_array('RiskChecker', $infraInside, true)) {
          $puml .= "{$handlerClass} -> RiskChecker: assess risk\n";
          $puml .= "activate RiskChecker\n";
          $puml .= "RiskChecker --> {$handlerClass}: allow?\n";
          $puml .= "deactivate RiskChecker\n\n";
        }
        $puml .= "alt OrderRejectedException\n";
        $puml .= "  {$handlerClass} -[#FFF3B0]-> OrderController: 422 Order rejected\n";
        $puml .= "  deactivate {$handlerClass}\n";
        $puml .= "  return\n";
        $puml .= "else Accepted\n";
        $puml .= "  alt Free order\n";
        $puml .= "    {$handlerClass} -> {$handlerClass}: transactionId = free_* (skip payment)\n";
        $puml .= "  else Paid order\n";
        if ($paymentOutside) {
          $puml .= "    loop Retry up to 3 attempts\n";
          $puml .= "      {$handlerClass} -> PaymentGateway: charge\n";
          $puml .= "      activate PaymentGateway\n";
          $puml .= "      PaymentGateway -> PaySvc: charge\n";
          $puml .= "      alt TransientPaymentException (attempt < 3)\n";
          $puml .= "        PaySvc --> PaymentGateway: transient error\n";
          $puml .= "        PaymentGateway --> {$handlerClass}: error\n";
          $puml .= "        deactivate PaymentGateway\n";
          $puml .= "      else Success\n";
          $puml .= "        PaySvc --> PaymentGateway: transactionId\n";
          $puml .= "        PaymentGateway --> {$handlerClass}: transactionId\n";
          $puml .= "        deactivate PaymentGateway\n";
          $puml .= "        break\n";
          $puml .= "      end\n";
          $puml .= "    end\n";
          $puml .= "    alt TransientPaymentException (after 3 attempts)\n";
          $puml .= "      {$handlerClass} -[#FFF3B0]-> OrderController: 503 Payment failed\n";
          $puml .= "      deactivate {$handlerClass}\n";
          $puml .= "      return\n";
          $puml .= "    else Payment succeeded\n";
        }
        if (!empty($repoInside)) {
          $puml .= "      {$handlerClass} -> OrderRepository: save(order)\n";
          $puml .= "      activate OrderRepository\n";
          $puml .= "      OrderRepository -> DataStore: write\n";
          $puml .= "      DataStore --> OrderRepository: stored\n";
          $puml .= "      OrderRepository --> {$handlerClass}: ok\n";
          $puml .= "      deactivate OrderRepository\n";
        }
        $puml .= "    end\n";
        $puml .= "  end\n";
        $puml .= "end\n\n";

        $puml .= "note over {$handlerClass}\n";
        $puml .= "OrderCreated event: ids, amounts, currency, discount (0–100), transactionId, timestamps\n";
        $puml .= "end note\n\n";

        $puml .= "{$handlerClass} --> OrderController: 201 Created\n";
        $puml .= "OrderController --> Client: 201 Created\n\n";
    } elseif (preg_match('/^(Get|List)/i', $useCase)) {
        if (!empty($repoInside)) {
          $puml .= "{$handlerClass} -> OrderRepository: findById(id)\n";
          $puml .= "activate OrderRepository\n";
          $puml .= "OrderRepository -> DataStore: read\n";
          $puml .= "DataStore --> OrderRepository: Order | null\n";
          $puml .= "OrderRepository --> {$handlerClass}: Order | null\n";
          $puml .= "deactivate OrderRepository\n\n";
        }
        $puml .= "alt OrderNotFoundException (404)\n";
        $puml .= "  {$handlerClass} -[#FFF3B0]-> OrderController: Not Found\n";
        $puml .= "  deactivate {$handlerClass}\n";
        $puml .= "  OrderController --> Client: 404 Not Found\n";
        $puml .= "  return\n";
        $puml .= "else Found (200)\n";
        $puml .= "  {$handlerClass} -> {$handlerClass}: Map Order → DTO\n";
        $puml .= "  {$handlerClass} --> OrderController: DTO\n";
        $puml .= "  deactivate {$handlerClass}\n";
        $puml .= "  OrderController --> Client: 200 OK (JSON)\n";
        $puml .= "end\n\n";
    } else {
        foreach ($infraInside as $svc) {
            $puml .= "{$handlerClass} -> {$svc}: call\n";
            $puml .= "activate {$svc}\n";
            $puml .= "{$svc} --> {$handlerClass}: result\n";
            $puml .= "deactivate {$svc}\n\n";
        }
        if (!empty($repoInside)) {
            $puml .= "{$handlerClass} -> OrderRepository: save/update\n";
            $puml .= "activate OrderRepository\n";
            $puml .= "OrderRepository -> DataStore: write\n";
            $puml .= "DataStore --> OrderRepository: stored\n";
            $puml .= "OrderRepository --> {$handlerClass}: ok\n";
            $puml .= "deactivate OrderRepository\n\n";
        }
        $puml .= "{$handlerClass} --> OrderController: Response\n\n";
    }

    $puml .= "legend left\n";
    $puml .= "  | Layer | Sample |\n";
    $puml .= "  | Presentation | <#C7F9CC> |\n";
    $puml .= "  | Application | <#FFF3B0> |\n";
    $puml .= "  | Domain | <#E8EAED> |\n";
    $puml .= "  | Infrastructure (Adapters/Persistence/3rd Party) | <#FFD6A5> |\n";
    $puml .= "  | External | <#AEE3FF> |\n";
    $puml .= "endlegend\n\n";

    $puml .= "footer [[https://mundiir.github.io/meetup-2025-orders-service/c4-component.svg Back to C4 Component]]\n";
    $puml .= "@enduml\n";

    return $puml;
}

/**
 * Update the c4-component.puml file to link to generated diagrams
 */
function updateC4Component(string $c4File, array $useCases): void
{
    if (!file_exists($c4File)) {
        echo "Warning: C4 component file not found: {$c4File}\n";
        return;
    }

    $content = file_get_contents($c4File);

    foreach ($useCases as $useCase) {
        $useCaseReadable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $useCase);
        $sequenceFile = 'sequence-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $useCase)) . '.svg';

        // Match any Component ID for this Use Case and update its $link to the generated SVG
        $pattern = '/Component\(([^,]+),\s*"' . preg_quote($useCaseReadable, '/') . '\s+Use\s+Case",\s*"Application Service",\s*\$link="[^"]*"\)/';
        $replacement = 'Component($1, "' . $useCaseReadable . ' Use Case", "Application Service", $link="' . $sequenceFile . '")';

        $content = preg_replace($pattern, $replacement, $content);
    }

    file_put_contents($c4File, $content);
    echo "Updated: {$c4File}\n";
}
