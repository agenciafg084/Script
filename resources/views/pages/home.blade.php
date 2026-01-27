@extends('layouts.generic')

@section('page_description', getSetting('site.description'))
@section('share_url', route('home'))
@section('share_title', getSetting('site.name') . ' - ' . getSetting('site.slogan'))
@section('share_description', getSetting('site.description'))
@section('share_type', 'article')
@section('share_img', GenericHelper::getOGMetaImage())

@section('scripts')
    @verbatim
        <script type="application/ld+json">
            {
              "@context": "http://schema.org",
              "@type": "Organization",
              "name": "{{getSetting('site.name')}}",
              "url": "{{getSetting('site.app_url')}}",
              "address": ""
            }
        </script>
    @endverbatim
@stop

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/pages/home.css',
            '/css/pages/search.css',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="home-header min-vh-75 relative pt-2" >
        <div class="container h-100 pb-5">
            <div class="row d-flex flex-row align-items-center h-100">
                <div class="col-12 col-md-6 mt-4 mt-md-0">
                    <h1 class="font-weight-bolder text-gradient bg-gradient-primary">{{__('Make more money')}}</h1>
                    <h1 class="font-weight-bolder text-gradient bg-gradient-primary">{{__('with your content')}}</h1>
                    <p class="font-weight-bold mt-3">🚀 {{__("Start your own premium creators platform with our ready to go solution.")}}</p>
                    <div class="pt-1 d-flex justify-content-center justify-content-md-start">
                        <a href="{{Auth::check() ? route('feed') : route('login')}}" class="btn btn-grow bg-gradient-primary  btn-round mb-0 me-1 mt-2 mt-md-0 mr-2">{{__('Try for free')}}</a>
                        <a href="{{route('search.get')}}" class="btn btn-grow btn-link  btn-round mb-0 me-1 mt-2 mt-md-0 ">
                            @include('elements.icon',['icon'=>'search-outline','centered'=>false])
                            {{__('Explore')}}</a>
                    </div>
                </div>
                <div class="col-12 col-md-6 d-none d-md-block">
                    <div class="pt-5">
                        <img src="{{asset('/img/home-header.svg')}}" alt="{{__('Make more money')}}"/>
                    </div>
                    {{--                    <img src="{{asset('/img/home-header-high-res.gif')}}" alt="{{__('Make more money')}}" class="img-fluid"/>--}}
                    {{--                    <img src="{{asset('/img/home-header.gif')}}" alt="{{__('Make more money')}}" class="img-fluid"/>--}}
                </div>
            </div>
        </div>
    </div>



    <div class="py-5 mt-4">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-4 mb-5 mb-md-0">
                    <div class="d-flex justify-content-center">
                        <img src="{{asset('/img/home-scene-1.svg')}}" class="img-fluid home-box-img" alt="{{__('Paywall social network')}}">
                    </div>
                    <div class="d-flex justify-content-center mt-4">
                        <div class="col-12 col-md-10 text-center">
                            <h5 class="text-bolder">{{__('Paywall social network')}}</h5>
                            <span>{{__('homepage_subHeader_paywall_description')}} </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 mb-5 mb-md-0">
                    <div class="d-flex justify-content-center">
                        <img src="{{asset('/img/home-scene-2.svg')}}" class="img-fluid home-box-img" alt="{{__('For fans and creators')}}">
                    </div>
                    <div class="d-flex justify-content-center mt-4">
                        <div class="col-12 col-md-10 text-center">
                            <h5 class="text-bolder">{{__('For fans and creators')}}</h5>
                            <span>{{__('homepage_subHeader_fans_description')}}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 mb-5 mb-md-0">
                    <div class="d-flex justify-content-center">
                        <img src="{{asset('/img/home-scene-3.svg')}}" class="img-fluid home-box-img" alt="{{__('Enjoy quality content')}}">
                    </div>
                    <div class="d-flex justify-content-center mt-4">
                        <div class="col-12 col-md-10 text-center">
                            <h5 class="text-bolder">{{__('Enjoy quality content')}}</h5>
                            <span>{{__("homepage_subHeader_content_description")}}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="mt-5 py-5 home-bg-section">
        <div class="container py-4">
            <div class="row">
                <div class="col-12 col-md-6 d-none d-md-flex justify-content-center">
                    <img src="{{asset('/img/home-creators.svg')}}" class="home-mid-img" alt="{{__('Make more money')}}">
                </div>
                <div class="col-12 col-md-6">
                    <div class="w-100 h-100 d-flex justify-content-center align-items-center">
                        <div class="pl-4 pl-md-5">
                            <h2 class="font-weight-bolder m-0">{{__('Create your profile')}}</h2>
                            <h2 class="font-weight-bolder m-0">{{__('in just a few clicks')}}</h2>
                            <div class="my-4 col-9 px-0">
                                <p>{{__("become a creator long")}}</p>
                            </div>
                            <div>
                                <a href="{{Auth::check() ? route('my.settings',['type'=>'verify']) : route('login') }}" class="btn bg-gradient-primary btn-grow btn-round mb-0 me-1 mt-2 mt-md-0 p-3">{{__('Become a creator')}}</a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="py-5">
        <div class="container py-4">
            <div class="text-center">
                <h2 class="font-weight-bolder ">{{__('Main features')}}</h2>
                <p>{{__("Here's a glimpse at the main features our script offers")}}</p>
            </div>
            <div class="row">


                <div class="col-12 col-md-4 mt-5 px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row-reverse mb-3">
                        @include('elements.icon',['icon'=>'wallet-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Advanced paywall")}}</h5>
                    <p class="mb-0">{{__("homepage_paywall_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row-reverse mb-3">
                        @include('elements.icon',['icon'=>'albums-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Advanced posting capabilities")}}</h5>
                    <p class="mb-0">{{__("homepage_posting_description")}}</p>
                </div>

                {{--                <div class="col-12 col-md-4 mt-5 px-4 py-3 rounded my-2 w-100">--}}
                {{--                    <div class="flex-row-reverse mb-3">--}}
                {{--                        @include('elements.icon',['icon'=>'hardware-chip-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])--}}
                {{--                    </div>--}}
                {{--                    <h5 class="text-bolder">{{__("AI Ready")}}</h5>--}}
                {{--                    <p class="mb-0">{{__("homepage_ai_description")}}</p>--}}
                {{--                </div>--}}

                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'chatbubbles-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Live chat & Notifications")}}</h5>
                    <p class="mb-0">{{__("homepage_chat_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row-reverse mb-3">
                        @include('elements.icon',['icon'=>'phone-portrait-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Mobile Ready")}}</h5>
                    <p class="mb-0">{{__("homepage_mobile_description")}}</p>
                </div>


                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'moon-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Light & Dark themes")}}</h5>
                    <p class="mb-0">{{__("homepage_themes_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'language-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("RTL & Locales")}}</h5>
                    <p class="mb-0">{{__("homepage_rtl_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'bookmarks-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Post Bookmarks & User lists")}}</h5>
                    <p class="mb-0">{{__("homepage_lists_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'flag-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Content flagging and User reports")}}</h5>
                    <p class="mb-0">{{__("homepage_reports_description")}}</p>
                </div>

                <div class="col-12 col-md-4 mt-5 text-left px-4 py-3 rounded my-2 w-100">
                    <div class="flex-row mb-3">
                        @include('elements.icon',['icon'=>'videocam-outline','variant'=>'large','centered'=>false,'classes'=>'text-primary'])
                    </div>
                    <h5 class="text-bolder">{{__("Live streaming")}}</h5>
                    <p class="mb-0">{{__("homepage_live_description")}}</p>
                </div>


            </div>
        </div>
    </div>

    {{--    <div class="my-5 py-2">--}}
    {{--        <div class="container">--}}
    {{--            <div class="text-center mb-5">--}}
    {{--                <h2 class="font-weight-bolder">{{__("Earnings simulator")}}</h2>--}}
    {{--                <p>{{__("Calculate the rough ammount you can earn on our platform, based on your subscription price and followers count.")}}</p>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    </div>--}}

    <div class="py-5 home-bg-section">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="font-weight-bolder">{{__("Featured creators")}}</h2>
                <p>{{__("Here's list of currated content creators to start exploring now!")}}</p>
            </div>

            <div class="creators-wrapper">
                <div class="row px-3">
                    @if(count($featuredMembers))
                        @foreach($featuredMembers as $member)
                            <div class="col-12 col-md-4 p-1">
                                <div class="p-2">
                                    @include('elements.feed.suggestion-card',['profile' => $member])
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="py-5 white-section ">
        <div class="container py-4">
            <div class="text-center">
                <h2 class="font-weight-bolder">{{__("Got questions?")}}</h2>
                <p>{{__("Don't hesitate to send us a message at")}} - <a href="{{route('contact')}}">{{__("Contact")}}</a> </p>
            </div>
        </div>
    </div>
@stop
