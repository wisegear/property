@extends('layouts.admin')
@section('content')   

<div class="mb-4 w-4/5 mx-auto">
    <h1 class="text-center text-xl font-bold">Blog Posts</h1>
    <p class="text-gray-500 text-sm">Lorem ipsum dolor sit amet consectetur adipisicing elit. Eos omnis quidem odio placeat, non voluptate adipisci ad obcaecati, nesciunt quod quasi esse aliquid ducimus! Debitis commodi accusantium nesciunt, non animi aspernatur? Officia optio debitis veritatis architecto dolores quisquam id, error et nam earum rerum vero itaque ab laboriosam autem repudiandae.</p>
</div>

<div class=" text-center my-5">
    <a class="border rounded-md py-1 px-2 bg-indigo-300 text-sm" href="/admin/blog-categories" role="button">Manage Categories</a>
</div>

<div class="">
    <table class="table-fixed text-center">
        <thead class="border">
            <tr class="border bg-gray-50">
                <th class="border w-1/12">ID</th>
                <th class="border w-3/12">Title</th>
                <th class="border w-1/12">Created By</th>
                <th class="border w-1/12">Date Created</th>
                <th class="border w-1/12">Comments</th>
                <th class="border w-1/12">Action</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($posts as $post)
            <tr class="border">
                <td class="border">{{ $post->id }}</td>
                <td class="border"><a href="../blog/{{ $post->slug }}" class="text-indigo-700 hover:text-indigo-300">{{ $post->title }}</a></td>
                <td class="border">{{ $post->users->name }}</td>
                <td class="border">{{ $post->created_at->diffForHumans() }}</td>
                <td class="border">{{ $post->comments->count() }}</td>
                <td class="border">
                <form action="../blog/{{ $post->id }}" method="POST" onsubmit="return confirm('Do you really want to delete this post?');">
                    {{ csrf_field() }}
                    {{ method_field ('DELETE') }} 			
                    <a class="border rounded-md bg-yellow-500 p-1 inline-block text-xs text-white font-bold" href="../blog/{{ $post->id }}/edit" role="button">Edit</a>
                    <button type="submit" class="border rounded-md bg-red-500 p-1 inline-block text-xs text-white font-bold">Del</button>
                </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="w-1/2 mx-auto my-5"> 
        {{ $posts->links() }} 
    </div>
</div>

@endsection