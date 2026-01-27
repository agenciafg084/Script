<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public const OPEN_AI_BASE_URL = 'https://api.openai.com/v1';

    // legacy endpoints (still usable, but be strict with params)
    public const OPEN_AI_COMPLETION_PATH = '/completions';
    public const OPEN_AI_CHAT_COMPLETION_PATH = '/chat/completions';
    public const OPEN_AI_IMAGE_GENERATIONS_PATH = '/images/generations';

    /**
     * Treat these as "chat style" models.
     * (Keep configurable; don’t rely on this list being perfect forever.).
     */
    public const CHAT_MODELS = [
        'gpt-5-chat-latest',
        'gpt-4o',
        'gpt-4o-mini',
        'o3',
    ];

    /**
     * Public API: generate text.
     */
    public static function generateText(string $prompt): string
    {
        return self::generateCompletionRequest($prompt);
    }

    /**
     * Main text generator (chat + legacy completion).
     */
    public static function generateCompletionRequest(string $prompt): string
    {
        if (!getSetting('ai.open_ai_text_enabled')) {
            return '';
        }

        $model = (string) getSetting('ai.open_ai_model');
        if ($model === '') {
            return '';
        }

        $client = self::client();

        try {
            $endpointPath = self::getEndpointPath($model);
            $payload = self::buildRequestBody($prompt, $model);

            $res = $client->post(self::OPEN_AI_BASE_URL.$endpointPath, [
                'headers' => [
                    'Authorization' => 'Bearer '.getSetting('ai.open_ai_api_key'),
                ],
                'json' => $payload,
            ]);

            $json = json_decode((string) $res->getBody(), true);

            // Safely extract output
            $text = self::extractText($json);

            return trim((string) $text);

        } catch (ClientException|RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body = (string) $e->getResponse()?->getBody();

            Log::error('OpenAI text generation failed', [
                'status' => $status,
                'model'  => $model,
                'body'   => $body,
            ]);

            return ''; // return empty so controller can handle gracefully
        } catch (\Throwable $e) {
            Log::error('OpenAI text generation unexpected error', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private static function getEndpointPath(string $model): string
    {
        return in_array($model, self::CHAT_MODELS, true)
            ? self::OPEN_AI_CHAT_COMPLETION_PATH
            : self::OPEN_AI_COMPLETION_PATH;
    }

    private static function buildRequestBody(string $prompt, string $model): array
    {
        return in_array($model, self::CHAT_MODELS, true)
            ? self::buildChatCompletionRequestBody($prompt, $model)
            : self::buildCompletionRequestBody($prompt, $model);
    }

    private static function buildCompletionRequestBody(string $prompt, string $model): array
    {
        // completions is legacy; keep legacy params here
        return [
            'model'       => $model,
            'prompt'      => $prompt,
            'temperature' => self::getTemperature(),
            'max_tokens'  => self::getMaxTokens(),
        ];
    }

    private static function buildChatCompletionRequestBody(string $prompt, string $model): array
    {
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        // Reasoning models often require max_completion_tokens (not max_tokens). :contentReference[oaicite:1]{index=1}
        if (self::usesMaxCompletionTokens($model)) {
            $body['max_completion_tokens'] = self::getMaxTokens();
        } else {
            // Many chat models still accept max_tokens, but it’s increasingly deprecated.
            $body['max_tokens'] = self::getMaxTokens();
            $body['temperature'] = self::getTemperature();
        }

        return $body;
    }

    private static function usesMaxCompletionTokens(string $model): bool
    {
        // Adjust as you add more reasoning models
        return in_array($model, ['o3', 'o1', 'o4-mini'], true);
    }

    private static function extractText(array $json): string
    {
        // Chat Completions: choices[0].message.content
        $content = data_get($json, 'choices.0.message.content');
        if (is_string($content)) {
            return $content;
        }

        // Sometimes content can be an array; try best-effort join
        if (is_array($content)) {
            // common patterns: [{type:'text', text:'...'}]
            $parts = [];
            foreach ($content as $item) {
                $t = $item['text'] ?? $item['content'] ?? null;
                if (is_string($t)) $parts[] = $t;
            }
            if ($parts) return implode("\n", $parts);
        }

        // Legacy completions: choices[0].text
        $legacy = data_get($json, 'choices.0.text');
        if (is_string($legacy)) {
            return $legacy;
        }

        return '';
    }

    public static function getTemperature(): float
    {
        // ✅ correct key (matches Filament)
        $val = getSetting('ai.open_ai_completion_temperature');

        if ($val === null || $val === '') {
            return 1.0;
        }

        $t = (float) $val;
        if ($t < 0) $t = 0;
        if ($t > 2) $t = 2;

        return $t;
    }

    public static function getMaxTokens(): int
    {
        $val = getSetting('ai.open_ai_completion_max_tokens');

        if ($val === null || $val === '') {
            return 200;
        }

        $n = (int) $val;
        if ($n < 1) $n = 1;
        if ($n > 4096) $n = 4096;

        return $n;
    }

    /**
     * Image generator: returns base64 (no data: prefix).
     */
    public static function generateImage(string $prompt, string $size = '1024x1024'): string
    {
        if (!getSetting('ai.open_ai_images_enabled')) {
            return '';
        }

        $model = (string) (getSetting('ai.open_ai_image_model') ?: 'dall-e-3');
        $client = self::client();

        $payload = [
            'model'  => $model,
            'prompt' => $prompt,
            'n'      => 1,
            'size'   => $size,
        ];

        // DALL·E supports response_format; GPT-image-* often returns base64 by default. :contentReference[oaicite:2]{index=2}
        if (in_array($model, ['dall-e-2', 'dall-e-3'], true)) {
            $payload['response_format'] = 'b64_json';
        }

        try {
            $res = $client->post(self::OPEN_AI_BASE_URL.self::OPEN_AI_IMAGE_GENERATIONS_PATH, [
                'headers' => [
                    'Authorization' => 'Bearer '.getSetting('ai.open_ai_api_key'),
                ],
                'json' => $payload,
            ]);

            $json = json_decode((string) $res->getBody(), true);

            $b64 = data_get($json, 'data.0.b64_json');
            if (is_string($b64) && $b64 !== '') {
                return $b64;
            }

            $url = data_get($json, 'data.0.url');
            if (is_string($url) && $url !== '') {
                $imgRes = $client->get($url);
                return base64_encode((string) $imgRes->getBody());
            }

            Log::warning('OpenAI image generation returned no b64_json or url', [
                'model'    => $model,
                'size'     => $size,
                'response' => $json,
            ]);

            return '';

        } catch (ClientException|RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body = (string) $e->getResponse()?->getBody();

            Log::error('OpenAI image generation failed', [
                'status' => $status,
                'model'  => $model,
                'size'   => $size,
                'body'   => $body,
            ]);

            return '';
        } catch (\Throwable $e) {
            Log::error('OpenAI image generation unexpected error', [
                'model' => $model,
                'size'  => $size,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private static function client(): Client
    {
        return new Client([
            'timeout' => 90,
            'connect_timeout' => 15,
        ]);
    }
}
