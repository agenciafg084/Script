<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AISettings extends Settings
{
    // Two independent switches
    public bool $open_ai_text_enabled = false;

    public bool $open_ai_images_enabled = false;

    // Shared creds
    public ?string $open_ai_api_key;

    // Text generation config
    public ?string $open_ai_model;

    public ?int $open_ai_completion_max_tokens;

    public ?float $open_ai_completion_temperature;

    // Image generation config
    public ?string $open_ai_image_model = null;

    public static function group(): string
    {
        return 'ai';
    }
}
