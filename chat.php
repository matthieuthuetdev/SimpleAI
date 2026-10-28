<?php

declare(strict_types=1);

use SimpleAI\OpenAI;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Dépendances manquantes. Lancez composer install.\n");
    exit(1);
}

require_once $autoloadPath;

function readLocalConfiguration(string $path): array
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        throw new RuntimeException('Fichier .env.local introuvable ou illisible.');
    }

    $configuration = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name !== 'OPENAI_API_KEY' && $name !== 'AI_MODEL') {
            continue;
        }

        if (strlen($value) >= 2 && $value[0] === $value[strlen($value) - 1]) {
            $quote = $value[0];

            if ($quote === '"' || $quote === "'") {
                $value = substr($value, 1, -1);
            }
        }

        $configuration[$name] = $value;
    }

    return $configuration;
}

try {
    $configuration = readLocalConfiguration(__DIR__ . '/.env.local');
    $apiKey = $configuration['OPENAI_API_KEY'] ?? '';
    $model = $configuration['AI_MODEL'] ?? '';

    if ($apiKey === '' || $model === '') {
        throw new RuntimeException('Renseignez OPENAI_API_KEY et AI_MODEL dans .env.local.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Configuration invalide : ' . $exception->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "SimpleAI - entrez q, quit ou exit pour terminer.\n");

while (true) {
    fwrite(STDOUT, "\nVotre question : ");
    $question = fgets(STDIN);

    if ($question === false) {
        break;
    }

    $question = trim($question);

    if (in_array(strtolower($question), ['q', 'quit', 'exit'], true)) {
        break;
    }

    if ($question === '') {
        continue;
    }

    try {
        $answer = OpenAI::ask($question, $model, $apiKey);
        fwrite(STDOUT, "\nRéponse :\n" . $answer . "\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "\nErreur : " . $exception->getMessage() . "\n");
    }
}

fwrite(STDOUT, "Au revoir.\n");
