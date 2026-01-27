<?php

namespace App\Providers;

use FFMpeg\Filters\Video\CustomFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Filters\WatermarkFactory;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class MediaEncoderServiceProvider extends ServiceProvider
{
    // Mixed for ffmpeg and coconut
    public static $videoEncodingPresets = [
        'size' => ['videoBitrate'=> 500, 'audioBitrate' => 128, 'quality' => 1],
        'balanced' => ['videoBitrate'=> 1000, 'audioBitrate' => 256, 'quality' => 3],
        'quality' => ['videoBitrate'=> 2000, 'audioBitrate' => 512, 'quality' => 5],
    ];

    /**
     * Method used to return real watermark path / fallback to the default one.
     *
     * @return mixed|string
     */
    public static function getWatermarkPath()
    {
        $watermark_image = getSetting('media.watermark_image');
        if($watermark_image){
            $watermark_image = $watermark_image;
        }
        else{
            $watermark_image = public_path('img/logo-black.png');
        }
        return $watermark_image;
    }

    /**
     * Generates coconut storage configuration.
     * @param $storageDriver
     * @return array|bool
     */
    public static function getCoconutStorageSettings($storageDriver) {
        switch ($storageDriver) {
            case 's3':
                return [
                    'service' => 's3',
                    'bucket' => getSetting('storage.aws_bucket_name'),
                    'region' => getSetting('storage.aws_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.aws_access_key'),
                        'secret_access_key' => getSetting('storage.aws_secret_key'),
                    ],
                ];
            case 'do_spaces':
                return [
                    'service' => 'dospaces',
                    'bucket' => getSetting('storage.do_bucket_name'),
                    'region' => getSetting('storage.do_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.do_access_key'),
                        'secret_access_key' => getSetting('storage.do_secret_key'),
                    ],
                ];
            case 'wasabi':
                return [
                    'service' => 'wasabi',
                    'bucket' => getSetting('storage.was_bucket_name'),
                    'region' => getSetting('storage.was_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.was_access_key'),
                        'secret_access_key' => getSetting('storage.was_secret_key'),
                    ],
                ];
            case 'minio':
                return [
                    'service' => 's3other',
                    'bucket' => getSetting('storage.minio_bucket_name'),
                    'force_path_style' => true,
                    'region' => getSetting('storage.minio_region'),
                    'credentials' => [
                        'access_key_id' => getSetting('storage.minio_access_key'),
                        'secret_access_key' => getSetting('storage.minio_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.minio_endpoint'),
                ];
            case 'pushr':
                return [
                    'service' => 's3other',
                    'bucket' => getSetting('storage.pushr_bucket_name'),
                    'force_path_style' => true,
                    'region' => 'us-east-1',
                    'credentials' => [
                        'access_key_id' => getSetting('storage.pushr_access_key'),
                        'secret_access_key' => getSetting('storage.pushr_secret_key'),
                    ],
                    'endpoint' => getSetting('storage.pushr_endpoint'),
                ];
            default:
                return false;
        }
    }

    public static function encodeVideo($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark) {

        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $fileExtension = $initialFileExtension = $file->guessExtension();
        $hasThumbnail = false;
        $hasBlurredPreview = false;
        $videoLength = 0;

        $fileContent = file_get_contents($file);
        if (getSetting('media.transcoding_driver') === 'ffmpeg') {
            // Move tmp file onto local files path, as ffmpeg can't handle absolute paths
            $filePath = $fileId.'.'.$fileExtension;
            Storage::disk('tmp')->put($filePath, $fileContent);

            $fileExtension = 'mp4';
            $newfilePath = $directory.'/'.$fileId.'.'.$fileExtension;

            // Converting the video
            $video = FFMpeg::
            fromDisk('tmp')
                ->open($filePath);

            $videoLength = $video->getFormat()->get('duration');
            $videoLength = explode('.', $videoLength);
            $videoLength = (int) $videoLength[0];

            // Checking if uploaded videos do no exceed maximum length in seconds
            if(getSetting('media.max_videos_length')){
                $maxLength = (int) getSetting('media.max_videos_length');
                if($videoLength > $maxLength){
                    throw new \Exception(__("Uploaded videos can not longer than :length seconds.", ['length'=>$maxLength]));
                }
            }

            // Add watermark if enabled in admin
            if ($applyWatermark && getSetting('media.apply_watermark')) {
                $dimensions = $video
                    ->getVideoStream()
                    ->getDimensions();
                if(getSetting('media.watermark_image')) {
                    // Add watermark to post images
                    $watermark = Image::make(self::getWatermarkPath());
                    $tmpWatermarkFile = 'watermark-'.$fileId.'-.png';
                    $resizePercentage = 75; //70% less than an actual image (play with this value)
                    $watermarkSize = round($dimensions->getWidth() * ((100 - $resizePercentage) / 100), 2); //watermark will be $resizePercentage less then the actual width of the image
                    // resize watermark width keep height auto
                    $watermark->resize($watermarkSize, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $watermark->encode('png', 100);
                    Storage::disk('tmp')->put($tmpWatermarkFile, $watermark);
                    if (getSetting('media.apply_watermark')) {
                        $video->addWatermark(function (WatermarkFactory $watermark) use ($fileId, $tmpWatermarkFile) {
                            $watermark->fromDisk('tmp')
                                ->open($tmpWatermarkFile)
                                ->right(25)
                                ->bottom(25);
                        });
                    }
                }

                if(getSetting('media.use_url_watermark')){
                    $textWaterMark = str_replace(['https://', 'http://', 'www.'], '', route('profile', ['username'=>Auth::user()->username]));
                    $textWaterMarkSize = 3 / 100 * $dimensions->getWidth();
                    // Note: Some hosts might need to default font on public_path('/fonts/OpenSans-Semibold.ttf') instead of verdana
                    $filter = new CustomFilter("drawtext=text='".$textWaterMark."':x=10:y=H-th-10:fontfile='".(env('FFMPEG_FONT_PATH') ?? 'Verdana')."':fontsize={$textWaterMarkSize}:fontcolor=white: x=(w-text_w)-25: y=(h-text_h)-35");
                    $video->addFilter($filter);
                }

            }

            // Re-converting mp4 only if enforced by the admin setting
            if($initialFileExtension == 'mp4' && !getSetting('media.enforce_mp4_conversion')){
                $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                $storage->put($filePath, $fileContent, 'public');
            }
            else{
                // Overriding default ffmpeg lib temporary_files_root behaviour
                $ffmpegOutputLogDir = storage_path().'/logs/ffmpeg';
                $ffmpegPassFile = $ffmpegOutputLogDir.'/'.uniqid();
                if(!is_dir($ffmpegOutputLogDir)){
                    mkdir($ffmpegOutputLogDir);
                }

                $videoQualityPreset = self::$videoEncodingPresets[getSetting('media.ffmpeg_video_conversion_quality_preset')];
                $video = $video->export()->toDisk(config('filesystems.defaultFilesystemDriver'));
                if(getSetting('media.ffmpeg_audio_encoder') == 'aac'){
                    $video->inFormat((new X264('aac', 'libx264'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                }
                elseif(getSetting('media.ffmpeg_audio_encoder') == 'libmp3lame'){
                    $video->inFormat((new X264('libmp3lame'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                }
                elseif (getSetting('media.ffmpeg_audio_encoder') == 'libfdk_aac'){
                    $video->inFormat((new X264('libfdk_aac', 'libx264'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                }

                $video->addFilter('-preset', 'ultrafast')
                    //->addFilter(['-strict', 2])
                    ->addFilter(['-passlogfile', $ffmpegPassFile])
                    ->save($newfilePath);

                // Generating thumbnail from converted video
                $thumbnailPath = $directory.'/thumbnails/'.$fileId.'.jpg';
                FFMpeg::fromDisk(config('filesystems.defaultFilesystemDriver'))
                    ->open($newfilePath)
                    ->getFrameFromSeconds(1)
                    ->export()
                    ->toDisk(config('filesystems.defaultFilesystemDriver'))
                    ->save($thumbnailPath);
                $hasThumbnail = true;

                // Generate blurred version of the thumbnail
                if (getSetting('media.use_blurred_previews_for_locked_posts') && $hasThumbnail && $generateBlurredShot) {
                    $blurredThumbnailPath = $directory.'/blurred/'.$fileId.'.jpg';
//                        $thumbnailPath = $directory . '/thumbnails/' . $fileId . '.jpg';

                    try {
                        // Open a stream from remote storage
                        $thumbnailStream = Storage::disk(config('filesystems.defaultFilesystemDriver'))->readStream($thumbnailPath);

                        // Load the stream into Intervention Image
                        $thumbnailImage = Image::make($thumbnailStream)->orientate();

                        // Access the GD resource and apply the blur
                        $gdImage = $thumbnailImage->getCore();
                        $blurredGdImage = multiStepBlur($gdImage, 4, 40, 25); // Adjust scaleFactor, blurIntensity, and finalBlur as needed

                        // Wrap the GD resource back into an Intervention Image instance
                        $blurredThumbnailImage = Image::make($blurredGdImage);

                        // Encode and save the blurred thumbnail
                        $blurredThumbnailImage->encode('jpg', 80);

                        // Save the blurred thumbnail back to remote storage
                        Storage::disk(config('filesystems.defaultFilesystemDriver'))->put($blurredThumbnailPath, (string) $blurredThumbnailImage, 'public');
                        $hasBlurredPreview = true;
                    } catch (\Exception $e) {
                        throw new \Exception("Failed to process blurred thumbnail: ".$e->getMessage());
                    }
                }

                if(file_exists($ffmpegPassFile.'-0.log')) unlink($ffmpegPassFile.'-0.log');
                if(file_exists($ffmpegPassFile.'-1.log')) unlink($ffmpegPassFile.'-1.log');

            }

            Storage::disk('tmp')->delete($filePath);
            if ($applyWatermark && getSetting('media.apply_watermark') && getSetting('media.watermark_image')) {
                Storage::disk('tmp')->delete($tmpWatermarkFile);
            }
            $filePath = $newfilePath;
        }
        elseif (getSetting('media.transcoding_driver') === 'coconut'){
            if($initialFileExtension == 'mp4' && !getSetting('media.coconut_enforce_mp4_conversion')){
                $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                $storage->put($filePath, $fileContent, 'public');
            }
            else{
                $region = getSetting('media.coconut_video_region');
                $configData = [];
                if($region && $region !== 'us-east-1'){
                    $configData['region'] = $region;
                }
                $coconut = new \Coconut\Client(getSetting('media.coconut_api_key'), $configData);
                // Uploading the original video onto s3
                $filePath = $directory.'/tmp/'.$fileId.'.'.$fileExtension;
                $storage->put($filePath, $fileContent, 'public');
                Storage::url($filePath);

                // Setting up the coconut notification
                $coconut->notification = [
                    'type' => 'http',
                    'url' => env('COCONUT_WEBHOOK_URL') ? env('COCONUT_WEBHOOK_URL') : route('transcoding.coconut.update'),
                    "params" => [
                        'attachmentId' => $fileId,
                    ],
                    'metadata' => true,
                ];

                // Setting up the storage for coconut
                if(getSetting('storage.driver') === 'public'){
                    throw new \Exception("Local storage driver is not supported by Coconut.");
                }
                $coconut->storage = self::getCoconutStorageSettings(getSetting('storage.driver'));

                $videoQualityPreset = self::$videoEncodingPresets[str_replace("coconut_", "", getSetting('media.coconut_video_conversion_quality_preset'))];
                // Sending the transcoding request
                $tempFileUrl = Storage::url($filePath);
                if(getSetting('storage.driver') === 'pushr'){
                    $tempFileUrl = "{$tempFileUrl}";
                }
                if(getSetting('storage.driver') === 's3' && getSetting('storage.aws_cdn_enabled')){
                    $tempFileUrl = 'https://'.getSetting('storage.cdn_domain_name').'/'.$filePath;
                }
                $jobData = [
                    'input' => ['url' => $tempFileUrl],
                    "settings"=> [
                        "ultrafast"=> true,
                    ],
                    // Review if 480 isn't too small
                    // For ffmpeg - we take a frame out of the full resolution video
                    // Add another image output with blur filter (1-5)
                    'outputs' => [
                        'jpg:540p' => [
                            'key' => 'jpg:medium',
                            'path' => '/posts/videos/thumbnails/'.$fileId.'.jpg',
                            "offsets" => [1],
                        ],
                        'mp4' => [
                            [
                                'key' => 'mp4',
                                'path' => '/posts/videos/'.$fileId.'.mp4',
                                'format' => [
                                    'quality' => $videoQualityPreset['quality'],
                                    'video_codec' => 'h264',
                                    'audio_codec' => getSetting('media.coconut_audio_encoder'),
                                    'video_bitrate' => $videoQualityPreset['videoBitrate'].'k',
                                    'audio_bitrate' => $videoQualityPreset['audioBitrate'].'k',
                                ],
                            ],
                        ],
                    ],
                ];

                if ($generateBlurredShot && getSetting('media.use_blurred_previews_for_locked_posts')) {
                    // Blurred thumbnail
                    $jobData['outputs']['jpg:720p'] = [
                        'key' => 'jpg',
                        'path' => '/posts/videos/blurred/'.$fileId.'.jpg',
                        "offsets" => [1],
                        'blur' => 5,
                    ];
                }

                // Watermark
                if ($applyWatermark && getSetting('media.apply_watermark')) {
                    if (getSetting('media.watermark_image')) {
                        $jobData['outputs']['mp4'][0]['watermark'] = [
                            'url' => self::getWatermarkPath(),
                            'position' => 'bottomright',
                        ];
                    }
                }

                $coconutJob = $coconut->job->create($jobData);
            }
        }
        else {
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $storage->put($filePath, $fileContent, 'public');
        }

        return [
            'filePath' => $filePath,
            'coconut_id' => (isset($coconutJob) ? $coconutJob->id : null),
            'hasBlurredPreview' => $hasBlurredPreview,
            'hasThumbnail' => $hasThumbnail,
            'length' => $videoLength,
        ];

    }

    public static function encodeImage($file, $directory, $fileId, $generateThumbnail, $generateBlurredShot, $applyWatermark)
    {
        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $fileExtension = $file->guessExtension();
        $hasThumbnail = false;
        $hasBlurredPreview = false;

        // Create the initial image instance and orientate it
        $jpgImage = Image::make($file);
        $jpgImage->fit($jpgImage->width(), $jpgImage->height())->orientate();

        // Save the original file content before any processing
        $originalFileContent = (string) $jpgImage->encode('jpg', 100); // Save the high-quality original

        if ($applyWatermark && getSetting('media.apply_watermark')) {
            // Add watermark to the main image
            if (getSetting('media.watermark_image')) {
                $watermark = Image::make(self::getWatermarkPath());
                $resizePercentage = 75; // 70% less than the actual image
                $watermarkSize = round($jpgImage->width() * ((100 - $resizePercentage) / 100), 2);
                $watermark->resize($watermarkSize, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $jpgImage->insert($watermark, 'bottom-right', 30, 25);
            }

            if (getSetting('media.use_url_watermark')) {
                $textWaterMark = str_replace(['https://', 'http://', 'www.'], '', route('profile', ['username' => Auth::user()->username]));
                $textWaterMarkSize = 3 / 100 * $jpgImage->width();
                $jpgImage->text($textWaterMark, $jpgImage->width() - 25, $jpgImage->height() - 10, function ($font) use ($textWaterMarkSize) {
                    $font->file(public_path('/fonts/OpenSans-Semibold.ttf'));
                    $font->size($textWaterMarkSize);
                    $font->color([255, 255, 255, 0.7]);
                    $font->align('right');
                    $font->valign('bottom');
                    $font->angle(0);
                });
            }
        }

        // Handle GIFs without processing
        if ($fileExtension == 'gif') {
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $storage->put($filePath, file_get_contents($file->getRealPath()), 'public');
        } else {
            // Save the processed image
            $fileExtension = 'jpg';
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $storage->put($filePath, (string) $jpgImage->encode('jpg', 100), 'public');
        }

        // Generate thumbnail
        if ($generateThumbnail) {
            $width = 150;
            $height = 150;
            $thumbnailImg = Image::make($originalFileContent); // Use the saved original content
            $thumbnailImg->fit($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
            $thumbnailImg->encode('jpg', 100);
            $thumbnailDir = $directory.'/'.$width.'X'.$height;
            $thumbnailfilePath = $thumbnailDir.'/'.$fileId.'.jpg';
            // Upload the thumbnail to storage
            $storage->put($thumbnailfilePath, (string) $thumbnailImg, 'public');
            $hasThumbnail = true;
        }

        if (getSetting('media.use_blurred_previews_for_locked_posts') && $generateBlurredShot) {
            $blurredImg = Image::make($originalFileContent)->orientate(); // Ensure proper orientation
            // Downscale the image to speed up processing and reduce memory usage
            $blurredImg->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Access the raw GD image resource
            $gdImage = $blurredImg->getCore();
            // Blur the image
            $blurredGdImage = multiStepBlur($gdImage);
            // Wrap the GD resource back into an Intervention Image instance
            $blurredImg = Image::make($blurredGdImage);
            // Encode the image as a web-optimized JPEG
            $blurredImg->encode('jpg', 80); // Adjust quality for faster load and reduced size
            // Define directory and file path for the blurred preview
            $blurredPreviewDir = $directory.'/blurred';
            $blurredPreviewPath = $blurredPreviewDir.'/'.$fileId.'.jpg';
            // Upload the blurred image to storage
            $storage->put($blurredPreviewPath, (string) $blurredImg, 'public');
            $hasBlurredPreview = true;
        }

        return [
            'filePath' => $filePath,
            'hasBlurredPreview' => $hasBlurredPreview,
            'hasThumbnail' => $hasThumbnail,
        ];

    }

    /**
     * Doesn't really encode such files at all, just uploads them onto storage driver.
     * @param $file
     * @param $filePath
     * @return false[]
     */
    public static function encodeRegularFile($file, $directory, $fileId)
    {
        $fileExtension = $file->guessExtension();
        $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        $fileContent = file_get_contents($file);
        $storage->put($filePath, $fileContent, 'public');
        return [
            'filePath' => $filePath,
            'hasBlurredPreview' => false,
            'hasThumbnail' => false,
        ];
    }
}
