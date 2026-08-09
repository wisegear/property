<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'PropertyResearch'))</title>
    @php
        $metaTitle = trim($__env->yieldContent('title', config('app.name', 'PropertyResearch')));
        $metaDescription = trim($__env->yieldContent(
            'description',
            'Independent UK property data, sales trends, market signals, and research insights across England, Wales, Scotland, and Northern Ireland.'
        ));
        $metaUrl = url()->current();
        $metaImage = asset('assets/images/site/research-logo-4.png');
        $schemaWebsite = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name', 'PropertyResearch'),
            'url' => url('/'),
        ];
        $schemaOrg = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name', 'PropertyResearch'),
            'url' => url('/'),
            'logo' => $metaImage,
        ];
    @endphp

    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $metaUrl }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/favicon/favicon.png') }}">

    <!-- Social Media Meta Tags (Twitter & Open Graph) - Only shown when $page variable exists -->
    @isset($page)
        @php
            $featuredMetaImage = $page->featuredImageUrl('medium') ?? $metaImage;
        @endphp
        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:site" content="@ukprores" />
        <meta name="twitter:title" content="{{ $page->title }}" />
        <meta name="twitter:description" content="{{ $page->summary }}" />
        <meta name="twitter:image" content="{{ $featuredMetaImage }}" />

        <!-- Open Graph Meta Tags (Facebook, LinkedIn, etc.) -->
        <meta property="og:type" content="article" />
        <meta property="og:site_name" content="PropertyResearch.uk" />
        <meta property="og:title" content="{{ $page->title }}" />
        <meta property="og:description" content="{{ $page->summary }}" />
        <meta property="og:url" content="{{ $metaUrl }}" />
        <meta property="og:image" content="{{ $featuredMetaImage }}" />
        <meta property="og:image:width" content="800" />
        <meta property="og:image:height" content="300" />
        <meta property="og:locale" content="en_GB" />
    @else
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:site" content="@ukprores" />
        <meta name="twitter:title" content="{{ $metaTitle }}" />
        <meta name="twitter:description" content="{{ $metaDescription }}" />
        <meta name="twitter:image" content="{{ $metaImage }}" />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="PropertyResearch.uk" />
        <meta property="og:title" content="{{ $metaTitle }}" />
        <meta property="og:description" content="{{ $metaDescription }}" />
        <meta property="og:url" content="{{ $metaUrl }}" />
        <meta property="og:image" content="{{ $metaImage }}" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:locale" content="en_GB" />
    @endisset

    @hasSection('meta')
        @yield('meta')
    @endif

    <script type="application/ld+json">{!! json_encode($schemaWebsite, JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="application/ld+json">{!! json_encode($schemaOrg, JSON_UNESCAPED_SLASHES) !!}</script>

    <!-- Vite Assets - Moved to top to prevent FOUC (Flash of Unstyled Content) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Bunny Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap">
    @stack('head')
</head>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-EKVHWD8V6J"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-EKVHWD8V6J');
</script>

<body class="bg-zinc-50">
    <div class="min-h-screen flex flex-col">
        
        <!-- ============================================ -->
        <!-- DESKTOP HEADER (Logo & Social Links & Auth) -->
        <!-- Hidden on mobile (xl:block = show on extra large screens only) -->
        <!-- ============================================ -->
        <div class="hidden">
            <div class="mx-auto flex max-w-7xl items-center py-4">
                
                <!-- Left Section: Social Media Icons -->
                <div class="flex-1 flex items-center">
                    <div class="inline-flex items-center gap-4 text-sm">
                        <!-- LinkedIn -->
                        <a href="https://www.linkedin.com/in/leewisener/" target="_blank" rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded
                            border border-zinc-300 bg-white/80 text-[#0A66C2] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="LinkedIn profile">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.473 0 16 .513 16 1.146v13.708c0 .633-.527 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146Zm4.943 12.248V6.169H2.542v7.225h2.401ZM3.743 5.182c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.225 2.4 3.934c0 .694.506 1.248 1.327 1.248Zm1.945 8.212h2.401V9.359c0-.216.016-.432.08-.586.175-.431.576-.878 1.25-.878.883 0 1.237.662 1.237 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225Z"/>
                            </svg>
                        </a>
                        
                        <!-- Facebook -->
                        <a href="https://www.facebook.com/lee.wisener" target="_blank" rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded border border-zinc-300 bg-white/80 text-[#1877F2] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="Facebook profile">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M16 8.049C16 3.604 12.418 0 8 0S0 3.604 0 8.049C0 12.07 2.925 15.401 6.75 16v-5.625H4.719V8.049H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98H10.554c-.993 0-1.304.621-1.304 1.258v1.51h2.219l-.354 2.326H9.25V16C13.075 15.401 16 12.07 16 8.049Z"/>
                            </svg>
                        </a>
                        
                        <!-- WhatsApp -->
                        <a href="https://wa.me/447720868799?text=Hi%20Lee%2C%20I%27m%20contacting%20you%20about%20propertyresearch.uk" target="_blank" rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded border border-zinc-300 bg-white/80 text-[#25D366] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="WhatsApp chat">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M13.601 2.326A7.854 7.854 0 0 0 8.003 0C3.58 0 .001 3.577 0 8a7.94 7.94 0 0 0 1.143 4.08L0 16l4.02-1.055A7.964 7.964 0 0 0 8.003 16c4.423 0 8-3.577 8-8 0-2.136-.832-4.146-2.399-5.674ZM8.003 14.5a6.5 6.5 0 0 1-3.317-.908l-.237-.14-2.387.626.637-2.327-.154-.24A6.5 6.5 0 1 1 8.003 14.5Zm3.566-4.844c-.194-.097-1.148-.567-1.326-.631-.177-.065-.307-.097-.437.097-.129.194-.501.63-.614.76-.113.129-.226.145-.42.048-.194-.097-.819-.302-1.56-.962-.576-.513-.964-1.146-1.077-1.34-.113-.194-.012-.299.085-.395.087-.086.194-.226.291-.339.097-.113.129-.194.194-.323.065-.129.032-.242-.016-.339-.048-.097-.437-1.052-.598-1.44-.157-.377-.317-.326-.437-.332l-.372-.006a.713.713 0 0 0-.517.242c-.178.194-.679.663-.679 1.617 0 .954.695 1.876.792 2.005.097.129 1.37 2.092 3.32 2.932.464.2.825.319 1.107.408.465.148.888.127 1.222.077.373-.056 1.148-.469 1.31-.923.162-.453.162-.841.113-.923-.048-.081-.177-.129-.371-.226Z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Center Section: Site Wordmark -->
                <div class="flex-1 flex items-center justify-center">
                    <a href="{{ url('/') }}" class="inline-flex items-baseline tracking-tight text-slate-800">
                        <span class="text-[1.35rem] font-bold">PropertyResearch</span><span class="text-[1.1rem] font-bold text-lime-700">.uk</span>
                    </a>
                </div>

                <!-- Right Section: User Authentication (Login/Register or User Menu) -->
                <div class="flex-1 flex items-center justify-end text-sm">
                    @if(Auth::check())
                        <!-- User Dropdown Menu (when logged in) -->
                        <div class="relative">
                            <button id="legacyUserMenuButton"
                                    class="flex items-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2 cursor-pointer">
                                {{ Auth::user()->name }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown Menu Items -->
                            <div id="legacyUserDropdown"
                                 class="absolute right-0 mt-4 w-30 bg-white border border-slate-200 translate-x-4 rounded-xl shadow-lg z-50 hidden">
                                <div>
                                    <a href="/profile/{{ Auth::user()->name_slug }}" 
                                       class="block px-4 py-2 hover:bg-zinc-100">Profile</a>
                                    <a href="/support" 
                                       class="block px-4 py-2 hover:bg-zinc-100">Support</a>
                                    @can('Admin')
                                        <a href="/admin" 
                                           class="block px-4 py-2 hover:bg-zinc-100 text-orange-800 font-bold">Admin</a>
                                    @endcan
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" 
                                                class="w-full text-left px-4 py-2 hover:bg-zinc-100 hover:text-teal-500 cursor-pointer">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Login/Register Buttons (when not logged in) -->
                        <div class="flex items-center gap-2 text-xs">
                            <a href="/login" 
                               class="px-4 py-2 rounded bg-zinc-700 text-white hover:bg-zinc-500 transition">Login</a>
                            <a href="/register" 
                               class="px-4 py-2 rounded bg-zinc-200 text-zinc-700 hover:bg-zinc-300 transition hover:text-black">Register</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- DESKTOP NAVIGATION BAR -->
        <!-- Hidden on mobile, shown on extra large screens -->
        <!-- ============================================ -->
        <nav class="hidden border-b border-zinc-200 bg-white px-4 xl:block">
            <div class="relative mx-auto flex max-w-7xl items-center gap-6">
                <a href="{{ url('/') }}" class="inline-flex shrink-0 items-baseline tracking-tight text-slate-800">
                    <span class="text-xl font-bold">PropertyResearch</span><span class="text-base font-bold text-lime-700">.uk</span>
                </a>
                
                <!-- Mobile Toggle Button (hidden on desktop) -->
                <button id="navToggle" 
                        aria-controls="primaryNav" 
                        aria-expanded="false" 
                        class="md:hidden ml-auto inline-flex items-center justify-center p-2 rounded text-zinc-700 hover:text-lime-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2" 
                        type="button">
                    <span class="sr-only">Open main menu</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- Primary Navigation Links -->
                <div id="primaryNav"
                     class="hidden min-w-0 flex-1 flex-col justify-center text-sm md:flex md:flex-row md:items-center md:gap-1">
                    
                    <!-- Home Link -->
                    <a href="{{ url('/') }}"
                       class="hidden px-3 py-4 {{ request()->is('/') ? 'text-zinc-900' : 'text-zinc-700 hover:text-lime-700' }}">
                        Home
                    </a>
                    
                    <!-- Property Dropdown Menu -->
                    <div class="relative">
                        <button id="propertyMenuButton" 
                                aria-haspopup="true" 
                                aria-controls="propertyDropdown" 
                                aria-expanded="false" 
                                class="flex cursor-pointer items-center gap-1 px-3 py-4 text-zinc-700 hover:text-lime-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            Property
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Property Dropdown Content (2 columns) -->
                        <div id="propertyDropdown" 
                             role="menu" 
                             aria-labelledby="propertyMenuButton" 
                             class="absolute left-0 mt-4 w-[32rem] bg-white border border-zinc-200 rounded shadow-lg z-50 transform transition duration-150 ease-out origin-top opacity-0 scale-95 pointer-events-none hidden">
                            <div class="flex">
                                <!-- Left Column -->
                                <div class="py-2 flex-1">
                                    <a href="{{ url('/property') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 font-bold">
                                        Dashboard
                                    </a>
                                    <a href="{{ url('/property/search') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Property Search
                                    </a>
                                    <a href="{{ url('/property/outer-prime-london') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Outer Prime London
                                    </a>
                                    <a href="{{ url('/property/prime-central-london') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Prime Central London
                                    </a>
                                    <a href="{{ url('/property/ultra-prime-central-london') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Ultra Prime Central London
                                    </a>
                                    <a href="{{ route('property.scottish-prices', absolute: false) }}"
                                       role="menuitem"
                                       tabindex="-1"
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Scottish House Prices
                                    </a>
                                </div>

                                <!-- Vertical Divider -->
                                <div class="w-px bg-zinc-200 my-2"></div>

                                <!-- Right Column -->
                                <div class="py-2 flex-1">
                                    <a href="{{ url('/hpi') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        House Price Index
                                    </a>
                                    <a href="{{ url('/new-old') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        New Build Comparison
                                    </a>
                                    <a href="{{ url('/epc') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        EPC Dashboard
                                    </a>
                                    <a href="{{ url('/rental') }}"
                                       role="menuitem"
                                       tabindex="-1"
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Rental
                                    </a>
                                    <a href="{{ url('/repossessions') }}"
                                       role="menuitem"
                                       tabindex="-1"
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Repossessions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stress Indicators Dropdown Menu -->
                    <div class="relative">
                        <button id="economicsMenuButton" 
                                aria-haspopup="true" 
                                aria-controls="economicsDropdown" 
                                aria-expanded="false" 
                                class="flex cursor-pointer items-center gap-1 px-3 py-4 text-zinc-700 hover:text-lime-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            Market &amp; Economy
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Economics Dropdown Content (2 columns) -->
                        <div id="economicsDropdown" 
                             role="menu" 
                             aria-labelledby="economicsMenuButton" 
                             class="absolute left-0 mt-4 w-[32rem] bg-white border border-zinc-200 rounded shadow-lg z-50 transform transition duration-150 ease-out origin-top opacity-0 scale-95 pointer-events-none hidden">
                            <div class="flex">
                                <!-- Left Column -->
                                <div class="py-2 flex-1">
                                    <a href="{{ url('/economic-dashboard') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 font-semibold hover:bg-zinc-200 text-zinc-900">
                                        Stress Indicators Dashboard
                                    </a>
                                    <a href="{{ url('/interest-rates') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Interest Rates
                                    </a>
                                    <a href="{{ url('/inflation') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Inflation (CPIH)
                                    </a>
                                    <a href="{{ url('/wage-growth') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Wage Growth
                                    </a>
                                </div>

                                <!-- Vertical Divider -->
                                <div class="w-px bg-zinc-200 my-2"></div>

                                <!-- Right Column -->
                                <div class="py-2 flex-1">
                                    <a href="{{ url('/hpi-overview') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        House Price Index (HPI)
                                    </a>
                                    <a href="{{ url('/unemployment') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Unemployment
                                    </a>
                                    <a href="{{ url('/approvals') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Mortgage Approvals
                                    </a>
                                    <a href="{{ url('/arrears') }}" 
                                       role="menuitem" 
                                       tabindex="-1" 
                                       class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                        Mortgage Arrears (MLAR)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Dropdown Menu -->
                    <div class="relative">
                        <button id="insightsMenuButton"
                                aria-haspopup="true"
                                aria-controls="insightsDropdown"
                                aria-expanded="false"
                                class="flex cursor-pointer items-center gap-1 px-3 py-4 {{ request()->routeIs('insights.dashboard') || request()->routeIs('insights.index') || request()->routeIs('insights.search') || request()->routeIs('insights.show') || request()->routeIs('insights.crime.index') || request()->routeIs('insights.crime.show') || request()->routeIs('insights.swap-rates') ? 'font-semibold text-zinc-900' : 'text-zinc-700 hover:text-lime-700' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            Insights
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="insightsDropdown"
                             role="menu"
                             aria-labelledby="insightsMenuButton"
                             class="absolute left-0 mt-4 w-56 bg-white border border-zinc-200 rounded shadow-lg z-50 transform transition duration-150 ease-out origin-top opacity-0 scale-95 pointer-events-none hidden">
                            <a href="{{ route('insights.dashboard') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('insights.dashboard') ? 'font-semibold' : '' }}">
                                Market Insights
                            </a>
                            <a href="{{ route('insights.index') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('insights.index') || request()->routeIs('insights.search') || request()->routeIs('insights.show') ? 'font-semibold' : '' }}">
                                County Insights
                            </a>
                            <a href="{{ route('insights.crime.index') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('insights.crime.index') || request()->routeIs('insights.crime.show') ? 'font-semibold' : '' }}">
                                Crime Insights
                            </a>
                            <a href="{{ route('insights.swap-rates') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('insights.swap-rates') ? 'font-semibold' : '' }}">
                                Swap Rates
                            </a>
                            <a href="{{ route('top-sales.index') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('top-sales.index') ? 'font-semibold' : '' }}">
                                Top Property Sales
                            </a>
                            <a href="{{ url('/blog') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 text-zinc-700 hover:bg-zinc-100">
                                Blog
                            </a>
                        </div>
                    </div>

                    <!-- Deprivation Link -->
                    <a href="{{ url('/deprivation') }}"
                       class="hidden px-3 py-4 {{ request()->is('deprivation') ? 'text-zinc-900' : 'text-zinc-700 hover:text-lime-700' }}">
                        Deprivation
                    </a>

                    <!-- Social Housing Dropdown Menu -->
                    <div class="relative">
                        <button id="socialHousingMenuButton"
                                aria-haspopup="true"
                                aria-controls="socialHousingDropdown"
                                aria-expanded="false"
                                class="flex cursor-pointer items-center gap-1 px-3 py-4 text-zinc-700 hover:text-lime-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            Local Research
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="socialHousingDropdown"
                             role="menu"
                             aria-labelledby="socialHousingMenuButton"
                             class="absolute left-0 mt-4 w-56 bg-white border border-zinc-200 rounded shadow-lg z-50 transform transition duration-150 ease-out origin-top opacity-0 scale-95 pointer-events-none hidden">
                            <a href="{{ route('schools.index') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-zinc-700 hover:bg-zinc-100">Schools (England)</a>
                            <a href="{{ route('deprivation.index') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-zinc-700 hover:bg-zinc-100">Deprivation</a>
                            <div class="my-1 border-t border-zinc-100"></div>
                            <a href="{{ route('localauthority.england') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('localauthority.england') ? 'font-semibold' : '' }}">
                                Council housing: England
                            </a>
                            <a href="{{ route('localauthority.scotland') }}"
                               role="menuitem"
                               tabindex="-1"
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700 {{ request()->routeIs('localauthority.scotland') ? 'font-semibold' : '' }}">
                                Council housing: Scotland
                            </a>
                        </div>
                    </div>

                    <!-- Calculators Dropdown Menu -->
                    <div class="relative">
                        <button id="calculatorsMenuButton" 
                                aria-haspopup="true" 
                                aria-controls="calculatorsDropdown" 
                                aria-expanded="false" 
                                class="flex cursor-pointer items-center gap-1 px-3 py-4 text-zinc-700 hover:text-lime-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            Tools
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Calculators Dropdown Content -->
                        <div id="calculatorsDropdown" 
                             role="menu" 
                             aria-labelledby="calculatorsMenuButton" 
                             class="absolute left-0 mt-4 w-56 bg-white border border-zinc-200 rounded shadow-lg z-50 transform transition duration-150 ease-out origin-top opacity-0 scale-95 pointer-events-none hidden">
                            <a href="{{ url('/mortgage-calculator') }}" 
                               role="menuitem" 
                               tabindex="-1" 
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                Mortgage Calculator
                            </a>
                            <a href="{{ url('/stamp-duty') }}" 
                               role="menuitem" 
                               tabindex="-1" 
                               class="block px-4 py-2 hover:bg-zinc-100 text-zinc-700">
                                Stamp Duty Calculator
                            </a>
                        </div>
                    </div>

                    <!-- Blog Link -->
                    <a href="{{ url('/blog') }}"
                       class="hidden px-3 py-4 {{ request()->is('blog') ? 'text-zinc-900' : 'text-zinc-700 hover:text-lime-700' }}">
                        Blog
                    </a>
                    
                    <!-- About Link -->
                    <a href="{{ url('/about') }}"
                       class="px-3 py-4 {{ request()->is('about') ? 'font-semibold text-zinc-900' : 'text-zinc-700 hover:text-lime-700' }}">
                        About
                    </a>
                </div>

                <div class="flex shrink-0 items-center gap-3 text-xs text-zinc-500">
                    @auth
                        <div class="relative">
                            <button
                                id="userMenuButton"
                                type="button"
                                aria-haspopup="true"
                                aria-controls="userDropdown"
                                aria-expanded="false"
                                class="flex cursor-pointer items-center gap-1 rounded-sm px-2 py-2 text-zinc-600 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2"
                            >
                                {{ Auth::user()->name }}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                id="userDropdown"
                                role="menu"
                                aria-labelledby="userMenuButton"
                                class="pointer-events-none absolute right-0 z-50 mt-3 hidden w-44 origin-top-right scale-95 rounded-sm border border-zinc-200 bg-white py-1 text-sm text-zinc-700 opacity-0 shadow-lg transition duration-150 ease-out"
                            >
                                <a href="/profile/{{ Auth::user()->name_slug }}" role="menuitem" class="block px-4 py-2 hover:bg-zinc-100 hover:text-zinc-900">Profile</a>
                                <a href="/support" role="menuitem" class="block px-4 py-2 hover:bg-zinc-100 hover:text-zinc-900">Support</a>
                                @can('Admin')
                                    <a href="/admin" role="menuitem" class="block px-4 py-2 font-semibold text-orange-800 hover:bg-zinc-100">Admin</a>
                                @endcan
                                <div class="my-1 border-t border-zinc-100"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" role="menuitem" class="w-full cursor-pointer px-4 py-2 text-left hover:bg-zinc-100 hover:text-zinc-900">Logout</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="/login" class="hover:text-zinc-900">Login</a>
                        <a href="/register" class="hover:text-zinc-900">Register</a>
                    @endauth
                </div>

            </div>
        </nav>

        <!-- ============================================ -->
        <!-- MOBILE NAVIGATION -->
        <!-- Hidden on desktop (xl:hidden = hide on extra large screens) -->
        <!-- ============================================ -->
        <nav class="border-b border-zinc-200 bg-white px-4 py-3 xl:hidden">
            <div class="w-full flex items-center justify-between">
                <!-- Mobile Wordmark -->
                <a href="{{ url('/') }}" class="inline-flex items-baseline tracking-tight text-slate-800">
                    <span class="text-xl font-bold">PropertyResearch</span><span class="text-base font-bold text-lime-700">.uk</span>
                </a>
                
                <!-- Mobile Menu Toggle Button -->
                <button id="mobileNavToggle" 
                        aria-controls="mobileNav" 
                        aria-expanded="false"
                        class="inline-flex items-center justify-center p-2 rounded text-zinc-700 hover:text-lime-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2"
                        type="button">
                    <span class="sr-only">Open main menu</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <!-- Collapsible Mobile Menu (hidden by default) -->
            <div id="mobileNav" class="hidden flex-col mt-3 space-y-1 w-full text-sm">
                
                <!-- Home Link -->
                <a href="{{ url('/') }}" 
                   class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                    Home
                </a>
                
                <!-- Property Dropdown (Mobile) -->
                <div>
                    <button id="mobilePropertyBtn"
                            class="w-full flex justify-between items-center px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                        Property
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <!-- Property Submenu Items -->
                    <div id="mobilePropertyMenu" class="hidden flex-col pl-2 space-y-1 mt-1">
                        <a href="{{ url('/property') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 font-bold">
                            Dashboard
                        </a>
                        <a href="{{ url('/property/search') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Property Search
                        </a>
                        <a href="{{ url('/property/outer-prime-london') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Outer Prime London
                        </a>
                        <a href="{{ url('/property/prime-central-london') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Prime Central London
                        </a>
                        <a href="{{ url('/property/ultra-prime-central-london') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Ultra Prime Central London
                        </a>
                        <a href="{{ route('property.scottish-prices', absolute: false) }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Scottish House Prices
                        </a>
                        <a href="{{ url('/hpi') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            House Price Index
                        </a>
                        <a href="{{ url('/new-old') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            New Build Comparison
                        </a>
                        <a href="{{ url('/epc') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            EPC Dashboard
                        </a>
                        <a href="{{ url('/rental') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Rental
                        </a>
                        <a href="{{ url('/repossessions') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Repossessions
                        </a>
                    </div>
                </div>

                <!-- Stress Indicators Dropdown (Mobile) -->
                <div>
                    <button id="mobileIndicatorsBtn" 
                            class="w-full flex justify-between items-center px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                        Market &amp; Economy
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <!-- Stress Indicators Submenu Items -->
                    <div id="mobileIndicatorsMenu" class="hidden flex-col pl-2 space-y-1 mt-1">
                        <a href="{{ url('/economic-dashboard') }}" 
                           class="block px-3 py-2 rounded font-semibold text-zinc-800 hover:bg-zinc-100">
                            Stress Indicators Dashboard
                        </a>
                        <div class="border-t border-zinc-100 my-1"></div>
                        <a href="{{ url('/interest-rates') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Interest Rates
                        </a>
                        <a href="{{ url('/inflation') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Inflation (CPIH)
                        </a>
                        <a href="{{ url('/wage-growth') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Wage Growth
                        </a>
                        <a href="{{ url('/hpi-overview') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            House Price Index (HPI)
                        </a>
                        <a href="{{ url('/unemployment') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Unemployment
                        </a>
                        <a href="{{ url('/approvals') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Mortgage Approvals
                        </a>
                        <a href="{{ url('/arrears') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Mortgage Arrears (MLAR)
                        </a>
                    </div>
                </div>

                <!-- Insights Dropdown (Mobile) -->
                <div>
                    <button id="mobileInsightsBtn"
                            class="w-full flex justify-between items-center px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                        Insights
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="mobileInsightsMenu" class="hidden flex-col pl-2 space-y-1 mt-1">
                        <a href="{{ route('insights.dashboard') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 {{ request()->routeIs('insights.dashboard') ? 'font-semibold' : '' }}">
                            Market Insights
                        </a>
                        <a href="{{ route('insights.index') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 {{ request()->routeIs('insights.index') || request()->routeIs('insights.search') || request()->routeIs('insights.show') ? 'font-semibold' : '' }}">
                            County Insights
                        </a>
                        <a href="{{ route('insights.crime.index') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 {{ request()->routeIs('insights.crime.index') || request()->routeIs('insights.crime.show') ? 'font-semibold' : '' }}">
                            Crime Insights
                        </a>
                        <a href="{{ route('insights.swap-rates') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 {{ request()->routeIs('insights.swap-rates') ? 'font-semibold' : '' }}">
                            Swap Rates
                        </a>
                        <a href="{{ route('top-sales.index') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 {{ request()->routeIs('top-sales.index') ? 'font-semibold' : '' }}">
                            Top Property Sales
                        </a>
                        <a href="{{ url('/blog') }}" class="block rounded px-3 py-2 text-zinc-700 hover:bg-zinc-100">Blog</a>
                    </div>
                </div>

                <!-- Deprivation Link -->
                <a href="{{ url('/deprivation') }}"
                   class="hidden rounded px-3 py-2 text-zinc-700 hover:bg-zinc-100">
                    Deprivation
                </a>

                <!-- Social Housing Dropdown (Mobile) -->
                <div>
                    <button id="mobileSocialHousingBtn"
                            class="w-full flex justify-between items-center px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                        Local Research
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="mobileSocialHousingMenu" class="hidden flex-col pl-2 space-y-1 mt-1">
                        <a href="{{ route('schools.index') }}" class="block rounded px-3 py-2 text-zinc-700 hover:bg-zinc-100">Schools (England)</a>
                        <a href="{{ route('deprivation.index') }}" class="block rounded px-3 py-2 text-zinc-700 hover:bg-zinc-100">Deprivation</a>
                        <a href="{{ route('localauthority.england') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Council housing: England
                        </a>
                        <a href="{{ route('localauthority.scotland') }}"
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Council housing: Scotland
                        </a>
                    </div>
                </div>

                <!-- Calculators Dropdown (Mobile) -->
                <div>
                    <button id="mobileCalculatorsBtn" 
                            class="w-full flex justify-between items-center px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                        Tools
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <!-- Calculators Submenu Items -->
                    <div id="mobileCalculatorsMenu" class="hidden flex-col pl-2 space-y-1 mt-1">
                        <a href="{{ url('/mortgage-calculator') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Mortgage Calculator
                        </a>
                        <a href="{{ url('/stamp-duty') }}" 
                           class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Stamp Duty Calculator
                        </a>
                    </div>
                </div>

                <!-- Blog Link -->
                <a href="{{ url('/blog') }}"
                   class="hidden rounded px-3 py-2 text-zinc-700 hover:bg-zinc-100">
                    Blog
                </a>
                
                <!-- About Link -->
                <a href="{{ url('/about') }}" 
                   class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                    About
                </a>

                <!-- Social Links Section (Mobile Only) -->
                <div class="hidden items-center gap-2 border-t border-zinc-100 px-3 py-4">
                    <span class="text-xs uppercase tracking-wide text-zinc-500">Connect</span>
                    <div class="inline-flex items-center gap-2">
                        <!-- LinkedIn -->
                        <a href="https://www.linkedin.com/in/leewisener/" 
                           target="_blank" 
                           rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-zinc-300 bg-white text-[#0A66C2] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="LinkedIn profile">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.473 0 16 .513 16 1.146v13.708c0 .633-.527 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146Zm4.943 12.248V6.169H2.542v7.225h2.401ZM3.743 5.182c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.225 2.4 3.934c0 .694.506 1.248 1.327 1.248Zm1.945 8.212h2.401V9.359c0-.216.016-.432.08-.586.175-.431.576-.878 1.25-.878.883 0 1.237.662 1.237 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225Z"/>
                            </svg>
                        </a>
                        
                        <!-- Facebook -->
                        <a href="https://www.facebook.com/lee.wisener" 
                           target="_blank" 
                           rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-zinc-300 bg-white text-[#1877F2] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="Facebook profile">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M16 8.049C16 3.604 12.418 0 8 0S0 3.604 0 8.049C0 12.07 2.925 15.401 6.75 16v-5.625H4.719V8.049H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98H10.554c-.993 0-1.304.621-1.304 1.258v1.51h2.219l-.354 2.326H9.25V16C13.075 15.401 16 12.07 16 8.049Z"/>
                            </svg>
                        </a>
                        
                        <!-- WhatsApp -->
                        <a href="https://wa.me/447720868799?text=Hi%20Lee%2C%20I%27m%20contacting%20you%20about%20propertyresearch.uk"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-zinc-300 bg-white text-[#25D366] hover:bg-zinc-100 transition shadow-sm"
                           aria-label="WhatsApp chat">
                            <svg class="inline-block h-[1em] w-[1em] text-sm" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M13.601 2.326A7.854 7.854 0 0 0 8.003 0C3.58 0 .001 3.577 0 8a7.94 7.94 0 0 0 1.143 4.08L0 16l4.02-1.055A7.964 7.964 0 0 0 8.003 16c4.423 0 8-3.577 8-8 0-2.136-.832-4.146-2.399-5.674ZM8.003 14.5a6.5 6.5 0 0 1-3.317-.908l-.237-.14-2.387.626.637-2.327-.154-.24A6.5 6.5 0 1 1 8.003 14.5Zm3.566-4.844c-.194-.097-1.148-.567-1.326-.631-.177-.065-.307-.097-.437.097-.129.194-.501.63-.614.76-.113.129-.226.145-.42.048-.194-.097-.819-.302-1.56-.962-.576-.513-.964-1.146-1.077-1.34-.113-.194-.012-.299.085-.395.087-.086.194-.226.291-.339.097-.113.129-.194.194-.323.065-.129.032-.242-.016-.339-.048-.097-.437-1.052-.598-1.44-.157-.377-.317-.326-.437-.332l-.372-.006a.713.713 0 0 0-.517.242c-.178.194-.679.663-.679 1.617 0 .954.695 1.876.792 2.005.097.129 1.37 2.092 3.32 2.932.464.2.825.319 1.107.408.465.148.888.127 1.222.077.373-.056 1.148-.469 1.31-.923.162-.453.162-.841.113-.923-.048-.081-.177-.129-.371-.226Z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Authentication Links (Mobile) -->
                @auth
                    <a href="/profile/{{ Auth::user()->name_slug }}" 
                       class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                        Profile
                    </a>
                    <a href="/support" 
                       class="block px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                        Support
                    </a>
                    @can('Admin')
                        <a href="/admin" 
                           class="block px-3 py-2 rounded text-orange-800 font-bold hover:bg-zinc-100">
                            Admin
                        </a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" 
                                class="block w-full text-left px-3 py-2 rounded text-zinc-700 hover:bg-zinc-100">
                            Logout
                        </button>
                    </form>
                @else
                    <!-- Login/Register Buttons (when not logged in) -->
                    <div class="flex gap-4 border-t border-zinc-100 px-3 pt-3 text-sm text-zinc-600">
                        <a href="/login" 
                           class="hover:text-zinc-900">
                            Login
                        </a>
                        <a href="/register" 
                           class="hover:text-zinc-900">
                            Register
                        </a>
                    </div>
                @endauth
            </div>
        </nav>

        <!-- ============================================ -->
        <!-- MAIN CONTENT AREA -->
        <!-- This is where page-specific content is injected -->
        <!-- ============================================ -->
        <main class="flex-1 p-6">
            @yield('content')
        </main>

        <!-- ============================================ -->
        <!-- FOOTER -->
        <!-- ============================================ -->
        <footer class="bg-slate-950 px-6 py-8 text-sm text-slate-300">
            <div class="mx-auto grid max-w-7xl gap-8 sm:grid-cols-2 lg:grid-cols-[1.35fr_1fr_1fr_1fr]">
                <div>
                    <a href="{{ url('/') }}" class="inline-flex items-baseline tracking-tight text-white">
                        <span class="text-xl font-bold">PropertyResearch</span><span class="text-base font-bold text-lime-400">.uk</span>
                    </a>
                    <p class="mt-3 max-w-sm leading-6 text-slate-400">Free, independent UK residential property data and research. Explore sales, prices, local records and the wider market.</p>
                    <div class="mt-4 flex items-center gap-4 text-slate-400">
                        <a href="https://www.linkedin.com/in/leewisener/" target="_blank" rel="noopener" class="hover:text-white" aria-label="LinkedIn profile">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.473 0 16 .513 16 1.146v13.708c0 .633-.527 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146Zm4.943 12.248V6.169H2.542v7.225h2.401ZM3.743 5.182c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.225 2.4 3.934c0 .694.506 1.248 1.327 1.248Zm1.945 8.212h2.401V9.359c0-.216.016-.432.08-.586.175-.431.576-.878 1.25-.878.883 0 1.237.662 1.237 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225Z"/></svg>
                        </a>
                        <a href="https://www.facebook.com/lee.wisener" target="_blank" rel="noopener" class="hover:text-white" aria-label="Facebook profile">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8.049C16 3.604 12.418 0 8 0S0 3.604 0 8.049C0 12.07 2.925 15.401 6.75 16v-5.625H4.719V8.049H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98H10.554c-.993 0-1.304.621-1.304 1.258v1.51h2.219l-.354 2.326H9.25V16C13.075 15.401 16 12.07 16 8.049Z"/></svg>
                        </a>
                        <a href="https://wa.me/447720868799?text=Hi%20Lee%2C%20I%27m%20contacting%20you%20about%20propertyresearch.uk" target="_blank" rel="noopener" class="hover:text-white" aria-label="WhatsApp chat">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M13.601 2.326A7.854 7.854 0 0 0 8.003 0C3.58 0 .001 3.577 0 8a7.94 7.94 0 0 0 1.143 4.08L0 16l4.02-1.055A7.964 7.964 0 0 0 8.003 16c4.423 0 8-3.577 8-8 0-2.136-.832-4.146-2.399-5.674ZM8.003 14.5a6.5 6.5 0 0 1-3.317-.908l-.237-.14-2.387.626.637-2.327-.154-.24A6.5 6.5 0 1 1 8.003 14.5Zm3.566-4.844c-.194-.097-1.148-.567-1.326-.631-.177-.065-.307-.097-.437.097-.129.194-.501.63-.614.76-.113.129-.226.145-.42.048-.194-.097-.819-.302-1.56-.962-.576-.513-.964-1.146-1.077-1.34-.113-.194-.012-.299.085-.395.087-.086.194-.226.291-.339.097-.113.129-.194.194-.323.065-.129.032-.242-.016-.339-.048-.097-.437-1.052-.598-1.44-.157-.377-.317-.326-.437-.332l-.372-.006a.713.713 0 0 0-.517.242c-.178.194-.679.663-.679 1.617 0 .954.695 1.876.792 2.005.097.129 1.37 2.092 3.32 2.932.464.2.825.319 1.107.408.465.148.888.127 1.222.077.373-.056 1.148-.469 1.31-.923.162-.453.162-.841.113-.923-.048-.081-.177-.129-.371-.226Z"/></svg>
                        </a>
                    </div>
                </div>

                <div>
                    <h2 class="font-semibold text-white">Property research</h2>
                    <div class="mt-3 grid gap-2">
                        <a href="{{ route('property.search') }}" class="hover:text-white">Property search</a>
                        <a href="{{ route('epc.home') }}" class="hover:text-white">EPC records</a>
                        <a href="{{ route('schools.index') }}" class="hover:text-white">Schools</a>
                        <a href="{{ route('deprivation.index') }}" class="hover:text-white">Deprivation</a>
                    </div>
                </div>

                <div>
                    <h2 class="font-semibold text-white">Market data</h2>
                    <div class="mt-3 grid gap-2">
                        <a href="{{ route('property.home') }}" class="hover:text-white">Transactions</a>
                        <a href="{{ route('hpi.home') }}" class="hover:text-white">House prices</a>
                        <a href="{{ route('rental.index') }}" class="hover:text-white">Rental market</a>
                        <a href="{{ route('economic.dashboard') }}" class="hover:text-white">Economic indicators</a>
                    </div>
                </div>

                <div>
                    <h2 class="font-semibold text-white">About and support</h2>
                    <div class="mt-3 grid gap-2">
                        <a href="{{ url('/about') }}" class="hover:text-white">About</a>
                        <a href="{{ route('legal.data-sources') }}" class="hover:text-white">Data sources</a>
                        <a href="{{ route('legal.index') }}" class="hover:text-white">Legal and support</a>
                        <a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a>
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-8 flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-slate-800 pt-5 text-xs text-slate-500">
                <span>&copy; {{ now()->year }} Lee Wisener · PropertyResearch.uk</span>
                <span>
                    Built using <a href="https://laravel.com" target="_blank" rel="noopener noreferrer" class="text-slate-400 transition hover:text-white">Laravel</a>
                    · Hosted with <a href="https://www.hetzner.com/cloud/" target="_blank" rel="noopener noreferrer" class="text-slate-400 transition hover:text-white">Hetzner Cloud</a>
                </span>
            </div>
        </footer>
    </div>

    <!-- Additional Scripts Section (injected from child views) -->
    @stack('scripts')
</body>
</html>
