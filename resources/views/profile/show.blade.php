@extends('layouts.app')
@section('content')    

<div class="w-3/4 mx-auto">
	<div class="text-center mb-4">
		<p class="font-semibold mb-2 text-lg">You're viewing the user profile for {{ $user->name }}</p>
		<div class="flex space-x-4 justify-center my-10">
			<a href="{{ $user->twitter }}"><i class="fa-brands fa-square-x-twitter fa-xl"></i></a>					
			<a href="{{ $user->facebook }}"><i class="fa-brands fa-square-facebook fa-xl"></i></a>					
			<a href="{{ $user->linkedin }}"><i class="fa-brands fa-linkedin fa-xl"></i></a>	
		</div>				
	</div>
	<div class="flex justify-evenly items-center my-10">
		<div class="">
			<img src="{{ asset("images/avatars/$user->avatar") }}" class="shadow-md border p-1">
		</div>
		<div class="">
			<ul class="text-sm space-y-1 flex flex-col justify-center">

				<li class=""><i class="fa-solid fa-house mr-4"></i><a href="{{ $user->website }}" class="hover:text-red-500">{{ $user->website }}</a></li>
				
				<li class=""><i class="fa-solid fa-globe mr-4"></i>{{ $user->location }}</li>

				<li class="">	
					<i class="fa-solid fa-envelope mr-4"></i>
					@if($user->email_visible === 0)
						Not shared
					@else				
					{{ $user->email }}
					@endif
				</li>

			</ul>
		</div>
	</div>
	<div class="text-center">
	@if (empty($user->bio))
				<p class="border p-2 text-sm">User has not provided any information about themselves.</p>
			@else
				<p class="border rounded-md p-2 text-gray-700 text-sm">{{ $user->bio }}</p>
			@endif
	</div>

		@if (Auth::user()->name === $user->name || Auth::user()->has_user_role('Admin'))
			<div class="my-4 text-center">
				<a class="border py-2 px-2 text-sm bg-green-700 text-white font-bold hover:border-green-500 hover:bg-green-50" href="/profile/{{ $user->name }}/edit" role="button">Edit Profile</a>
			</div>
		@endif
</div>

@endsection

