@extends('layouts.app')
@section('content')    

<div class="flex flex-col md:flex-row justify-between md:space-x-10">  
    <div id="posts" class="md:w-9/12">
        @foreach ($posts as $post)
            <div class="border border-gray-300 my-10 p-2">
                <img src="../images/media/medium-{{$post->image}}" style="width: 100%; height: 300px;">
                <div class="flex items-end justify-between">
                    <h2 class="text-2xl font-bold text-gray-700 mt-4 hover:text-sky-700"><a href="/blog/{{$post->slug}}">{{ $post->title }}</a></h2>
                    @can('Admin')
                        <div class="flex space-x-4">
                            <button class="border bg-orange-300 hover:bg-orange-500 font-bold uppercase text-xs p-1"><a href="/blog/{{$post->id}}/edit">Edit</a></button>
                             <form action="/blog/{{ $post->id }}" method="POST" onsubmit="return confirm('Do you really want to delete this Post?');">
                                {{ csrf_field() }}
                                {{ method_field ('DELETE') }} 
                                <input class="border bg-red-300 hover:bg-red-500 font-bold uppercase text-xs p-1" role="button" type="submit" value="Delete">
                            </form>
                        </div>
                    @endcan
                </div>
                <ul class="flex space-x-4 text-sm">
                    <li><a href="../profile/{{$post->users->name_slug }}" class="text-gray-700 hover:text-sky-700"><i class="fa-solid fa-user mr-2"></i>{{ $post->users->name }}</a></li>
                    <li class="text-gray-700"><i class="fa-solid fa-calendar-days mr-2"></i>{{ $post->date->diffForHumans() }}</li>
                    <li><a href="/blog?category={{ $post->blogcategories->name }}" class="text-gray-700 hover:text-sky-700"><i class="fa-solid fa-folder mr-2"></i>{{ $post->blogcategories->name }}</a></li>
                </ul>
                <p class="my-4">{{ $post->excerpt }}</p>
                <div class="flex space-x-4">
                    @foreach ($post->blogtags as $tag)
                        <a href="/blog?tag={{ $tag->name }}" class="p-1 text-xs uppercase font-bold bg-gray-300 hover:bg-sky-700 hover:text-white">{{ $tag->name }}</a>
                    @endforeach
                </div>
            </div>
        @endforeach    

        <!-- Pagination -->
        <div>
            {{ $posts->links() }}
        </div>

    </div>

    <div id="sidebar" class="mt-4 md:w-3/12 grow-0">

        <!-- Search Form -->
        <div class="my-6">
         <h2 class="text-xl font-bold text-gray-700 border-b border-gray-300 mb-6">Search Blog</h2>
            <form action="/blog">
                <input type="text" class="border p-2 w-full hover:border-gray-300" name="search" id="search" placeholder="Enter a Search Term">
            </form>
        </div>

        <!-- Blog Categories -->
        <div class="my-6">
         <h2 class="text-xl font-bold text-gray-700 border-b border-gray-300 mb-4">Blog Categories</h2>
            <ul>
                @foreach ($categories as $category)
                    <li><a href="/blog?category={{ $category->name }}" class="text-gray-700 mt-4 hover:text-sky-700">{{ $category->name }}</a></li>
                @endforeach
            </ul>
        </div>   

        <!-- Blog Tags -->
        <div class="my-6">
         <h2 class="text-xl font-bold text-gray-700 border-b border-gray-300 mb-4">Popular Tags</h2>
            @foreach ($popular_tags as $tag)
                <button class="mr-2 mb-4">
                    <a href="/blog?tag={{ $tag->name }}" class="border p-2 text-xs uppercase bg-gray-300 hover:bg-sky-700 hover:text-white">{{ $tag->name }}</a>
                </button>
            @endforeach
        </div>  

        @can('Admin')
        <!-- Unpublished Posts -->
        <div class="my-6">
         <h2 class="text-xl font-bold text-gray-700 border-b border-gray-300 mb-4"><i class="fa-solid fa-user-secret text-red-800"></i> Unpublished Posts</h2>
            <div class="flex flex-col space-y-2 text-sm">
                @foreach ($unpublished as $post)
                    <a href="../blog/{{$post->id}}/edit" class="text-gray-700 hover:text-sky-700">{{ $post->title }}</a>
                @endforeach
            </div>
        </div> 

        <!-- Admin -->
        <div class="my-6">
         <h2 class="text-xl font-bold text-gray-700 border-b border-gray-300 mb-4"><i class="fa-solid fa-user-secret text-red-800"></i> Admin Tools</h2>
            <p class="hover:text-sky-700"><a href="/blog/create">Create New Post</a></p>
        </div> 
        @endcan

    </div>
</div>

@endsection
