<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Blog') }}</title>

        <!-- TinyMCE -->
        <script src="https://cdn.tiny.cloud/1/a1rn9rzvnlulpzdgoe14w7kqi1qpfsx7cx9am2kbgg226dqz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- FontAwesome -->
        <script src="https://kit.fontawesome.com/0ff5084395.js" crossorigin="anonymous"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">

        <!-- Main outer container for the entire site -->
        <div class="max-w-screen-xl px-5 mx-auto">

            <!-- Main Navigation for the site -->
            @include('layouts.admin-nav')

            <!-- This is where the content for each page is rendered -->
            <div class="my-10">
                @yield('content')
            </div>

            <!-- Footer for entire site -->
            @include('layouts.footer')

        </div>

    </body>

</html>