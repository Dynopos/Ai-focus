<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->business->contacts()->withCount(['tasks' => fn ($q) => $q->open()])->orderBy('name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%"));
        }

        return view('contacts.index', ['contacts' => $query->paginate(30)->withQueryString(), 'search' => $search]);
    }

    public function create(): View
    {
        return view('contacts.form', ['contact' => new Contact]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->business->contacts()->create($this->validated($request));

        return redirect()->route('contacts.index')->with('status', 'Customer saved.');
    }

    public function edit(Contact $contact): View
    {
        Gate::authorize('update', $contact);

        return view('contacts.form', ['contact' => $contact]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);
        $contact->update($this->validated($request, $contact));

        return redirect()->route('contacts.index')->with('status', 'Customer updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return redirect()->route('contacts.index')->with('status', 'Customer deleted.');
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ]);

        $optIn = (bool) ($data['whatsapp_opt_in'] ?? false);
        unset($data['whatsapp_opt_in']);
        // Keep the original opt-in timestamp as evidence; clear it if consent is withdrawn.
        $data['whatsapp_opt_in_at'] = $optIn ? ($contact?->whatsapp_opt_in_at ?? now()) : null;

        return $data;
    }
}
