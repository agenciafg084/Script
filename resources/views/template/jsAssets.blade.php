{{-- Global JS Assets --}}
{!!
    Minify::javascript(
        array_merge([
        '/libs/jquery/dist/jquery.min.js',
        '/libs/popper.js/dist/umd/popper.min.js',
        '/libs/bootstrap/dist/js/bootstrap.min.js',
        '/js/plugins/toasts.js',
        '/libs/vanilla-cookieconsent/dist/cookieconsent.umd.js',
        '/libs/xss/dist/xss.min.js',
        '/libs/pusher-js-auth/lib/pusher-auth.js',
        '/js/Websockets.js',
        '/js/app.js',
    ],
    (isset($additionalJs) ? $additionalJs : [])
    ))->withFullUrl()
!!}

{{-- Page specific JS --}}
@yield('scripts')

<script type="module" src="{{asset('/libs/ionicons/dist/ionicons/ionicons.esm.js')}}"></script>

{{--TODO: Only include this if livekit streaming is enabled ?--}}
@if(getSetting('streams.streaming_driver') === 'livekit')
    <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.9.1/dist/livekit-client.umd.min.js"></script>
@endif

@if(getSetting('site.custom_code_js'))
    {!! getSetting('site.custom_code_js') !!}
@endif

@include('elements.translations')
