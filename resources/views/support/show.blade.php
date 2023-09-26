@extends('layouts.app')
@section('content')   

<div class="border rounded-md p-2 w-4/5 mx-auto">
	<h2 class="font-bold text-xl text-center mb-1">{{ $ticket->title }}</h2>
	<div class="text-center text-gray-700 text-xs space-x-4 mb-4">
		<a href="" class="">{{ $ticket->users->name }}</a>
		<a href="" class="">{{ $ticket->created_at->diffForHumans() }}</a>
	</div>
	<p class="text-gray-500 text-sm text-center">{!! $ticket->text !!}</p>
</div>

<div class="my-5 text-center">
	<form method="POST" action="/support/{{ $ticket->id }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	{{ method_field('PUT') }}
		<div class="space-x-4">  
			@if ( $ticket->status === 'Open' || $ticket->status === 'In Progress' || $ticket->status === 'Awaiting Reply' )
			<button type="submit" class="border p-2 bg-red-300 font-bold" name="closeTicket" value="true">Close Ticket</button> 
			@elseif ( $ticket->status == "Closed" || $ticket->status == "In Progress")
			<button type="submit" class="border p-2 bg-green-700 font-bold" name="openTicket" value="true">Open Ticket</button> 
			@endif
			@can('Admin')
			<button type="submit" class="border p-2 bg-indigo-300 font-bold" name="inProgress" value="true">In Progress</button>
			<button type="submit" class="border p-2 bg-yellow-300 font-bold" name="AwaitingReply" value="true">Awaiting Reply</button>
			@endcan
		</div>  
	</form>	
</div>

<div class="w-4/5 mx-auto">
	<p class="text-xl font-bold text-center my-10">Ticket Replies</p>
	@foreach ( $ticket->comments as $comment)
		<div class="border rounded-md p-2 my-5">
			<div class="text-center">
				<a href="" class="font-semibold mr-5">{{ $comment->users->name }}</a>
				<a href="" class="font-semibold">{{ $comment->created_at->diffForHumans() }}</a>
				<p class="text-sm mt-2">{!! $comment->body !!}</p>
			</div>
		</div>
	@endforeach						
</div>

<div class="w-4/5 mx-auto my-10">
	<form method="POST" action="/support/{{ $ticket->id }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	{{ method_field('PUT') }}     
		<div class="text-center">
			<div class="text-red-500">{{ $errors->has('comment') ? 'You need to tell us something before replying :)' : '' }}</div>
				<textarea class="w-full border" name="comment" id="text" placeholder="Reply here."></textarea>
		</div>  
		<button type="submit" class="border p-2 bg-gray-700 text-white my-2" style="">Add Reply</button> 
	</form>			
</div>

@endsection