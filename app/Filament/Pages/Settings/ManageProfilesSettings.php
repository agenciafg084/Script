<?php

namespace App\Filament\Pages\Settings;

use App\Model\User;
use App\Settings\ProfilesSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ManageProfilesSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings/users';

    protected static string $settings = ProfilesSettings::class;

    protected static ?string $title = 'Users Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Profile Settings')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->schema([

                            Toggle::make('allow_users_enabling_open_profiles')
                                ->label('Allow open profiles')
                                ->helperText('Allows users to set their profiles "open", making non-PPV content visible to everyone.'),

                            Toggle::make('allow_profile_qr_code')
                                ->label('Allow profile QR code')
                                ->helperText("Displays a QR code button on profiles for easy sharing."),

                            Toggle::make('allow_gender_pronouns')
                                ->label('Allow gender pronouns')
                                ->helperText('Enable users to set gender pronouns on their profile.'),

                            Toggle::make('allow_hyperlinks')
                                ->label('Allow hyperlinks')
                                ->helperText('Enable links to be clickable in posts, messages and profile bios.'),

                            Toggle::make('disable_website_link_on_profile')
                                ->label('Disable website link')
                                ->helperText('Removes the external website link field from user profiles.'),

                            Toggle::make('allow_profile_bio_markdown')
                                ->label('Enable markdown in bio')
                                ->helperText('Allow users to use Markdown formatting in their profile bio.'),

                            Toggle::make('disable_profile_offers')
                                ->label('Disable profile offers')
                                ->helperText('Turns off the ability for users to set promotional profile offers.'),

                            Toggle::make('disable_profile_bio_excerpt')
                                ->label('Disable bio excerpt')
                                ->helperText('If enabled, bio previews/excerpts will not be shown.'),

                            Toggle::make('hide_profile_followers_count')
                                ->label('Disable follower count')
                                ->helperText('If enabled, a followers & likes count will be shown on the profile page..'),

                            TextInput::make('max_profile_bio_length')
                                ->label('Max bio length')
                                ->numeric()
                                ->helperText('Maximum number of chars allowed in the profile bio. If set to 0, no limit will be set.'),

                        ])
                        ->columns(2),

                    Tabs\Tab::make('Registration')
                        ->schema([
                            Select::make('default_profile_type_on_register')
                                ->options([
                                    'paid' => 'Paid',
                                    'free' => 'Free',
                                    'open' => 'Open',
                                ])
                                ->label('Default profile type on register')
                                ->required()
                                ->helperText('Profile type assigned automatically to new users.'),

                            Select::make('default_user_privacy_setting_on_register')
                                ->options([
                                    'public' => 'Public',
                                    'private' => 'Private',
                                ])
                                ->label('Default privacy setting')
                                ->required()
                                ->helperText('Determines if user profiles are public or private by default.'),

                            Select::make('default_users_to_follow')
                                ->label('Default users to follow')
                                ->multiple()
                                ->searchable()
                                // Preload a small default set (optional, e.g. top creators)
                                ->options(
                                    fn () => User::query()
                                    ->orderByDesc('id')   // or by followers_count, created_at, etc.
                                    ->limit(20)
                                    ->pluck('username', 'id')
                                    ->toArray()
                                )
                                // Used when searching in the dropdown
                                ->getSearchResultsUsing(
                                    fn (string $search) => User::query()
                                    ->where('username', 'like', "%{$search}%")
                                    ->orderBy('username')
                                    ->limit(20)
                                    ->pluck('username', 'id')
                                    ->toArray()
                                )
                                // Used to resolve labels for saved values when editing
                                ->getOptionLabelsUsing(
                                    fn (array $values) => User::query()
                                    ->whereIn('id', $values)
                                    ->pluck('username', 'id')
                                    ->toArray()
                                )
                                ->helperText('Select which users new accounts will follow by default.'),

                            TextInput::make('default_wallet_balance_on_register')
                                ->label('Initial wallet balance')
                                ->numeric()
                                ->helperText('Virtual currency amount given to users upon sign-up.'),

                        ])
                        ->columns(2),

                    Tabs\Tab::make('Visibility & Tracking')
                        ->schema([

                            Toggle::make('show_online_users_indicator')
                                ->label('Show online status')
                                ->helperText('Display a real-time online indicator on profiles. WebSockets must be set up.'),

                            Toggle::make('record_users_last_activity_time')
                                ->label('Track last activity timestamp')
                                ->helperText('Log the most recent activity time for each user.'),

                            Toggle::make('record_users_last_ip_address')
                                ->label('Track last IP address')
                                ->helperText('Store the last known IP address for audit or security.'),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Notifications')
                        ->schema([
                            Toggle::make('enable_new_post_notification_setting')
                                ->label('Enable post notifications')
                                ->helperText('If enabled, creators can choose whether to send notifications when publishing new posts. Subscribers can also manage their preferences.'),

                            Toggle::make('default_new_post_notification_setting')
                                ->label('Default post notification setting')
                                ->helperText('Whether new post notifications are enabled by default on registration.'),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Social Links')
                        ->schema([
                            Toggle::make('social_links_enabled')
                                ->label('Enable social links on profiles')
                                ->helperText('Allows users to add social/profile links (Instagram, X, etc.) in Profile Settings.'),

                            Select::make('allowed_social_network_keys')
                                ->label('Allowed social networks')
                                ->multiple()
                                ->searchable()
                                ->options(
                                    fn () => collect(config('social_networks', []))
                                        ->mapWithKeys(fn (array $meta, string $key) => [
                                            $key => ($meta['label'] ?? $key),
                                        ])
                                        ->toArray()
                                )
                                ->helperText('Leave empty for no restrictions. Select values here to restrict what users can choose.')
                                ->hint('Empty = defaults')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Social Auth')
                        ->columns(2)
                        ->schema([

                            Placeholder::make('social_login_info')
                                ->columnSpanFull()
                                ->label('')
                                ->hiddenLabel()
                                ->content(new HtmlString(view('filament.partials.social-login-info-box')->render())),

                            TextInput::make('social_auth_facebook_client_id')->label('Facebook client ID'),
                            TextInput::make('social_auth_facebook_secret')->label('Facebook client secret'),

                            TextInput::make('social_auth_twitter_client_id')->label('Twitter client ID'),
                            TextInput::make('social_auth_twitter_secret')->label('Twitter client secret'),

                            TextInput::make('social_auth_google_client_id')->label('Google client ID'),
                            TextInput::make('social_auth_google_secret')->label('Google client secret'),
                        ]),

                ]),
        ]);
    }
}
