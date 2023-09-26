<!-- Main Navigation -->
<div class="">
    <div class="flex flex-col items-center py-4">
        <h2 class="text-2xl font-bold text-blue-900">PropertyBlog.scot</h2>
        <p class="hidden md:block text-sm">Scottish Mortgage & Property Blog</p>
    </div>

    <!-- Navigation from Medium Screen Size and Above -->

    <div class="hidden md:flex justify-between items-center border-t border-b border-gray-300 py-4">
        <div class="flex space-x-4 w-2/12">
            <a href="https://twitter.com/wisenerl"><i class="fa-brands fa-x-twitter"></i></a>
            <a href="https://linkedin.com/in/leewisener/" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in text-[#0a66c2]"></i></a>
            <a href="https://facebook.com/lee.wisener" aria-label="FaceBook"><i class="fa-brands fa-facebook-f text-[#1877f2]"></i></a>
        </div>
        <ul class="flex space-x-10">
            <li><a href="/" class="hover:text-blue-800 hover:font-bold">Home</a></li>
            <li><a href="/blog" class="hover:text-blue-800 hover:font-bold">Blog</a></li>
            <li><a href="/about" class="hover:text-blue-800 hover:font-bold">About</a></li>
            <li><a href="/contact" class="hover:text-blue-800 hover:font-bold">Contact</a></li>
        </ul>
        <div class="w-2/12 grow-0 flex justify-end space-x-4">
            @if(Auth::check())
                <ul class="relative">
                    <li id="user-menu-top" class="text-blue-800 cursor-pointer">{{ Auth::user()->name }} <i class="fa-solid fa-circle-down"></i></li>
                    <ul id="user-menu" class="hidden absolute shadow-lg mt-4 bg-gray-200 border border-gray-300 w-full p-2">
                        <li><a href="/profile/{{ Auth::user()->name_slug }}">Profile</a></li>
                        <li><a href="/support">Support</a></li>
                            @can('Admin')
                                <li class="text-orange-600"><a href="/admin">Dashboard</a></li>
                            @endcan
                        <li class="text-red-800 border-t mt-4 border-gray-700"><a href="/logout">Logout</a></li>
                    </ul>
                </ul>
                
            @else
                <a href="/login"><button class="border text-xs text-white uppercase font-bold bg-green-800 p-2">Login</button></a>
            @endif
        </div>
    </div>

    <!-- Visible Mobile Navigation -->

    <div class="flex md:hidden justify-between border-t border-b py-2 border-gray-300">
        <div class="flex space-x-4 w-2/12">
            <a href="https://twitter.com/wisenerl"><i class="fa-brands fa-x-twitter"></i></a>
            <a href="https://linkedin.com/in/leewisener/" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in text-[#0a66c2]"></i></a>
            <a href="https://facebook.com/lee.wisener" aria-label="FaceBook"><i class="fa-brands fa-facebook-f text-[#1877f2]"></i></a>
        </div>
        <div>
            <a href="#" class="hamburger"><i class="fa-solid fa-bars fa-xl"></i></a>
        </div>
    </div>

    <!-- Reveal when hamburger is clicked -->

    <div class="mobile-menu hidden">
        <div class="absolute bg-gray-800 w-full top-0 left-0 min-h-screen">
            <div class="m-5">
                <div class="text-end"><a href="#" class="mobile-nav-close"><i class="fa-regular fa-circle-xmark fa-2xl text-white"></i></a></div>
            </div>
        <!-- Actual Menu -->
        <div class="">
            <h2 class="mx-4 mb-4 text-xl font-bold text-white border-b border-gray-200">Mobile Navigation</h2>
            <ul class="flex-col space-y-4 mx-4 text-white">
                <li><a href="/" class="hover:text-blue-800 hover:font-bold">Home</a></li>
                <li><a href="/blog" class="hover:text-blue-800 hover:font-bold">Blog</a></li>
                <li><a href="/about" class="hover:text-blue-800 hover:font-bold">About</a></li>
                <li><a href="/contact" class="hover:text-blue-800 hover:font-bold">Contact</a></li>
            </ul>            
        </div>
        </div>
    </div>


</div>


