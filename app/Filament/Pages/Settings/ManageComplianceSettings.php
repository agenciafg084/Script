<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ComplianceSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ManageComplianceSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'settings/compliance';

    protected static string $settings = ComplianceSettings::class;

    protected static ?string $title = 'Compliance Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Compliance Settings')
                ->columnSpanFull()
                ->tabs([

                    Tab::make('General')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enable_age_verification_dialog')
                                ->label('Enable age verification dialog')
                                ->helperText("Site entry consent dialog, to be used for NSWF content."),
                            TextInput::make('age_verification_cancel_url')
                                ->label('Cancel redirect URL')
                                ->helperText("The cancel button URL for the entry consent dialog."),
                            Toggle::make('enable_cookies_box')
                                ->label('Enable cookies box')
                                ->helperText("Cookies consent dialog box to be used for GDPR.")
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Post & Creator Limits')
                        ->columns(2)
                        ->schema([
                            TextInput::make('admin_approved_posts_limit')
                                ->numeric()
                                ->label('Admin approved posts limit')
                                ->helperText("The number of posts that needs admin approval. After this number of posts has been reached, the creator can post freely (value = 0 means no limit)."),
                            TextInput::make('minimum_posts_until_creator')
                                ->numeric()
                                ->label('Posts before monetization')
                                ->helperText("The minimum number of posts for users to be able to earn money. Users won`t be able to receive money until they reach this limit (value = 0 means no limit)."),
                            TextInput::make('minimum_posts_deletion_limit')
                                ->numeric()
                                ->label('Minimum deletion limit')
                                ->helperText("The minimum posts deletion limit for creators. Enforce them to have a minimum number of posts on their accounts (value = 0 means no limit)."),
                            TextInput::make('monthly_posts_before_inactive')
                                ->numeric()
                                ->label('Monthly post requirement')
                                ->helperText("The minimum monthly posts number a creator must publish before having his account marked as inactive. If value = 0, no inactivity rule will be applied."),
                            Toggle::make('disable_creators_ppv_delete')
                                ->label('Prevent deletion of purchased PPV')
                                ->helperText("If enabled, creators won't be able to delete paid PPV content (paid posts/messages) if already paid by a customer."),
                            Toggle::make('allow_text_only_ppv')
                                ->label('Allow text-only PPV')
                                ->helperText("If enabled, creators will be allowed to sell text-only PPV messages & posts (no media requirements)."),
                        ]),

                    Tab::make('ID Checks')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enforce_tos_check_on_id_verify')
                                ->label('TOS agreement on ID verify')
                                ->helperText("If enabled, a TOS & Creator agreement checkbox will be shown on ID-verify page. CCBill compliance requirement."),

                            Toggle::make('enforce_media_agreement_on_id_verify')->label('Media agreement on ID verify')
                                ->helperText("If enabled, a media-agreement checkbox will be shown on ID-verify page. CCBill compliance requirement."),

                        ]),

                    Tab::make('DAC7')
                        ->columns(2)
                        ->schema([
                            Toggle::make('tax_info_dac7_enabled')
                                ->label('Enable DAC7')
                                ->helperText('Enables the tax information tab for collecting required EU/UK tax details from users.'),
                            Toggle::make('tax_info_dac7_withdrawals_enforced')
                                ->label('Enforce for withdrawals')
                                ->helperText('Blocks withdrawals for users who haven’t completed their DAC7 tax information.'),
                            TextInput::make('tax_info_dac7_earnings_limit_before_enforced')
                                ->numeric()
                                ->label('Earnings limit before enforcement')
                                ->helperText("Minimum year-to-date gross earnings after which DAC7 tax information is required for withdrawals. 0 = enforce from the first transaction.")->columnSpanFull(),

                        ]),

                ]),
        ]);
    }
}
