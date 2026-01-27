@php
    $announcement = GenericHelper::getLatestGlobalMessage();
    if($announcement){
        $isSticky = $announcement->is_sticky;
        if(in_array(Route::currentRouteName(),['feed']) && \App\Providers\GenericHelperServiceProvider::isMobileDevice()){
            $isSticky = true;
        }
    }
@endphp

@if( $announcement &&
    ($announcement->is_global || (!$announcement->is_global && in_array(Route::currentRouteName(),['home']))) &&
    !request()->cookie('dismissed_banner_' . $announcement->id) &&
    (!$announcement->expiring_at || ($announcement->expiring_at && $announcement->expiring_at > \Carbon\Carbon::now()))
)
    <div class="{{ $isSticky ? 'sticky-info-bar' : '' }}
            alert alert-dismissible fade show mb-0 border-0 global-announcement-banner
            bg-gradient-faded-primary text-dark"
         role="alert"
         data-banner-id="{{ $announcement->id }}">

        @if($announcement->is_dismissible)
            <button type="button"
                    class="close text-white"
                    data-dismiss="alert"
                    aria-label="Close"
                    onclick="dimissGlobalAnnouncement('{{ $announcement->id }}')">
                <span aria-hidden="true">&times;</span>
            </button>
        @endif

        <!-- NEW WRAPPER -->
        <div class="announcement-body text-center">
            <div class="content {{ $announcement->size === 'small' ? '' : 'py-1' }}">
                {!! $announcement->content !!}
            </div>
        </div>

    </div>

@endif
