<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public bool $enable_2fa;

    public bool $default_2fa_on_register;

    public bool $allow_users_2fa_switch;

    public bool $enforce_app_ssl;

    public bool $allow_geo_blocking;

    public bool $enforce_email_valid_check;

    public ?string $abstract_api_key;

    public ?string $email_abstract_api_key;

    public string $captcha_driver;

    public ?string $recaptcha_site_key;

    public ?string $recaptcha_site_secret_key;

    public ?string $turnstile_site_key;

    public ?string $turnstile_site_secret_key;

    public ?string $hcaptcha_site_key;

    public ?string $hcaptcha_site_secret_key;

    public ?string $domain_policy = null;

    public array $allowedlist_domains = [];

    public array $blocklist_domains = [];

    public static function group(): string
    {
        return 'security';
    }
}
