<?php

namespace App\Http\Controllers;

use App\Providers\MembersHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\SuggestionsServiceProvider;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JavaScript;
use View;

class FeedController extends Controller
{
    /**
     * Renders feed items.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('pages.feed', $this->buildFeedData($request));
    }

    public function buildFeedData(Request $request): array
    {
        // Avoid page caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $startPage = PostsHelperServiceProvider::getFeedStartPage(
            PostsHelperServiceProvider::getPrevPage($request)
        );

        $posts = PostsHelperServiceProvider::getFeedPosts(
            Auth::user()->id,
            false,
            $startPage
        );

        PostsHelperServiceProvider::shouldDeletePaginationCookie($request);

        JavaScript::put([
            'paginatorConfig' => [
                'next_page_url' => str_replace('/feed?page=', '/feed/posts?page=', $posts->nextPageUrl()),
                'prev_page_url' => str_replace('/feed?page=', '/feed/posts?page=', $posts->previousPageUrl()),
                'current_page'  => $posts->currentPage(),
                'total'         => $posts->total(),
                'per_page'      => $posts->perPage(),
                'hasMore'       => $posts->hasMorePages(),
            ],
            'initialPostIDs' => $posts->pluck('id')->toArray(),
            'sliderConfig' => [
                'suggestions' => [
                    'autoslide'=> (bool) getSetting('feed.feed_suggestions_autoplay'),
                ],
                'expiredSubs' => [
                    'autoslide'=> (bool) getSetting('feed.expired_subs_widget_autoplay'),
                ],
            ],
        ]);

        $data = [
            'posts' => $posts,
        ];

        if (!getSetting('feed.hide_suggestions_slider')) {
            $data['suggestions'] = SuggestionsServiceProvider::getSuggestedMembers();
        }

        if (!getSetting('feed.expired_subs_widget_hide')) {
            $data['expiredSubscriptions'] = MembersHelperServiceProvider::getExpiredSubscriptions();
        }

        $additionalAssets = ['js' => [], 'css' => []];
        if(getSetting('stories.stories_enabled')){
            $additionalAssets['js'][] = '/js/stories/stories-player.js';
            $additionalAssets['js'][] = '/js/stories/stories-swiper.js';
            $additionalAssets['js'][] = '/js/messenger/messenger-modal-dm.js';
            $additionalAssets['css'][] = '/css/stories.css';
        }
        $data['additionalAssets'] = $additionalAssets;

        return $data;
    }

    /**
     * Returns ( paginated ) feed psots.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeedPosts(Request $request)
    {
        return response()->json(['success'=>true, 'data'=>PostsHelperServiceProvider::getFeedPosts(Auth::user()->id, true)]);
    }

    /**
     * Returns lists of suggested members.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterSuggestedMembers(Request $request)
    {
        return response()->json(['success'=>true, 'data'=>SuggestionsServiceProvider::getSuggestedMembers(true, $request->get('filters'))]);
    }
}
