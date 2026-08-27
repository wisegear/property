@extends('layouts.admin')

@section('title', 'Change Log')

@section('content')
<div class="mx-auto max-w-7xl px-4">
    <section class="mb-8 flex flex-col items-start justify-between gap-6 rounded-xl border border-zinc-200 bg-white p-8 shadow-sm md:flex-row md:items-center">
        <div>
            <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white/70 px-3 py-1 text-xs text-zinc-700 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span> Admin Console
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Change Log</h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-zinc-500 md:text-base">Manage public records of site changes and data updates.</p>
        </div>
        <a href="{{ route('admin.changelog.create') }}" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-black">+ Add Entry</a>
    </section>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('success') }}</div>
    @endif

    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        @if ($entries->isEmpty())
            <p class="text-sm text-zinc-600">No Change Log entries yet. Add your first one.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b border-zinc-200 bg-zinc-50 text-left text-zinc-600">
                        <th class="px-4 py-2 font-semibold">Entry</th><th class="px-4 py-2 font-semibold">Published</th><th class="px-4 py-2 font-semibold">Author</th><th class="px-4 py-2 text-right font-semibold">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3"><span class="text-xs font-semibold uppercase text-zinc-500">{{ $entry->category }}</span><div class="font-medium text-zinc-900">{{ $entry->title }}</div></td>
                                <td class="px-4 py-3 text-zinc-700">{{ $entry->published_at->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $entry->author?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right"><div class="inline-flex items-center gap-3">
                                    <a href="{{ route('admin.changelog.edit', $entry) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form action="{{ route('admin.changelog.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this Change Log entry?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline">Delete</button></form>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $entries->links() }}</div>
        @endif
    </section>
</div>
@endsection
