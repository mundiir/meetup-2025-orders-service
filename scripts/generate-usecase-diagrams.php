#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script to automatically generate PlantUML sequence diagrams for each UseCase
 * in the Application layer.
 * 
 * This script:
 * 1. Scans src/Application/UseCase for all UseCases
 * 2. Generates a PlantUML sequence diagram for each UseCase
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

// Generate sequence diagram for each UseCase
foreach ($useCases as $useCase) {
    $useCasePath = $useCaseDir . '/' . $useCase;
    $handlerFile = $useCasePath . '/' . $useCase . 'Handler.php';
    
    if (!file_exists($handlerFile)) {
        echo "Warning: Handler file not found for {$useCase}, skipping.\n";
        continue;
    }
    
    // Read the handler file to extract information
    $handlerContent = file_get_contents($handlerFile);
    
    // Extract class name and dependencies from constructor
    preg_match('/class\s+(\w+Handler)/', $handlerContent, $classMatches);
    $handlerClass = $classMatches[1] ?? $useCase . 'Handler';
    
    // Extract constructor dependencies
    $dependencies = [];
    if (preg_match('/public\s+function\s+__construct\s*\((.*?)\)/s', $handlerContent, $constructorMatches)) {
        $constructorParams = $constructorMatches[1];
        // Parse each parameter
        preg_match_all('/private\s+readonly\s+(\w+)\s+\$(\w+)/s', $constructorParams, $paramMatches, PREG_SET_ORDER);
        foreach ($paramMatches as $match) {
            $type = $match[1];
            $name = $match[2];
            $dependencies[] = ['type' => $type, 'name' => $name];
        }
    }
    
    // Generate PlantUML sequence diagram
    $diagramFile = $docsDir . '/sequence-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $useCase)) . '.puml';
    
    // Only generate if the file doesn't exist (don't overwrite hand-crafted diagrams)
    if (!file_exists($diagramFile)) {
        $puml = generateSequenceDiagram($useCase, $handlerClass, $dependencies);
        file_put_contents($diagramFile, $puml);
        echo "Generated: {$diagramFile}\n";
    } else {
        echo "Skipped (already exists): {$diagramFile}\n";
    }
}

// Update c4-component.puml to link to the generated diagrams
updateC4Component($c4ComponentFile, $useCases);

echo "Done!\n";

/**
 * Generate a PlantUML sequence diagram for a UseCase
 */
function generateSequenceDiagram(string $useCase, string $handlerClass, array $dependencies): string
{
    $useCaseReadable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $useCase);
    $commandClass = $useCase . 'Command';
    
    $puml = "@startuml\n";
    $puml .= "' {$useCaseReadable} Use Case — Application Layer\n\n";
    $puml .= "actor Client\n";
    $puml .= "participant \"Controller\" as Controller\n";
    $puml .= "participant \"{$handlerClass}\" as Handler\n";
    
    // Add participants for each dependency
    foreach ($dependencies as $dep) {
        $puml .= "participant {$dep['type']} as {$dep['name']}\n";
    }
    
    $puml .= "\n";
    $puml .= "Client -> Controller: Request\n";
    $puml .= "Controller -> Handler: __invoke({$commandClass})\n";
    $puml .= "activate Handler\n\n";
    
    // Add basic flow with dependencies
    foreach ($dependencies as $dep) {
        $puml .= "Handler -> {$dep['name']}: call operation\n";
        $puml .= "activate {$dep['name']}\n";
        $puml .= "{$dep['name']} --> Handler: result\n";
        $puml .= "deactivate {$dep['name']}\n\n";
    }
    
    $puml .= "Handler --> Controller: Response\n";
    $puml .= "deactivate Handler\n";
    $puml .= "Controller --> Client: Result\n\n";
    
    $puml .= "footer [[https://mundiir.github.io/meetup-2025-orders-service/c4-component.svg Back to C4 Component]]\n\n";
    $puml .= "@enduml\n";
    
    return $puml;
}

/**
 * Update the c4-component.puml file to link to generated sequence diagrams
 */
function updateC4Component(string $c4File, array $useCases): void
{
    if (!file_exists($c4File)) {
        echo "Warning: C4 component file not found: {$c4File}\n";
        return;
    }
    
    $content = file_get_contents($c4File);
    $lines = explode("\n", $content);
    $newLines = [];
    $useCaseComponentsFound = [];
    $insertAfterAPI = false;
    $apiLineIndex = -1;
    
    // First pass: identify existing UseCase components and API component line
    foreach ($lines as $i => $line) {
        if (preg_match('/Component\(orders_api,/', $line)) {
            $apiLineIndex = $i;
        }
        foreach ($useCases as $useCase) {
            $useCaseReadable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $useCase);
            if (preg_match('/Component\([^,]+,\s*"' . preg_quote($useCaseReadable, '/') . '\s+Use\s+Case"/', $line)) {
                $useCaseComponentsFound[$useCase] = true;
                // Update the link
                $sequenceFile = 'sequence-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $useCase)) . '.svg';
                $lines[$i] = preg_replace('/\$link="[^"]*"/', '$link="' . $sequenceFile . '"', $line);
            }
        }
    }
    
    // Second pass: add missing UseCase components after API component
    $componentIndex = 0;
    foreach ($lines as $i => $line) {
        $newLines[] = $line;
        
        // After the API component, add any missing UseCase components
        if ($i === $apiLineIndex) {
            foreach ($useCases as $useCase) {
                if (!isset($useCaseComponentsFound[$useCase])) {
                    $componentIndex++;
                    $useCaseReadable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $useCase);
                    $sequenceFile = 'sequence-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $useCase)) . '.svg';
                    $componentId = 'orders_uc' . $componentIndex;
                    $newLines[] = '  Component(' . $componentId . ', "' . $useCaseReadable . ' Use Case", "Application Service", $link="' . $sequenceFile . '")';
                    echo "Added component: {$useCaseReadable} Use Case\n";
                }
            }
        }
    }
    
    $content = implode("\n", $newLines);
    file_put_contents($c4File, $content);
    echo "Updated: {$c4File}\n";
}
