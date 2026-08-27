<?php

namespace App\Http\Controllers;

use App\Models\ChangeLogEntry;
use Illuminate\View\View;

class ChangeLogController extends Controller
{
    public function index(): View
    {
        $entries = ChangeLogEntry::query()->with('author')->where('published_at', '<=', now())->latest('published_at')->paginate(12);

        return view('changelog.index', compact('entries'));
    }

    public function show(ChangeLogEntry $changeLogEntry): View
    {
        abort_if($changeLogEntry->published_at->isFuture(), 404);
        $changeLogEntry->load('author');

        return view('changelog.show', compact('changeLogEntry'));
    }
}
