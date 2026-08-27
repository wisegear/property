<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChangeLogEntryRequest;
use App\Http\Requests\UpdateChangeLogEntryRequest;
use App\Models\ChangeLogEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChangeLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $entries = ChangeLogEntry::query()->with('author')->latest('published_at')->paginate(20);

        return view('admin.changelog.index', compact('entries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.changelog.form', ['changeLogEntry' => new ChangeLogEntry(['published_at' => now()]), 'mode' => 'create']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChangeLogEntryRequest $request): RedirectResponse
    {
        $request->user()->changeLogEntries()->create($request->validated());

        return redirect()->route('admin.changelog.index')->with('success', 'Change Log entry created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChangeLogEntry $changeLogEntry): View
    {
        return view('admin.changelog.form', ['changeLogEntry' => $changeLogEntry, 'mode' => 'edit']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChangeLogEntryRequest $request, ChangeLogEntry $changeLogEntry): RedirectResponse
    {
        $changeLogEntry->update($request->validated());

        return redirect()->route('admin.changelog.index')->with('success', 'Change Log entry updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChangeLogEntry $changeLogEntry): RedirectResponse
    {
        $changeLogEntry->delete();

        return redirect()->route('admin.changelog.index')->with('success', 'Change Log entry deleted.');
    }
}
