@extends('layouts.app')
@section('content')    

<!-- Logo -->
<div class="flex justify-center">
    <img src="../images/site/logo.svg" class="w-[200px] mb-4">
</div>

<!-- Description -->
<div class="md:w-7/12 mx-auto my-4">
    <p class="text-center">A personal property and mortgage blog with general information related to this sector in the main but there is other information related to finance in general. There is no particular audience intended here, just anyone that is interested in the content.</p>
</div>

<!-- Buttons -->
<div class="flex justify-center space-x-10">
    <a href="/important"><button class="border p-2 text-xs uppercase font-bold bg-orange-300 hover:bg-orange-500 hover:text-white">Important</button></a>
    <a href="/about"><button class="border p-2 text-xs uppercase font-bold bg-gray-300 hover:bg-sky-700 hover:text-white">About</button></a>
</div>

<!-- Featured Posts -->
<div class="border-b border-gray-300 font-bold my-10">
    <p>Featured Posts</p>        
</div>

<div class="grid md:grid-cols-4 gap-5">
    @foreach ($posts as $post)
    <div>
        <img src="../images/media/{{$post->image}}" style="width: 100%; height: 175px;">
        <h2 class="text-gray-700 py-2 text-center hover:text-sky-700"><a href="/blog/{{$post->slug}}">{{$post->title}}</a></h2>
    </div>
    @endforeach
</div>

<!-- Recent Posts -->

<div class="my-10">
    <p class="text-center md:w-8/12 mx-auto border-b border-gray-300 mb-4 font-bold">Recent Posts</p>  
    @foreach ($recent_posts as $post)
    <?php 
        $words = str_word_count($post->body);
        $time = ceil( $words / 250);
    ?>
        <div class="flex md:w-8/12 space-x-4 items-center text-gray-700 justify-center mx-auto">
            <p class="hidden md:block md:w-2/12">{{$post->created_at->diffForHumans() }}</p>
            <h2 class="md:w-6/12 py-2 text-center hover:text-sky-700"><a href="/blog/{{$post->slug}}">{{$post->title}}</a></h2>
            <p class="hidden md:block md:w-2/12"><?= $time ?> min read</p>
        </div>
    @endforeach
</div>


@endsection



