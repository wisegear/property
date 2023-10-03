
<h2 class="">Gallery <span class="text-gray-500 text-xs uppercase">(click to enlarge)</span></h2>
<div class="grid grid-cols-2 md:grid-cols-3 my-4 gap-5">
    @foreach($images as $image)
        <a href="{{ asset('images/blog/galleries/' . $image) }}" class="venobox shadow-lg border-2 border-gray-400 p-2" data-ratio="4x3" data-gall="myGallery" data-maxwidth="75%"><img src="{{ asset('images/blog/galleries/' . 'thumbnail_' . $image) }}" alt="Gallery Image"></a>
    @endforeach
</div>

        <script> 
            new VenoBox({
                selector: '.venobox',
                border: '5px',
                numeration: true,
                infinigall: true,
                navigation: true,
                spinner: 'wave',
                share: true,
                sharestyle: 'bar',
                navTouch: true,
            });
        </script>