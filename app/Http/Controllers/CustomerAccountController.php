<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $this->user($request);
        $user->load('profile');

        $reservations = $user->reservations()
            ->with(['branch', 'items'])
            ->orderByDesc('visit_date')
            ->orderByDesc('start_minute')
            ->paginate(20);

        return view('account.dashboard', compact('user', 'reservations'));
    }

    public function edit(Request $request): View
    {
        $user = $this->user($request);
        $user->load('profile');

        return view('account.profile', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'locale' => ['required', Rule::in(['ka', 'en', 'ru'])],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'email' => $data['email'] ?: null,
            'locale' => $data['locale'],
        ]);

        $profile = $user->profile()->firstOrCreate([]);
        $newConsent = (bool) ($data['marketing_consent'] ?? false);

        $profile->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'birth_date' => $data['birth_date'] ?: null,
            'marketing_consent' => $newConsent,
            'marketing_consent_at' => $newConsent
                ? ($profile->marketing_consent_at ?: now())
                : null,
        ]);

        return redirect()->route('account.profile.edit')->with('success', 'პროფილი განახლდა.');
    }

    private function user(Request $request): User
    {
        return $request->attributes->get('customer');
    }
}
