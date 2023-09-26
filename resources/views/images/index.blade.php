@extends('layouts.app')
@section('content')

  <!-- Media section for managing blog images, can be used for other areas also -->

    <div class="w-1/2 mx-auto">
        <!-- Upload new image -->
        <form method="POST" action="/media" enctype="multipart/form-data">
        {{ csrf_field() }}
          <div class="">
            <label>Select new image:</label>
            <input type="file" name="add-media" accept="image/*" onchange="loadFile(event)">
                <!-- Using JS to get image from temp storage and display -->
                <script>
                var loadFile = function(event) {
                var output = document.getElementById('media');
                output.src = URL.createObjectURL(event.target.files[0]);
                };
                </script> 
                <img class="img-responsive img-thumbnail" id="media">
           </div>
            <button type="submit" class="border rounded-md bg-green-500 text-sm py-1 px-2 font-bold mt-5">Insert Image</button> 
        </form>
    </div>

    <!-- Display all existing images in 5 col grid -->
    <div class="grid grid-cols-5 gap-10 mt-10">
        @foreach ($all_media as $item)
            <div>
                <img src="/images/media/small-{{ $item->name }}" class="w-full max-h-60 border border-gray-900">
                <input class="w-full text-center mt-1 text-xs text-gray-600" type="text" name="image" id="imagename" value="{{ $item->name }}">
            </div>
        @endforeach
    </div>

@endsection