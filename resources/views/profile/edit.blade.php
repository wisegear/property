@extends('layouts.app')
@section('content')    

<div class="">

	<div class="">
		@if (session('status'))
			<div class="text-center text-green-800 text-2xl font-bold my-5 border py-1 px-2 bg-gray-100">
				{{ session('status') }}
			</div>
		@endif
	</div>

	<form action="/profile/{{ $user->id }}" method="post" enctype="multipart/form-data">
	@csrf
	@method('PUT')

	@can('Admin')
	<div class="border p-2 bg-gray-500 text-white text-sm">
		<!-- Allows an Admin to alter user groups, set trusted, add notes, remove user lock -->
		<div class="text-center">
				<p class="font-bold">Admin Controls</p>
			</div>

			<div class="my-4 flex justify-center space-x-4">
				@foreach ($roles as $role)
					<div class="">
						<input class="" type="checkbox" name="roles[]" value="{{ $role->id }}" 
							
							@foreach ($user->user_roles as $user_role)
								@if ($user_role->name === $role->name)
									checked
								@endif
							@endforeach
							>
						<label class="" for="roles">{{ $role->name }}</label>
					</div>
				@endforeach
			</div>

			<div class="flex justify-center">       
					<ul class="flex space-x-4">           
						<li class="">
							<label>Trusted Member?</label>     
							<input type="checkbox" class="" id="trusted" name="trusted" @if ($user->trusted === 1) checked=checked @endif>
						</li>
					</ul>
			</div>
						
			<div class="flex flex-col">
				<label class="font-bold">Notes</label>
				<textarea class="text-sm text-gray-700" name="notes" id="notes">{{ $user->notes }}</textarea>
			</div> 
		  
	    @endcan
	</div>

	<div class="my-10 flex justify-evenly items-center border rounded-md p-6">
		    <!-- Manage the user avatar -->
			
			<div class="text-center w-4/12">
				<p class="py-2">Add an Avatar by uploading new file.</p>
				<input type="file" name="image" accept="image/*" onchange="loadFile(event)" class="w-3/4">
					<script>
						var loadFile = function(event) {
							var new_avatar = document.getElementById('new_avatar');
							new_avatar.src = URL.createObjectURL(event.target.files[0]);
						};
					</script>
				<p>{{ $errors->has('image') ? ' Image must be JPG, JPEG, PNG or GIF.  Cannot be larger than 500kb' : '' }}</p>
			</div>
			
			<div class="w-4/12">
				<p class="text-center mb-2">Existing Avatar</p>
				<img src="{{ asset("images/avatars/$user->avatar") }}" class="mx-auto shadow-md" style="width: 100px; height: 100px;">
			</div>
  
			<div class="w-4/12">
				<p class="text-center mb-2">New Avatar here (if added)</p>
				<img class="" id="new_avatar" name="new_avatar">
			</div>		
	</div>

	<div class="my-10 w-3/4 mx-auto">
		<div class="flex flex-col space-y-6">
			  <div class="">
			    <label for="name" class="font-semibold">Username (Locked, open a ticket to change username)</label>
			    <input type="text" class="border w-full bg-gray-100" id="name" name="name" value="{{ $user->name }}" disabled>
			    <p class="text-red-500">{{ $errors->has('name') ? ' Cannot be blank, Max 255 characters, must be unique.' : '' }}</p>
			  </div>

			  <div class="">
			    <label for="email">Email</label>
			    <input type="text" class="border w-full" id="email" name="email" value="{{ $user->email }}">
			    <p class="text-red-500">{{ $errors->has('email') ? ' Cannot be blank, Max 255 characters, must be unique.' : '' }}</p>
			  </div>

			  <div class="text-center pt-4">
				<input type="checkbox" class="" id="email_visible" name="email_visible" value="{{ $user->email_visible }}" @if ($user->email_visible == true) checked @endif>
				<label class="" for="email_visible">Checking this box will display your email publically.</label>
			  </div>

			  <div class="">
			    <label for="website">Website</label>
			    <input type="text" class="border w-full" id="website" name="website" value="{{ $user->website }}">
			  </div>

			  <div class="">
			    <label for="location">Location</label>
			    <input type="text" class="border w-full" id="location" name="location" value="{{ $user->location }}">
			  </div>

			  <div class="">
			    <label for="bio">Tell everyone a bit about you</label>
			    <textarea class="border rounded-md w-full" id="bio" name="bio" rows="6">{{ $user->bio }}</textarea>
			    <p class="text-red-500">{{ $errors->has('bio') ? ' Max 500 characters' : '' }}</p>
			  </div>

			  <div class="">
			    <label for="linkedin">Linkedin</label>
			    <input type="text" class="border w-full" id="linkedin" name="linkedin" value="{{ $user->linkedin }}">
			  </div>

			  <div class="">
			    <label for="twitter">Twitter</label>
			    <input type="text" class="border w-full" id="x" name="x" value="{{ $user->x }}">
			  </div>

			  <div class="">
			    <label for="facebook">Facebook</label>
			    <input type="text" class="border w-full" id="facebook" name="facebook" value="{{ $user->facebook }}">
			  </div>

			  <button type="submit" class="border py-1 px-2 bg-green-600 text-white font-bold w-1/3 mx-auto">Update Profile</button>
		</form>
		</div>
	</div>
</div>

</div>

@endsection