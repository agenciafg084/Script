@php
    // required: $idPrefix e.g. "media" or "text"
    $wrapId   = $idPrefix . '-storySoundWrap';
    $inputId  = $idPrefix . '-storySoundSelect';
    $hiddenId = $idPrefix . '-storySoundId';
    $helpDef  = $idPrefix . '-storySoundHelpDefault';
    $helpVid  = $idPrefix . '-storySoundHelpVideo';
@endphp

<div class="mt-3" id="{{ $wrapId }}">
    <label class="mb-1">{{ $label ?? __('Sound') }}</label>

    <input type="text"
           id="{{ $inputId }}"
           placeholder="{{ __('Search for a sound…') }}"
           autocomplete="off">

    <div class="mt-1" id="{{ $helpDef }}">
        <p class="mb-2"><small class="form-text text-muted">{{ __('Start typing to search. Select a sound to attach it to this story.') }}</small></p>
    </div>

    <div class="mt-1"  id="{{ $helpVid }}">
        <p class="mb-2"><small class="form-text text-muted"> {{ __('Sounds are not available for video stories.') }}</small></p>
    </div>

    <input type="hidden" name="sound_id" id="{{ $hiddenId }}" value="">
</div>
