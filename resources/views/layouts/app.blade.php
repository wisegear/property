<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Blog') }}</title>

        <!-- TinyMCE -->
        <script src="https://cdn.tiny.cloud/1/a1rn9rzvnlulpzdgoe14w7kqi1qpfsx7cx9am2kbgg226dqz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

        <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
            
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- FontAwesome -->
        <script src="https://kit.fontawesome.com/0ff5084395.js" crossorigin="anonymous"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])


    </head>
    <body class="font-sans antialiased dark:bg-gray-900">
        <div class="flex flex-col min-h-screen container px-5 mx-auto max-w-screen-xl">

            @include('layouts.navigation')

            <!-- Page Content -->
            <div class="my-10">
                @yield('content')
            </div>
            
            @include('layouts.footer')

        </div>
    </body>
</html>
