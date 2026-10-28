<?php

declare(strict_types=1);

namespace SimpleAI;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use RuntimeException;

final class OpenAI
{
    private const ENDPOINT = 'https://api.openai.com/v1/';

    private static ?ClientInterface $httpClient = null;

    public static function ask(string $question, string $model, string $apiKey): string
    {
        if (trim($question) === '') {
            throw new \InvalidArgumentException('The question must not be empty.');
        }

        if (trim($model) === '') {
            throw new \InvalidArgumentException('The model must not be empty.');
        }

        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('The API key must not be empty.');
        }

        $client = self::$httpClient ?? new Client([
            'base_uri' => self::ENDPOINT,
            'timeout' => 30.0,
            'connect_timeout' => 10.0,
            'http_errors' => false,
        ]);

        try {
            $response = $client->request('POST', 'responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'input' => $question,
                ],
            ]);
        } catch (GuzzleException) {
            throw new RuntimeException('The OpenAI request failed due to a network error.');
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new RuntimeException(self::httpErrorMessage($statusCode));
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        if (!is_array($data)) {
            throw new RuntimeException('OpenAI returned an invalid response.');
        }

        if (($data['status'] ?? null) === 'incomplete') {
            throw new RuntimeException('OpenAI returned an incomplete response.');
        }

        if (($data['status'] ?? null) === 'failed') {
            throw new RuntimeException('OpenAI could not complete the response.');
        }

        $output = $data['output'] ?? null;

        if (!is_array($output)) {
            throw new RuntimeException('OpenAI returned no usable text.');
        }

        $text = '';

        foreach ($output as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (
                    is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)
                ) {
                    $text .= $content['text'];
                }
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('OpenAI returned no usable text.');
        }

        return $text;
    }

    private static function httpErrorMessage(int $statusCode): string
    {
        return match (true) {
            $statusCode === 401, $statusCode === 403 => 'OpenAI rejected the API credentials or permissions.',
            $statusCode === 400, $statusCode === 404 => 'OpenAI rejected the request. Check the request parameters and model.',
            $statusCode === 429 => 'OpenAI rate limit or quota exceeded.',
            $statusCode >= 500 => 'OpenAI server error.',
            default => 'OpenAI returned HTTP status ' . $statusCode . '.',
        };
    }
}
