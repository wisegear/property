@extends('layouts.admin')

@section('title', $mode === 'create' ? 'Add Change Log Entry' : 'Edit Change Log Entry')

@section('content')
<div class="mx-auto max-w-5xl px-4">
    <section class="mb-8 rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
        <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white/70 px-3 py-1 text-xs text-zinc-700 shadow-sm"><span class="h-2 w-2 rounded-full bg-lime-500"></span> Admin Console</div>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">{{ $mode === 'create' ? 'Add Change Log Entry' : 'Edit Change Log Entry' }}</h1>
        <p class="mt-3 text-sm leading-7 text-zinc-500 md:text-base">Record a concise site change or data update.</p>
    </section>

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
        <form action="{{ $mode === 'create' ? route('admin.changelog.store') : route('admin.changelog.update', $changeLogEntry) }}" method="POST" class="space-y-8">
            @csrf
            @if ($mode === 'edit') @method('PUT') @endif
            <div class="grid gap-6 md:grid-cols-2">
                <div><label for="category" class="block text-sm font-medium text-zinc-700">Category <span class="text-red-500">*</span></label><select id="category" name="category" required class="mt-2 block w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-zinc-900 focus:ring-zinc-900"><option value="Change" @selected(old('category', $changeLogEntry->category) === 'Change')>Change</option><option value="Update" @selected(old('category', $changeLogEntry->category) === 'Update')>Update</option></select></div>
                <div><label for="published_at" class="block text-sm font-medium text-zinc-700">Published date <span class="text-red-500">*</span></label><input id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at', $changeLogEntry->published_at?->format('Y-m-d\TH:i')) }}" required class="mt-2 block w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-zinc-900 focus:ring-zinc-900"></div>
            </div>
            <div><label for="title" class="block text-sm font-medium text-zinc-700">Title <span class="text-red-500">*</span></label><input id="title" type="text" name="title" value="{{ old('title', $changeLogEntry->title) }}" required maxlength="255" class="mt-2 block w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-zinc-900 focus:ring-zinc-900"></div>
            <div><label for="body" class="block text-sm font-medium text-zinc-700">Body <span class="text-red-500">*</span></label><textarea id="body" name="body" rows="14" required class="mt-2 block w-full rounded-md border-zinc-300 text-sm leading-6 shadow-sm focus:border-zinc-900 focus:ring-zinc-900">{{ old('body', $changeLogEntry->body) }}</textarea><p class="mt-1 text-xs text-zinc-500">Plain text with paragraph breaks. No images are required.</p></div>
            <div class="flex items-center justify-end gap-4 pt-4"><a href="{{ route('admin.changelog.index') }}" class="text-sm text-zinc-600 hover:underline">Cancel</a><button type="submit" class="inline-flex items-center rounded-md bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-black">{{ $mode === 'create' ? 'Create' : 'Save changes' }}</button></div>
        </form>
    </section>
</div>
@endsection
