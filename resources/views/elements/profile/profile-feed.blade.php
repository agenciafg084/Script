<div class="mt-3 inline-border-tabs">
    <nav class="nav nav-pills nav-justified text-bold">
        <a class="nav-item nav-link {{$activeFilter == false ? 'active' : ''}}" href="{{route('profile',['username'=> $user->username])}}">{{trans_choice('posts', $posts->total(), ['number'=> short_number($posts->total()) ])}} </a>

        @if($filterTypeCounts['image'] > 0)
            <a class="nav-item nav-link {{$activeFilter == 'image' ? 'active' : ''}}" href="{{route('profile',['username'=> $user->username]) . '?filter=image'}}">{{trans_choice('images', $filterTypeCounts['image'], ['number'=> short_number($filterTypeCounts['image'])])}}</a>
        @endif

        @if($filterTypeCounts['video'] > 0)
            <a class="nav-item nav-link {{$activeFilter == 'video' ? 'active' : ''}}" href="{{route('profile',['username'=> $user->username]) . '?filter=video'}}">{{trans_choice('videos', $filterTypeCounts['video'], ['number'=> short_number($filterTypeCounts['video'])])}}</a>

        @endif

        @if($filterTypeCounts['audio'] > 0)
            <a class="nav-item nav-link {{$activeFilter == 'audio' ? 'active' : ''}}" href="{{route('profile',['username'=> $user->username]) . '?filter=audio'}}">{{trans_choice('audio', $filterTypeCounts['audio'], ['number'=> short_number($filterTypeCounts['audio'])])}}</a>
        @endif

        @if(getSetting('streams.streaming_driver') !== 'none')
            @if(isset($filterTypeCounts['streams']) && $filterTypeCounts['streams'] > 0)
                <a class="nav-item nav-link {{$activeFilter == 'streams' ? 'active' : ''}}" href="{{route('profile',['username'=> $user->username]) . '?filter=streams'}}"> {{short_number($filterTypeCounts['streams'])}} {{trans_choice('streams', $filterTypeCounts['streams'], ['number'=> short_number($filterTypeCounts['streams'])])}}</a>
            @endif
        @endif

    </nav>
</div>
<div class="justify-content-center align-items-center {{(Cookie::get('app_feed_prev_page') && PostsHelper::isComingFromPostPage(request()->session()->get('_previous'))) ? 'mt-3' : 'mt-4'}}">
    @if($activeFilter !== 'streams')
        @include('elements.feed.posts-load-more', ['classes' => 'mb-2'])
        <div class="feed-box mt-0 posts-wrapper">
            @include('elements.feed.posts-wrapper',['posts'=>$posts])
        </div>
    @else
        <div class="streams-box mt-4 streams-wrapper mb-4">
            @include('elements.search.streams-wrapper',['streams'=>$streams,'showLiveIndicators'=>true, 'showUsername' => false])
        </div>
    @endif
    @include('elements.feed.posts-loading-spinner')
</div>
