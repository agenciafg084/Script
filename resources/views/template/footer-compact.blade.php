{{--TODO: Maybe leave it open on mobiles as well --}}
<footer class="d-none d-md-block">
    <!-- A grey container -->
    <div class="greycontainer">
        <!-- A black container -->
        <div class="blackcontainer">
            <!-- Container to indent the content -->
            <div class="container">
                <div class="copyRightInfo d-flex flex-column-reverse flex-md-row d-md-flex justify-content-md-between py-3">
                    <div class="d-flex align-items-center">
                        <p class="mb-0">&copy; {{date('Y')}} {{getSetting('site.name')}}. {{__('All rights reserved.')}}</p>
                    </div>
                    <div class="d-flex justify-content-md-center align-items-center mt-4 mt-md-0 footer-social-links">
                        @if(getSetting('general.social_facebook_url'))
                            <a class="m-2" href="{{getSetting('general.social_facebook_url')}}" target="_blank" alt="{{__("Facebook")}}" title="{{__("Facebook")}}">
                                @include('elements.icon',['icon'=>'logo-facebook','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_twitter_url'))
                            <a class="m-2" href="{{getSetting('general.social_twitter_url')}}" target="_blank" alt="{{__("Twitter")}}" title="{{__("Twitter")}}">
                                @include('elements.icon',['icon'=>'x-logo','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_instagram_url'))
                            <a class="m-2" href="{{getSetting('general.social_instagram_url')}}" target="_blank" alt="{{__("Instagram")}}" title="{{__("Instagram")}}">
                                @include('elements.icon',['icon'=>'logo-instagram','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_whatsapp_url'))
                            <a class="m-2" href="{{getSetting('general.social_whatsapp_url')}}" target="_blank" alt="{{__("Whatsapp")}}" title="{{__("Whatsapp")}}">
                                @include('elements.icon',['icon'=>'logo-whatsapp','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_tiktok_url'))
                            <a class="m-2" href="{{getSetting('general.social_tiktok_url')}}" target="_blank" alt="{{__("Tiktok")}}" title="{{__("Tiktok")}}">
                                @include('elements.icon',['icon'=>'logo-tiktok','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_youtube_url'))
                            <a class="m-2" href="{{getSetting('general.social_youtube_url')}}" target="_blank" alt="{{__("Youtube")}}" title="{{__("Youtube")}}">
                                @include('elements.icon',['icon'=>'logo-youtube','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_telegram_link'))
                            <a class="m-2" href="{{getSetting('general.social_telegram_link')}}" target="_blank" alt="{{__("Telegram")}}" title="{{__("Telegram")}}">
                                @include('elements.icon',['icon'=>'paper-plane','variant'=>'medium','classes' => 'text-lg opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_reddit_url'))
                            <a class="m-2" href="{{getSetting('general.social_reddit_url')}}" target="_blank" alt="{{__("Reddit")}}" title="{{__("Reddit")}}">
                                @include('elements.icon',['icon'=>'logo-reddit','variant'=>'medium','classes' => 'text-lg opacity-8'])
                            </a>
                        @endif

                        {{--                                        <div class="d-flex flex-column flex-md-row">--}}
                        {{--                                            <a href="{{route('contact')}}" class="text-dark-r mr-2 mt-0 mt-md-2 mb-2 ml-2 ml-md-0">--}}
                        {{--                                                {{__('Contact page')}}--}}
                        {{--                                            </a>--}}
                        {{--                                            @foreach(GenericHelper::getFooterPublicPages() as $page)--}}
                        {{--                                                <a href="{{route('pages.get',['slug' => $page->slug])}}" target="" class="text-dark-r m-2">{{__($page->title)}}</a>--}}
                        {{--                                            @endforeach--}}
                        {{--                                        </div>--}}

                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
