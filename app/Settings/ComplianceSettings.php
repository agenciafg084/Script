<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ComplianceSettings extends Settings
{
    public bool $enable_cookies_box;

    public bool $enable_age_verification_dialog;

    public ?string $age_verification_cancel_url;

    public ?int $admin_approved_posts_limit;

    public ?int $minimum_posts_until_creator;

    public ?int $minimum_posts_deletion_limit;

    public ?int $monthly_posts_before_inactive;

    public bool $disable_creators_ppv_delete;

    public bool $allow_text_only_ppv;

    public bool $enforce_tos_check_on_id_verify;

    public bool $enforce_media_agreement_on_id_verify;

    public ?string $tax_info_dac7_enabled = null;

    public ?string $tax_info_dac7_withdrawals_enforced = null;

    public ?string $tax_info_dac7_earnings_limit_before_enforced = "0";

    public static function group(): string
    {
        return 'compliance';
    }
}
