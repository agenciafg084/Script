<?php

namespace App\Filament\Pages\Settings;

use App\Settings\AiSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageAiSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $slug = 'settings/ai';

    protected static string $settings = AiSettings::class;

    protected static ?string $title = 'AI Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([

                    Toggle::make('open_ai_text_enabled')
                        ->label('Enable AI Text generation')
                        ->helperText('Bio, post, titles, etc.')
                        ->columnSpanFull(),

                    Toggle::make('open_ai_images_enabled')
                        ->label('Enable AI Image generation')
                        ->helperText('Avatar & cover generation.')
                        ->columnSpanFull(),

                    TextInput::make('open_ai_api_key')
                        ->label('OpenAI API key')
                        ->required()
                        ->columnSpanFull(),

                    Select::make('open_ai_model')
                        ->label('OpenAI text model')
                        ->options([
                            'gpt-5-chat-latest' => 'GPT 5',
                            'gpt-4o' => 'GPT 4o',
                            'gpt-4o-mini' => 'GPT 4o-mini',
                            'o3' => 'o3',
                        ])
                        ->helperText('Used for text suggestions.'),

                    Select::make('open_ai_image_model')
                        ->label('OpenAI image model')
                        ->options([
                            'gpt-image-1.5' => 'GPT Image 1.5 (best quality)',
                            'gpt-image-1' => 'GPT Image 1',
                            'gpt-image-1-mini' => 'GPT Image 1 mini',
                            'dall-e-3' => 'DALL·E 3 (fallback)',
                            'dall-e-2' => 'DALL·E 2 (fallback)',
                        ])
                        ->helperText('Used for AI avatar/cover generation.'),

                    TextInput::make('open_ai_completion_max_tokens')
                        ->label('Max tokens')
                        ->numeric()
                        ->required()
                        ->helperText("Dictates how long the suggestion should be. E.g. 1000 tokens is about 750 words."),

                    TextInput::make('open_ai_completion_temperature')
                        ->label('Temperature')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(2)
                        ->step(0.1)
                        ->required()
                        ->helperText("Higher = more random, lower = more deterministic."),
                ]),
        ]);
    }
}
