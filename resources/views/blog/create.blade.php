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



<h2 class="text-2xl text-center font-bold mb-10">Create a New Post</h2>

<div class="w-3/4 mx-auto">

    <form method="POST" action="/blog" enctype="multipart/form-data">
        {{ csrf_field() }}
        
        <!-- Upload Featured Image -->
        <div>
            <div class="flex justify-center space-x-10">
            <input type="text" id="imageInput" name="imageInput" class="w-[500px] text-xs" placeholder="Copy image name here...">
            <a href="/media" target="_blank"><button type="button" class="border p-2 uppercase text-xs bg-orange-300 font-bold">View Available Images</button></a>
            </div>
        </div>

        <!-- Display the new image -->
        <div id="imageContainer" class="text-center mt-2 text-red-500">
            {{ $errors->has('newimage') ? 'An image is required' : '' }}
        </div>

        <div class="mx-auto" id="output"></div>            
            
            <!-- Post Title --> 
            <div class="mt-3">
                <p class="font-semibold text-gray-700 mb-2">Enter title:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('title') ? 'A title is required' : '' }}</div>
                <input class="border text-sm h-8 w-full" type="text" id="title" name="title"  value="{{ old('title') }}" placeholder="Enter a title for this post">
            </div>  
            
            <!-- Text area with TinyMCE for Excerpt of post -->
            <div class="form-group my-10">
                <p class="font-semibold text-gray-700 mb-2">Enter an excerpt:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('excerpt') ? 'A excerpt is required' : '' }}</div>
                <textarea class="border text-sm w-full" id="excerpt" name="excerpt"  value="{{ old('excerpt') }}" placeholder="Enter a excerpt for this post"></textarea>
            </div> 

            <!-- Text area with TinyMCE for Body of post -->
            <div class="my-10">
                <p class="font-semibold text-gray-700 mb-2">Enter the body of the post:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('body') ? 'At least some text is required for the body' : '' }}</div>
                <textarea class="w-full border" name="body" id="editor" placeholder="This is the body of the post">{{ old('body') }}</textarea>    
            </div>


            <!-- Manage category selection -->
            <div class="border border-gray-300 p-2 my-10">
                <p class="font-semibold text-gray-700 mb-2">Select a category for the post:</p>
                <div class="mt-2 text-red-500">{{ $errors->has('category') ? 'A category is required' : '' }}</div>
                <div class="">
                    <ul class="flex justify-evenly mt-4 mb-10">
                    @foreach ($categories as $category)
                    <li class="">
                    <input type="radio" id="category" name="category" value="{{ $category->id }}"> 
                    {{ $category->name }}            
                    </li class="list-inline-item">
                    @endforeach
                    </ul>
                </div>
            </div>  

            <!-- Post Tags -->
            <div class="my-10">
                <p class="font-semibold text-gray-700 mb-2">Enter some tags if required:</p>
                <input type="text" class="w-full border text-sm h-8" id="tags" name="tags" placeholder="Enter tags for the post, eg. one-two-three">
            </div>

            <!-- Gallery, optional -->
            <div class="my-10 flex flex-col space-y-6">
                <label for="gallery" class="font-bold">Gallery Images:</label>
                <input type="file" name="gallery[]" multiple>
            </div>


            <!-- Manage the post options -->
            <div class="">
                <p class="font-semibold text-gray-700 mb-2">Post Options:</p>
                <ul class="flex border border-gray-300 py-2 text-sm justify-evenly">           
                    <li class="list-inline-item">
                        <label>Publish?</label>     
                        <input type="checkbox" class="form-field" id="published" name="published">
                    </li>
                    <li class="list-inline-item">
                        <label>Featured?</label>        
                        <input type="checkbox" class="form-field" id="featured" name="featured">
                    </li>
                </ul>
            </div> 
            <button type="submit" class="my-10 border p-2 bg-gray-700 text-white uppercase text-sm hover:bg-green-500">Create New Post</button> 
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

