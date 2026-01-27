@extends('layouts.user-no-nav')

@section('page_title', __('Messenger'))

@section('styles')
    {!!
        Minify::stylesheet(array_merge([
            '/libs/@selectize/selectize/dist/css/selectize.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap4.css',
            '/libs/dropzone/dist/dropzone.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/messenger.css',
            '/css/pages/checkout.css'
         ],$additionalAssets['css']))->withFullUrl()
    !!}
    @if(getSetting('stories.stories_enabled'))
        <link rel="stylesheet" href="{{asset('/libs/swiper/swiper-bundle.min.css')}}">
    @endif
@stop

@section('scripts')
    {!!
        Minify::javascript(array_merge([
            '/js/messenger/messenger.js',
            '/js/messenger/elements.js',
            '/libs/@selectize/selectize/dist/js/selectize.min.js',
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/js/pages/lists.js',
            '/js/pages/checkout.js',
            '/libs/autolinker/dist/autolinker.min.js'
         ],$additionalAssets['js']))->withFullUrl()
    !!}
@stop

@section('content')
    @include('elements.uploaded-file-preview-template')
    @include('elements.photoswipe-container')
    @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
    @include('elements.feed.post-delete-dialog')
    @include('elements.feed.post-list-management')
    @include('elements.messenger.message-price-dialog')
    @include('elements.checkout.checkout-box')
    @include('elements.attachments-uploading-dialog')
    @include('elements.messenger.locked-message-no-attachments-dialog', ['type' => lcfirst(__('Messages'))])
    <div class="d-flex flex-wrap">
        <div class=" col-12 px-0">
            <div class="container messenger ">
                <div class="row ">
                    <div class="col-3 col-xl-3 col-lg-3 col-md-3 col-sm-3 col-xs-2 border border-right-0 border-left-0 rounded-left conversations-wrapper  overflow-hidden border-top ">
                        <div class="d-flex justify-content-center justify-content-md-between pt-3 pr-1 pb-2">
                            <h5 class="d-none d-md-block text-truncate pl-3 pl-md-0 text-bold {{(Cookie::get('app_theme') == null ? (getSetting('site.default_user_theme') == 'dark' ? '' : 'text-dark-r') : (Cookie::get('app_theme') == 'dark' ? '' : 'text-dark-r'))}}">{{__('Contacts')}}</h5>
                            <span data-toggle="tooltip" title="" class="pointer-cursor"
                                  @if(!count($availableContacts))
                                      data-original-title="{{trans_choice('Before sending a new message, please subscribe to a creator a follow a free profile.',['user' => 0])}}"
                                  @else
                                      data-original-title="{{trans_choice('Send a new message',['user' => 0])}}"
                                  @endif
                            >
                                <a title="" class="pointer-cursor new-conversation-toggle" data-original-title="{{trans_choice('Send a new message',['user' => 0])}}">  <div class="mt-0 h5">@include('elements.icon',['icon'=>'create-outline','variant'=>'medium']) </div> </a>
                            </span>
                        </div>
                        <div class="conversations-list">
                            @if($lastContactID == false)
                                <div class="d-flex mt-3 mt-md-2 pl-3 pl-md-0 mb-3 pl-md-0"><span>{{__('Click the text bubble to send a new message.')}}</span></div>
                            @else
                                @include('elements.preloading.messenger-contact-box', ['limit'=>3])
                            @endif
                        </div>
                    </div>
                    <div class="col-9 col-xl-9 col-lg-9 col-md-9 col-sm-9 col-xs-10 border conversation-wrapper rounded-right p-0 d-flex flex-column ">
                        @include('elements.message-alert')
                        @include('elements.messenger.messenger-conversation-header')
                        @include('elements.messenger.messenger-new-conversation-header')
                        @include('elements.preloading.messenger-conversation-header-box')
                        @include('elements.preloading.messenger-conversation-box')
                        <div class="conversation-content pt-4 pb-1 px-3 flex-fill">
                        </div>
                        <div class="dropzone-previews dropzone w-100 ppl-0 pr-0 pt-1 pb-1"></div>
                        <div class="conversation-writeup pt-1 pb-1 d-flex align-items-center mb-1 {{!$lastContactID ? 'hidden' : ''}}">
                            {{-- Left side--}}
                            <div class="messenger-buttons-wrapper d-flex mx-2">
                                <button class="btn btn-outline-primary btn-rounded-icon messenger-button attach-file file-upload-button to-tooltip" data-placement="top" title="{{__('Attach file')}}">
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'document','variant'=>''])
                                    </div>
                                </button>
                            </div>
                            {{-- Input --}}
                            <form class="message-form w-100">
                                <div class="input-group messageBoxInput-wrapper">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="receiverID" id="receiverID" value="">
                                    <textarea name="message" class="form-control messageBoxInput dropzone" placeholder="{{__('Write a message..')}}" onkeyup="messenger.textAreaAdjust(this)"></textarea>
                                    {{--                                    <div class="input-group-append z-index-3 d-flex align-items-center justify-content-center">--}}
                                    {{--                                        <span class="h-pill h-pill-primary rounded mr-3 trigger" data-toggle="tooltip" data-placement="top" title="Like" >😊</span>--}}
                                    {{--                                    </div>--}}
                                </div>
                            </form>
                            {{-- Right side --}}
                            <div class="messenger-buttons-wrapper d-flex mx-2">
                                @if((GenericHelper::creatorCanEarnMoney(Auth::user()) && !(!GenericHelper::isUserVerified() && getSetting('site.enforce_user_identity_checks'))) /*|| Auth::user()->role_id === 1*/)
                                    <button class="btn btn-outline-primary btn-rounded-icon messenger-button mx-0 mx-md-1 to-tooltip" data-placement="top" title="{{__('Message price')}}" onClick="messenger.showSetPriceDialog()">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <span class="message-price-lock">@include('elements.icon',['icon'=>'lock-open','variant'=>''])</span>
                                            <span class="message-price-close d-none">@include('elements.icon',['icon'=>'lock-closed','variant'=>''])</span>
                                        </div>
                                    </button>
                                @endif
                                <button class="btn btn-outline-primary btn-rounded-icon messenger-button  mx-0 mx-md-1 tip-btn to-tooltip"
                                        title="{{__('Send a tip')}}"
                                        data-placement="top"
                                        data-toggle="modal"
                                        data-target="#checkout-center"
                                        data-type="chat-tip"
                                        data-first-name="{{Auth::user()->first_name}}"
                                        data-last-name="{{Auth::user()->last_name}}"
                                        data-billing-address="{{Auth::user()->billing_address}}"
                                        data-country="{{Auth::user()->country}}"
                                        data-city="{{Auth::user()->city}}"
                                        data-state="{{Auth::user()->state}}"
                                        data-postcode="{{Auth::user()->postcode}}"
                                        data-available-credit="{{Auth::user()->wallet->total}}"
                                >
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'wallet','variant'=>''])
                                    </div>
                                </button>
                                <button class="btn btn-outline-primary btn-rounded-icon messenger-button send-message mx-0 mx-md-1 to-tooltip" onClick="messenger.sendMessage()" data-placement="top" title="{{__('Send message')}}">
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'paper-plane','variant'=>''])
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('elements.standard-dialog',[
    'dialogName' => 'message-delete-dialog',
    'title' => __('Delete message'),
    'content' => __('Are you sure you want to delete this message?'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'messenger.deleteMessage();',
])
@stop
