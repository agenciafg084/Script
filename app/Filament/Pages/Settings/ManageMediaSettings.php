<?php

namespace App\Filament\Pages\Settings;

use App\Providers\AttachmentServiceProvider;
use App\Settings\MediaSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageMediaSettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $slug = 'settings/media';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static string $settings = MediaSettings::class;

    protected static ?string $title = 'Media Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([

                            TextInput::make('allowed_file_extensions')
                                ->label('Allowed file extensions')
                                ->helperText('If no transcoding service is available, video formats will fallback to mp4 only. '),

                            TextInput::make('max_file_upload_size')
                                ->label('Max upload size (MB)')
                                ->helperText('Maximum allowed size for uploaded media files.'),

                            Toggle::make('use_chunked_uploads')
                                ->label('Use chunked uploads')
                                ->helperText("Uploads large files in smaller parts to avoid size limits (e.g., Cloudflare restrictions)."),

                            TextInput::make('upload_chunk_size')
                                ->label('Upload chunk size (MB)')
                                ->helperText('Sets how large each part of a file upload can be (in MB). Keep within server upload limits.')
                                ->helperText('The size of each upload chunk in megabytes.'),

                            TextInput::make('users_covers_size')
                                ->helperText('Target size for user cover images. Higher resolutions improve quality but increase file size. Maintain the original aspect ratio for best results.')
                                ->label('User cover size (WxH)'),

                            TextInput::make('users_avatars_size')
                                ->helperText('Target size for user avatar images. Higher resolutions improve quality but increase file size. Maintain the original aspect ratio for best results.')
                                ->label('User avatar size (WxH)'),

                            Toggle::make('disable_media_right_click')
                                ->helperText('If enabled, right click on media (posts,  messages & stories) will be disabled.')
                                ->label('Disable right-click on media'),

                            TextInput::make('max_avatar_cover_file_size')
                                ->helperText('Maximum file size in MB for both avatar and cover images.')
                                ->label('Max avatar/cover Size (MB)'),

                            Toggle::make('use_blurred_previews_for_locked_posts')
                                ->helperText('If enabled, locked content will display blurred previews. Video files require the video transcoding service.')
                                ->label('Blur previews for locked posts')
                                ->columnSpanFull(),

                        ]),

                    Tabs\Tab::make('Videos')
                        ->columns(2)
                        ->schema([

                            Placeholder::make('coconut_warnings')
                                ->hiddenLabel()
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString(view('filament.partials.coconut-warnings')->render()))
                                ->visible(
                                    fn ($get) => $get('transcoding_driver') === 'coconut'
                                        && (
                                            getSetting('storage.driver') === 'public'
                                            || (!getSetting('websockets.pusher_app_id') && !getSetting('websockets.soketi_host_address'))
                                            || (
                                                getSetting('storage.driver') === 's3'
                                                && getSetting('storage.aws_cdn_enabled')
                                                && getSetting('storage.aws_cdn_presigned_urls_enabled')
                                            )
                                        )
                                )
                                ->columnSpanFull(),

                            Select::make('transcoding_driver')
                                ->label('Transcoding driver')
                                ->options([
                                    'none' => 'None',
                                    'ffmpeg' => 'FFmpeg',
                                    'coconut' => 'Coconut',
                                ])
                                ->placeholder('Select a driver')
                                ->helperText('Select the video transcoding engine to use.')
                                ->reactive()
                                ->required(),

                            TextInput::make('max_videos_length')
                                ->label('Max video length (seconds)')
                                ->visible(fn ($get) => in_array($get('transcoding_driver'), ['ffmpeg', 'coconut']))
                                ->required()
                                ->integer()
                                ->helperText('Maximum allowed video length in seconds (0 = unlimited).'),

                            TextInput::make('ffmpeg_path')
                                ->label('FFmpeg path')
                                ->required()
                                ->helperText("FFmpeg executable path. EG: /usr/bin/ffmpeg")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            TextInput::make('ffprobe_path')
                                ->label('FFprobe path')
                                ->required()
                                ->helperText("FFmpeg executable path. EG: /usr/bin/ffprobe")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Select::make('ffmpeg_audio_encoder')
                                ->label('Audio encoder')
                                ->options([
                                    'aac' => 'AAC Encoder',
                                    'libfdk_aac' => 'libfdk_aac Encoder',
                                    'libmp3lame' => 'LAME MP3 Encoder',
                                ])
                                ->required()
                                ->helperText("AAC is recommended, it usually offers the best compatibility.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Select::make('ffmpeg_video_conversion_quality_preset')
                                ->label('Video quality preset')
                                ->options([
                                    'size' => 'Size optimized',
                                    'balanced' => 'Balanced',
                                    'quality' => 'Quality optimized',
                                ])
                                ->required()
                                ->helperText("Better quality speeds up processing, but files will be bigger than the original.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Toggle::make('enforce_mp4_conversion')
                                ->label('Force MP4 conversion')
                                ->helperText('Disables automatic MP4 re-encoding, lowering resource usage. Watermarks and blurred previews won\'t apply to MP4 files.')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            TextInput::make('coconut_api_key')
                                ->label('Coconut API key')
                                ->helperText('The coconut API Key')
                                ->required()
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Select::make('coconut_video_region')
                                ->label('Region')
                                ->options([
                                    'us-east-1' => 'us-east-1 (North Virginia)',
                                    'us-west-2' => 'us-west-2 (Oregon)',
                                    'eu-west-1' => 'eu-west-1 (Ireland)',
                                ])
                                ->required()
                                ->helperText('Make sure you\'re using the same region under which you registered the account on')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Select::make('coconut_audio_encoder')
                                ->label('Audio encoder')
                                ->options([
                                    'aac' => 'AAC Encoder',
                                    'mp3' => 'MP3 Encoder',
                                ])
                                ->required()
                                ->helperText("AAC is recommended, it usually offers the best compatibility.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Select::make('coconut_video_conversion_quality_preset')
                                ->label('Video quality preset')
                                ->options([
                                    'coconut_size' => 'Size optimized',
                                    'coconut_balanced' => 'Balanced',
                                    'coconut_quality' => 'Quality optimized',
                                ])
                                ->required()
                                ->helperText("Better quality speeds up processing, but files will be bigger than the original.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Toggle::make('coconut_enforce_mp4_conversion')
                                ->label('Force MP4 conversion')
                                ->helperText('Disables automatic MP4 re-encoding, lowering resource usage. Watermarks and blurred previews won\'t apply to MP4 files.')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),
                        ]),

                    Tabs\Tab::make('Watermark')
                        ->columns(2)
                        ->schema([
                            FileUpload::make('watermark_image')
                                ->label('Watermark image')
                                ->directory('assets')
                                ->multiple(false)
                                ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                                ->image()
                                ->imagePreviewHeight(80)
                                ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']),

                            Toggle::make('apply_watermark')
                                ->helperText('For images, GD library is required. For videos, either ffmpeg or coconut transcoders.')
                                ->label('Apply watermark')->columnSpanFull(),

                            Toggle::make('use_url_watermark')
                                ->helperText('Adds profile url link as watermark to media. * Not supported for coconut transcoder.')
                                ->label('Use watermark URL')->columnSpanFull(),

                        ]),

                ]),

        ]);
    }
}
