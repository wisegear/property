@extends('layouts.app')
@section('content')    
<div class="">    
    <h1 class="text-4xl font-bold text-center">{{$post->title}}</h1>
        <ul class="flex justify-center space-x-8 my-4">
            <li><a href="/profile/{{ $post->users->name_slug }}" class="text-gray-700 hover:text-sky-700"><i class="fa-solid fa-user mr-2"></i>{{ $post->users->name }}</a></li>
            <li class="text-gray-700"><i class="fa-solid fa-calendar-days mr-2"></i>{{ $post->created_at->diffForHumans() }}</li>
            <li><a href="/blog?category={{ $post->blogcategories->name }}" class="text-gray-700 hover:text-sky-700"><i class="fa-solid fa-folder mr-2"></i>{{ $post->blogcategories->name }}</a></li>
        </ul>    
    <p class="text-center md:w-1/2 mx-auto text-gray-500">{{$post->excerpt }}</p>
        <div class="flex space-x-4 my-6 justify-center">
            @foreach ($post->blogtags as $tag)
                <a href="/blog?tag={{ $tag->name }}" class="p-1 text-xs uppercase border border-gray-400 font-bold bg-gray-200 hover:bg-sky-700 hover:text-white">{{ $tag->name }}</a>
            @endforeach
        </div>
    <img src="../images/media/large-{{$post->image}}" class="my-6 w-full h-[300px]">
</div>



<!-- Post Text -->

<div class="flex space-x-10">
    
    <!-- Post Area -->

    <div class="md:w-9/12">

        <div class="md:hidden">
            @if(count($post->getBodyHeadings('h2')) > 3)
                <div class="toc mb-10 pl-4">
                    <h2 class="font-bold text-xl mb-2 border-b border-gray-300">Table of contents</h2>
                    <ul class="space-y-2">
                        @foreach($post->getBodyHeadings('h2') as $heading)
                        <li><a href="#{{ Str::slug($heading) }}" class="hover:text-blue-700">{{ $heading }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Post Text, separate from other content due to Wise1Text -->

        <div class="wise1text">
            {!! str_replace('{gallery}', view('snippets.gallery', ['images' => $galleryImages])->render(), $post->addAnchorLinkstoHeadings()) !!}
        </div>

        <!-- Author Box -->
        
        <div class="flex flex-col border-y border-gray-300 p-4 my-10 space-y-2">
            <img src="/images/avatars/{{ $post->users->avatar }}" class="mx-auto rounded-full h-16 border-2 border-gray-400 p-1">
            <p class="font-bold text-center text-gray-700">Lee Wisener CeMAP, CeRER, CeFAP, CSME</p>
            <p class="text-center text-sm">{{ $post->users->bio }}</p>
        </div>

        <!-- Comments -->

        <div>
            <h2 class="font-bold text-xl">Comments:</h2>
            <div class="my-5">
                <!-- Get comments and sort by latest date -->
                @foreach( $post->comments->sortByDesc('created_at') as $comment)
                <div class="flex space-x-5 text-sm">
                    <!-- Name of the user commenting -->
                    <a href="/profile/{{ $comment->users->name_slug }}" class="text-blue-500">{{ $comment->users->name }}</a>
                        <a>{{ $comment->created_at->diffForHumans() }}</a>
                        <!-- Only admin can edit/delete -->

                        <div class="flex-grow"></div> <!-- Spacer to move delete to end -->
                        @can ('Admin')
                            <a class="border bg-red-100 px-2 ml-auto self-end">
                                <form action="/comments/{{ $comment->id }}" method="POST" onsubmit="return confirm('Do you really want to delete this Comment?');">
                                {{ csrf_field() }}
                                {{ method_field ('DELETE') }} 
                                <button type="submit" class="text-xs">Delete</button>
                                </form>
                            @endcan
                            </a>
                </div>
                <!-- Display the comment text-->
                <div class="mt-3 mb-5 pb-4 text-sm border-b">
                        <p class="">{{ $comment->body }}</p>
                </div>
                @endforeach
            </div>

            <!-- Must be a member to see comment box and comment -->
            @if (Auth::check() && Auth::user()->can('Member'))
            <div class="md:w-3/4 mx-auto my-10">
                <p class="text-sm text-gray-500">Comment on this post</p>
                <form method="POST" action="/comments/{{ $post->id }}" enctype="multipart/form-data">
                {{ csrf_field() }}      
                {{ method_field('PUT') }}    
                    <div class="form-group">
                        <div class="mt-2" style="color: red;">{{ $errors->has('comment') ? 'At least some text is required' : '' }}</div>
                        <textarea class="w-full bg-gray-50 border-gray-200 text-sm" name="comment" id="comment" placeholder="Reply here...">{{ old('text') }}</textarea>
                    </div>
                    <div class="text-center"><button type="submit" class="border bg-gray-400 font-bold p-2 text-xs uppercase hover:bg-gray-500 hover:text-white mt-2">Add Reply</button></div>
                </form>
            </div>
            @else <!-- If not a member show login comment -->
                <div>
                    <p class="font-semibold text-red-500 text-center mt-10">You must <a href="/login" class="text-gray-700">log in</a> or <a href="/register" class="text-gray-700">register</a> if you want to comment on this article</p>
                </div>
            @endif
 
        </div>

        <!-- End comments section -->

    </div>

    <!-- SideBar -->

    <div class="hidden md:block w-3/12 border-l border-gray-300">
        <div id="TOC" class="sticky top-10">
            <!-- TOC -->
            @if(count($post->getBodyHeadings('h2')) > 3)
                <div class="toc mb-10 pl-4">
                    <h2 class="font-bold text-xl mb-4 border-b border-gray-300">Table of contents</h2>
                    <ul class="space-y-2">
                        @foreach($post->getBodyHeadings('h2') as $heading)
                        <li><a href="#{{ Str::slug($heading) }}" class="hover:text-blue-700">{{ $heading }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <!-- Recent Posts -->
            <div class="mb-10 pl-4">
                <h2 class="font-bold text-xl mb-4 border-b border-gray-300">Recent Posts</h2>
                @foreach( $recent_posts as $post)
                    <a href="/blog/{{ $post->slug }}" class="hover:opacity-70">
                        <img src="/images/media/small-{{ $post->image }}" class="">
                        <h2 class="my-2 text-sm pb-6">{{ $post->title }}</h2>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

</div>





@endsection
