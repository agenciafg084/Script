<?php

namespace App\Http\Controllers;

use App\Providers\AiServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    /**
     * Saves license.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSuggestion(Request $request)
    {
        abort_unless(getSetting('ai.open_ai_text_enabled'), 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['profile_bio', 'post', 'stream', 'story'])],
        ]);

        $user = $request->user();

        $locale = data_get($user, 'locale')
            ?? data_get($user, 'settings.locale')
            ?? app()->getLocale();

        app()->setLocale($locale);

        $siteName = getSetting('site.name');

        $key = match ($validated['type']) {
            'profile_bio' => 'ai.text.profile_bio',
            'post'        => 'ai.text.post',
            'stream'      => 'ai.text.stream',
            'story'       => 'ai.text.story',
        };

        $basePrompt = __($key, ['siteName' => $siteName]);

        // Hard constraints (keep short + clean)
        $prompt = $basePrompt
            ."\n\n"
            ."No explanations. Output only the final text.\n";

        // Call your provider
        $text = AiServiceProvider::generateText($prompt);

        return response()->json(['message' => $text]);
    }
}
