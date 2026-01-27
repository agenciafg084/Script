<?php

namespace App\Filament\Pages\Settings;

use App\Settings\FeedSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageFeedSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rss';

    protected static ?string $slug = 'settings/feed';

    protected static string $settings = FeedSettings::class;

    protected static ?string $title = 'Feed Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Feed Settings')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([
                            TextInput::make('min_post_description')
                                ->label('Min post description length')
                                ->helperText('If set to 0 or left empty, at least one attachment is required per post. Any other value makes attachments optional.'),
                            TextInput::make('post_box_max_height')
                                ->label('Post box max height')
                                ->helperText('Maximum height (in pixels) for media in post boxes. For example: 450. If set, images and videos will be cropped or scaled to this height when not viewed fullscreen.'),
                            TextInput::make('feed_posts_per_page')
                                ->label('Posts per page')
                                ->helperText('Number of posts shown per page in the feed.')->columnSpanFull(),

                            Toggle::make('allow_post_polls')
                                ->helperText('When enabled, users can add polls to their posts.'),

                            Toggle::make('enable_post_description_excerpts')
                                ->helperText('If enabled, long post descriptions will be truncated with a \'Show more\' link.'),

                            Toggle::make('allow_post_scheduling')
                                ->helperText('When enabled, users can schedule posts with release and expiry dates'),

                            Toggle::make('disable_posts_text_preview')
                                ->helperText('If enabled, text content in posts and messages will also be hidden behind the paywall.'),

                            Toggle::make('allow_gallery_zoom')
                                ->helperText('If enabled, high-resolution photos in post galleries can be zoomed in during preview.'),
                        ]),

                    Tabs\Tab::make('Widgets')
                        ->columns(2)
                        ->schema([
                            Select::make('selected_widget')
                                ->label('Widget')
                                ->options([
                                    'suggestions' => 'Suggestions slider',
                                    'expired' => 'Expired subscriptions',
                                    'search' => 'Search box',
                                ])
                                ->helperText('Select which widget you want to edit.')
                                ->default('suggestions')
                                ->placeholder('Select a widget')
                                ->columnSpanFull()
                                ->reactive(),

                            /*
                             * =====================
                             *  Suggestions slider
                             * =====================
                             */
                            Group::make()
                                ->visible(fn ($get) => $get('selected_widget') === 'suggestions')
                                ->columnSpanFull()
                                ->schema([

                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('feed_suggestions_card_per_page')
                                                ->label('Cards per page')
                                                ->helperText('Number of suggested profiles shown at once in the slider.'),

                                            TextInput::make('feed_suggestions_total_cards')
                                                ->label('Total cards')
                                                ->helperText('Total number of suggestions fetched for the slider.'),
                                        ]),

                                    Grid::make(2)
                                        ->schema([
                                            Group::make()
                                                ->schema([
                                                    Toggle::make('hide_suggestions_slider')
                                                        ->label('Hide the widget')
                                                        ->helperText('Hides the suggestions slider from the feed page when enabled.'),

                                                    Toggle::make('feed_suggestions_autoplay')
                                                        ->label('Autoplay suggestions')
                                                        ->helperText('Automatically scrolls through suggested profiles in the slider.'),
                                                ]),

                                            Group::make()
                                                ->schema([
                                                    Toggle::make('suggestions_skip_empty_profiles')
                                                        ->label("Skip empty profiles")
                                                        ->helperText('Only shows profiles with both avatar and cover images.'),

                                                    Toggle::make('suggestions_skip_unverified_profiles')
                                                        ->label("Skip non-verified profiles")
                                                        ->helperText('Show only ID verified profiles in suggestions.'),

                                                    Toggle::make('suggestions_use_featured_users_list')
                                                        ->label("Use featured users")
                                                        ->helperText('Limit suggestions to users marked as featured.'),
                                                ]),
                                        ]),
                                ]),

                            // === Expired Subs Widget ===
                            TextInput::make('expired_subs_widget_card_per_page')
                                ->label('Cards per page')
                                ->helperText('Number of expired subscriptions shown at once.')
                                ->visible(fn ($get) => $get('selected_widget') === 'expired'),

                            TextInput::make('expired_subs_widget_total_cards')
                                ->label('Total cards')
                                ->helperText('Total number of expired subscriptions loaded into the widget.')
                                ->visible(fn ($get) => $get('selected_widget') === 'expired'),

                            Toggle::make('expired_subs_widget_hide')
                                ->label('Hide the widget')
                                ->helperText('Hides the expired subscriptions slider from view.')
                                ->visible(fn ($get) => $get('selected_widget') === 'expired'),

                            Toggle::make('expired_subs_widget_autoplay')
                                ->helperText('Automatically scrolls through expired subscription cards.')
                                ->visible(fn ($get) => $get('selected_widget') === 'expired'),

                            // === Search Widget ===
                            Toggle::make('search_widget_hide')
                                ->label('Hide the widget')
                                ->helperText('Removes the search widget from the feed when enabled.')
                                ->visible(fn ($get) => $get('selected_widget') === 'search'),

                            Toggle::make('hide_non_verified_users_from_search')
                                ->label('Hide non-verified profiles')
                                ->helperText('Prevents unverified profiles from appearing in search results.')
                                ->visible(fn ($get) => $get('selected_widget') === 'search'),

                            Select::make('default_search_widget_filter')
                                ->label('Default search filter')
                                ->options([
                                    'live' => 'Live',
                                    'top' => 'Top',
                                    'people' => 'People',
                                    'videos' => 'Videos',
                                    'photos' => 'Photos',
                                ])
                                ->helperText('Sets the default filter applied to search results.')
                                ->visible(fn ($get) => $get('selected_widget') === 'search'),
                        ]),

                ]),
        ]);
    }
}
