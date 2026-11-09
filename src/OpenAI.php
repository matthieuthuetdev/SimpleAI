<?php

declare(strict_types=1);

namespace SimpleAI;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class OpenAI
{
    private const ENDPOINT = 'https://api.openai.com/v1/';

    private const IMAGE_MODEL = 'gpt-image-2.6-flare';

    private const TTS_MODEL = 'gpt-4o-mini-tts';

    private const VOICES = [
        'alloy',
        'ash',
        'ballad',
        'coral',
        'echo',
        'fable',
        'nova',
        'onyx',
        'sage',
        'shimmer',
        'verse',
        'marin',
        'cedar',
    ];

    private const AUDIO_FORMATS = ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'];

    private static ?ClientInterface $httpClient = null;

    public static function ask(string $question, string $model, string $apiKey): string
    {
        self::validateRequired($question, 'The question');
        self::validateRequired($model, 'The model');

        $data = self::requestJson('POST', 'responses', $apiKey, [
            'model' => $model,
            'input' => $question,
        ]);

        return self::extractResponseText($data);
    }

    /**
     * Generate an image from a text prompt. The returned string contains the
     * binary image bytes and can be written directly with file_put_contents().
     */
    public static function generateImage(
        string $prompt,
        string $apiKey,
        string $model = self::IMAGE_MODEL,
    ): string {
        self::validateRequired($prompt, 'The prompt');
        self::validateRequired($model, 'The model');

        $data = self::requestJson('POST', 'images/generations', $apiKey, [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'output_format' => 'png',
        ], 180.0);

        return self::imageBytesFromResponse($data);
    }

    /**
     * Edit a local PNG, JPEG, or WEBP image using a text prompt.
     * The returned string contains the binary PNG bytes.
     */
    public static function editImage(
        string $imagePath,
        string $prompt,
        string $apiKey,
        string $model = self::IMAGE_MODEL,
    ): string {
        self::validateRequired($prompt, 'The prompt');
        self::validateRequired($model, 'The model');

        $image = self::readEditableImage($imagePath);
        $response = self::sendRequest('POST', 'images/edits', $apiKey, [
            'multipart' => [
                ['name' => 'model', 'contents' => $model],
                ['name' => 'prompt', 'contents' => $prompt],
                [
                    'name' => 'image[]',
                    'contents' => $image['contents'],
                    'filename' => basename($imagePath),
                    'headers' => ['Content-Type' => $image['mime']],
                ],
                ['name' => 'output_format', 'contents' => 'png'],
            ],
        ], 180.0);

        return self::imageBytesFromResponse(self::decodeJson((string) $response->getBody()));
    }

    /**
     * Analyze an image and return a text answer. $image can be a local image
     * path, an HTTP(S) URL, or a base64 data URL.
     */
    public static function analyzeImage(
        string $image,
        string $question,
        string $model,
        string $apiKey,
    ): string {
        self::validateRequired($question, 'The question');
        self::validateRequired($model, 'The model');

        $dataUrl = self::imageDataUrl($image);
        $data = self::requestJson('POST', 'responses', $apiKey, [
            'model' => $model,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $question],
                    ['type' => 'input_image', 'image_url' => $dataUrl, 'detail' => 'auto'],
                ],
            ]],
        ], 90.0);

        return self::extractResponseText($data);
    }

    /** Return the built-in voice names accepted by the speech endpoint. */
    public static function availableVoices(): array
    {
        return self::VOICES;
    }

    /**
     * Turn text into audio. The returned string contains the audio bytes.
     */
    public static function textToSpeech(
        string $text,
        string $voice,
        string $apiKey,
        string $model = self::TTS_MODEL,
        string $responseFormat = 'mp3',
        ?string $instructions = null,
    ): string {
        self::validateRequired($text, 'The text');
        self::validateRequired($model, 'The model');
        self::validateRequired($voice, 'The voice');

        if (!in_array($voice, self::VOICES, true)) {
            throw new \InvalidArgumentException(
                'Unsupported voice. Available voices: ' . implode(', ', self::VOICES) . '.',
            );
        }

        if (!in_array($responseFormat, self::AUDIO_FORMATS, true)) {
            throw new \InvalidArgumentException(
                'Unsupported audio format. Supported formats: ' . implode(', ', self::AUDIO_FORMATS) . '.',
            );
        }

        $payload = [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'response_format' => $responseFormat,
        ];

        if ($instructions !== null && trim($instructions) !== '') {
            $payload['instructions'] = $instructions;
        }

        $response = self::sendRequest('POST', 'audio/speech', $apiKey, [
            'json' => $payload,
        ], 120.0);

        return (string) $response->getBody();
    }

    /**
     * Generate one audio sample per built-in voice, using the supplied phrase.
     * The result maps each voice name to its binary audio content.
     * This method makes one billable API request per voice.
     */
    public static function generateVoiceSamples(
        string $testPhrase,
        string $apiKey,
        string $model = self::TTS_MODEL,
        string $responseFormat = 'mp3',
    ): array {
        self::validateRequired($testPhrase, 'The test phrase');

        $samples = [];

        foreach (self::VOICES as $voice) {
            $samples[$voice] = self::textToSpeech(
                $testPhrase,
                $voice,
                $apiKey,
                $model,
                $responseFormat,
            );
        }

        return $samples;
    }

    private static function requestJson(
        string $method,
        string $uri,
        string $apiKey,
        array $payload,
        float $timeout = 30.0,
    ): array {
        $response = self::sendRequest($method, $uri, $apiKey, ['json' => $payload], $timeout);

        return self::decodeJson((string) $response->getBody());
    }

    private static function sendRequest(
        string $method,
        string $uri,
        string $apiKey,
        array $options,
        float $timeout = 30.0,
    ): ResponseInterface {
        self::validateRequired($apiKey, 'The API key');

        $client = self::$httpClient ?? new Client([
            'base_uri' => self::ENDPOINT,
            'timeout' => $timeout,
            'connect_timeout' => 10.0,
            'http_errors' => false,
        ]);

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $apiKey,
        ]);
        $options['http_errors'] = false;

        try {
            $response = $client->request($method, $uri, $options);
        } catch (GuzzleException) {
            throw new RuntimeException('The OpenAI request failed due to a network error.');
        }

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(self::httpErrorMessage($response->getStatusCode()));
        }

        return $response;
    }

    private static function decodeJson(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        if (!is_array($data)) {
            throw new RuntimeException('OpenAI returned an invalid response.');
        }

        return $data;
    }

    private static function extractResponseText(array $data): string
    {
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

    private static function imageBytesFromResponse(array $data): string
    {
        $encodedImage = $data['data'][0]['b64_json'] ?? null;

        if (!is_string($encodedImage)) {
            throw new RuntimeException('OpenAI returned no usable image.');
        }

        $image = base64_decode($encodedImage, true);

        if ($image === false) {
            throw new RuntimeException('OpenAI returned invalid image data.');
        }

        return $image;
    }

    private static function imageDataUrl(string $image): string
    {
        self::validateRequired($image, 'The image');

        if (str_starts_with($image, 'data:image/')) {
            if (preg_match('/^data:image\/(png|jpeg|webp|gif);base64,[A-Za-z0-9+\/=]+$/', $image) !== 1) {
                throw new \InvalidArgumentException('The image data URL must contain a supported base64 image.');
            }

            return $image;
        }

        if (filter_var($image, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));

            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new \InvalidArgumentException('The image URL must use HTTP or HTTPS.');
            }

            return $image;
        }

        $mime = self::imageMimeType($image);
        $contents = @file_get_contents($image);

        if ($contents === false) {
            throw new \InvalidArgumentException('The image file could not be read.');
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private static function readEditableImage(string $imagePath): array
    {
        self::validateRequired($imagePath, 'The image path');
        $mime = self::imageMimeType($imagePath);

        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Image editing accepts PNG, JPEG, and WEBP files.');
        }

        $size = @filesize($imagePath);

        if ($size === false || $size > 50 * 1024 * 1024) {
            throw new \InvalidArgumentException('The image file must be smaller than 50 MB.');
        }

        $contents = @file_get_contents($imagePath);

        if ($contents === false) {
            throw new \InvalidArgumentException('The image file could not be read.');
        }

        return ['mime' => $mime, 'contents' => $contents];
    }

    private static function imageMimeType(string $imagePath): string
    {
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            throw new \InvalidArgumentException('The image file does not exist or cannot be read.');
        }

        $mime = null;

        if (class_exists(\finfo::class)) {
            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $fileInfo->file($imagePath);

            if (is_string($detectedMime)) {
                $mime = $detectedMime;
            }
        }

        if ($mime === null) {
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => null,
            };
        }

        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            throw new \InvalidArgumentException('Supported image formats are PNG, JPEG, WEBP, and GIF.');
        }

        return $mime;
    }

    private static function validateRequired(string $value, string $label): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException($label . ' must not be empty.');
        }
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
