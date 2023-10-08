@extends('layouts.app')
@section('content')    


<!-- Adding TinyMCE editor, this is the code required to make it work further down-->
  <script>
    tinymce.init({
      selector: '#editor',
      plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
    });
  </script>

<h2 class="text-2xl text-center font-bold mb-10">Edit Existing Blog Post</h2>

<div class="w-3/4 mx-auto">
    <form method="POST" action="/blog/{{$post->id}}" enctype="multipart/form-data">
        {{ csrf_field() }}
        {{ method_field('PUT') }}
        <!-- Upload Featured Image -->
        <div>
            <div class="flex space-x-10 text-xs justify-center mb-6">
                <input type="text" id="imageInput" name="imageInput" class="w-[500px] text-xs" placeholder="Copy image name here...">
                <a href="/media" target="_blank"><button type="button" class="border p-2 uppercase text-xs bg-orange-300 font-bold">View Available Images</button></a>
            </div>
            <div class="flex space-x-4">
                <img src="/images/media/{{$post->image}}" id="old_image" alt="Old Image" class="w-6/12 h-[300px] my-4 border border-gray-300 p-2">
                <div id="imageContainer" src="" alt="Image Preview" class="w-6/12 h-[300px] my-4 border border-gray-300 p-2"></div>
            </div>
        </div>

        <!-- Display the new image -->
        <div id="imageContainer1" class="text-center mt-2 text-red-500">
            {{ $errors->has('newimage') ? 'An image is required' : '' }}
        </div>

        <div class="mx-auto" id="output"></div>  

            <!-- Post Date --> 
            <div class="mt-10">
                <p class="font-semibold text-gray-700 mb-2">Enter Date of Post<span class="text-gray-400">(dd-mm-yyyy)</span>:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('title') ? 'A title is required' : '' }}</div>
                <input class="border text-sm h-8 w-full" type="text" id="date" name="date"  value="{{ $post->date->format('d-m-Y') }}">
            </div>           

            <!-- Post Title --> 
            <div class="mt-3">
                <p class="font-semibold text-gray-700 mb-2">Enter title:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('title') ? 'A title is required' : '' }}</div>
                <input class="border  h-8 w-full" type="text" id="title" name="title"  value="{{ $post->title }}">
            </div>  
            
            <!-- Text area with TinyMCE for Excerpt of post -->
            <div class="form-group my-10">
                <p class="font-semibold text-gray-700 mb-2">Enter an excerpt:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('excerpt') ? 'An excerpt is required' : '' }}</div>
                <textarea class="border w-full" id="excerpt" name="excerpt"  value="{{ $post->excerpt }}">{{ $post->excerpt }}</textarea>
            </div> 

            <!-- Text area with TinyMCE for Body of post -->
            <div class="my-10">
                <p class="font-semibold text-gray-700 mb-2">Enter the body of the post:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('body') ? 'At least some text is required for the body' : '' }}</div>
                <textarea class="w-full" name="body" id="editor">{{ $post->body }}</textarea>    
            </div>
                
        <!-- Manage category selection -->
        <div class="border my-10">
            <p class="font-semibold text-gray-700 mb-2">Select a category:</p>
            <div class="mt-2 text-red-500">{{ $errors->has('category') ? 'A category is required' : '' }}</div>
            <div class="">
                <ul class="flex justify-evenly mt-4 mb-10">
                    @foreach ($categories as $category)
                    <li class="">
                        {{ $category->name }}
                        <input type="radio" id="category" name="category" value="{{ $category->id }}"
                            @if ($post->categories_id === $category->id)
                                checked="checked"
                            @endif
                        >
                    </li>
                    @endforeach 
                </ul>
            </div>
        </div>  

        <!-- Post tags -->
        <div class="my-10">
            <p class="font-semibold text-gray-700 mb-2">Enter some tags if required:</p>
            <input type="text" class="w-full border text-sm h-8" id="tags" name="tags" value="{{ $split_tags }}">
        </div>

        <!-- Gallery, optional -->
        <div class="my-10 flex flex-col space-y-6">
            <label for="gallery" class="font-bold">Gallery Images:</label>
            <input type="file" name="gallery[]" multiple>
        </div>

        <!-- Post Options -->   
        <div class="">
            <p class="font-semibold text-gray-700 mb-2">Post Options:</p>
            <ul class="flex border py-2 text-sm justify-evenly">           
                <li class="list-inline-item">
                    <label>Publish?</label>     
                    <input type="checkbox" class="form-field" id="published" name="published" checked="checked">
                </li>
                <li class="list-inline-item">
                    <label>Featured?</label>        
                    <input type="checkbox" class="form-field" id="featured" name="featured"
                        @if ($post->featured == 1)
                            checked="checked"
                        @endif
                    >
                </li>
            </ul>
        </div> 
        <button type="submit" class="my-10 border py-1 px-2 bg-slate-800 text-white hover:bg-slate-600">Update Post</button> 
    </form>

</div>

<script>
// Get references to the input field and image container
const imageInput = document.getElementById('imageInput');
const imageContainer = document.getElementById('imageContainer');

// Add an event listener to the input field to detect changes
imageInput.addEventListener('input', function () {
    // Get the value entered in the input field
    const imageName = imageInput.value;

    // Add the folder path to the image name
    const imagePath = `/images/media/${imageName}`; // Assuming "images/media" is the folder path

    // Create an image element
    const image = document.createElement('img');

    // Set the source of the image using the full image path
    image.src = imagePath;

    // Clear the previous image in the container
    imageContainer.innerHTML = '';

    // Append the new image to the container
    imageContainer.appendChild(image);
});
</script>

@endsection

