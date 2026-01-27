<div class="px-3 new-post-comment-area">
    <div class="d-flex justify-content-center align-items-center">
        <img class="rounded-circle" src="{{Auth::user()->avatar}}">
        <div class="input-group">
            <textarea name="message" class="form-control comment-textarea mr-2 ml-3 comment-text new-comment-textarea" placeholder="{{__('Write a message..')}}"  onkeyup="textAreaAdjust(this)"></textarea>
            <span class="invalid-feedback pl-4 text-bold" role="alert"></span>
        </div>
        <div class="">
            <button class="btn btn-outline-primary btn-rounded-icon" onclick="Post.addComment({{$post->id}})">
                <div class="d-flex justify-content-center align-items-center">
                    @include('elements.icon',['icon'=>'paper-plane','variant'=>''])
                </div>
            </button>
        </div>
    </div>
</div>
