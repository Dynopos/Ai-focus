<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Services\Executive\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Executive context the AI uses. The CEO can review, correct and delete every entry. */
class MemoryController extends Controller
{
    public function index(Request $request): View
    {
        $memories = $request->user()->business->memories()->latest()->get()->groupBy('category');

        return view('memory.index', ['memories' => $memories, 'categories' => Memory::CATEGORIES]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $memory = $request->user()->business->memories()->create($this->validated($request) + [
            'source' => 'ceo',
            'created_by' => $request->user()->id,
        ]);
        $audit->record($memory->business_id, 'memory.created', $memory, ['title' => $memory->title], Actor::user($request->user()));

        return back()->with('status', 'Saved to executive memory.');
    }

    public function edit(Memory $memory): View
    {
        Gate::authorize('update', $memory);

        return view('memory.edit', ['memory' => $memory, 'categories' => Memory::CATEGORIES]);
    }

    public function update(Request $request, Memory $memory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $memory);
        $memory->update($this->validated($request));
        $audit->record($memory->business_id, 'memory.updated', $memory, [], Actor::user($request->user()));

        return redirect()->route('memory.index')->with('status', 'Memory corrected.');
    }

    public function destroy(Request $request, Memory $memory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $memory);
        $memory->forceDelete(); // deletion means gone, not hidden
        $audit->record($memory->business_id, 'memory.deleted', null, ['id' => $memory->id], Actor::user($request->user()));

        return back()->with('status', 'Memory deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Memory::CATEGORIES))],
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:3000'],
            'is_verified' => ['nullable', 'boolean'],
        ]);
        $data['is_verified'] = (bool) ($data['is_verified'] ?? false);

        return $data;
    }
}
