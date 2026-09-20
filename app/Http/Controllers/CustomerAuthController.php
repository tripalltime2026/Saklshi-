<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Services\SmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CustomerAuthController extends Controller
{
    public function showPhone(Request $request): View
    {
        if ($request->session()->has('saklshi_customer_id')) {
            return view('account.already-authenticated');
        }

        return view('account.phone');
    }

    public function requestCode(Request $request, SmsSender $sms): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:24'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        $last = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'login')
            ->latest('id')
            ->first();

        if ($last && now()->diffInSeconds($last->created_at, true) < 60) {
            throw ValidationException::withMessages([
                'phone' => 'ახალი კოდის მოთხოვნა შესაძლებელია 60 წამში ერთხელ.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $id = DB::table('phone_verification_codes')->insertGetId([
            'phone' => $phone,
            'purpose' => 'login',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $sms->send(
                $phone,
                'ბათუმის სახლი — ავტორიზაციის კოდი: '.$code.'. კოდი მოქმედებს 5 წუთი.',
                'login-'.$id,
            );
        } catch (Throwable $e) {
            DB::table('phone_verification_codes')->where('id', $id)->delete();
            report($e);

            return back()->withInput()->withErrors([
                'phone' => 'SMS-ის გაგზავნა ვერ მოხერხდა. გთხოვთ სცადოთ ხელახლა.',
            ]);
        }

        $request->session()->put('saklshi_pending_phone', $phone);

        return redirect()->route('account.verify')->with('status', 'კოდი გამოგზავნილია.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('saklshi_pending_phone');

        if (! $phone) {
            return redirect()->route('account.login');
        }

        return view('account.verify', compact('phone'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = (string) $request->session()->get('saklshi_pending_phone', '');

        if ($phone === '') {
            return redirect()->route('account.login');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $record = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record || (int) $record->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => 'კოდი აღარ არის მოქმედი. მოითხოვეთ ახალი კოდი.',
            ]);
        }

        if (! Hash::check($validated['code'], $record->code_hash)) {
            DB::table('phone_verification_codes')->where('id', $record->id)->increment('attempts');

            throw ValidationException::withMessages([
                'code' => 'კოდი არასწორია.',
            ]);
        }

        $user = DB::transaction(function () use ($record, $phone) {
            DB::table('phone_verification_codes')
                ->where('id', $record->id)
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            $user = User::query()->firstOrCreate(
                ['phone' => $phone],
                ['phone_verified_at' => now(), 'locale' => 'ka', 'active' => true],
            );

            if (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            $profile = $user->profile()->firstOrCreate([]);

            $latestReservation = Reservation::query()
                ->where('phone', $phone)
                ->latest('id')
                ->first();

            if ($latestReservation && ! $profile->first_name) {
                $profile->fill([
                    'first_name' => $latestReservation->first_name,
                    'last_name' => $latestReservation->last_name,
                    'birth_date' => $latestReservation->birth_year
                        ? sprintf('%04d-%02d-%02d', $latestReservation->birth_year, $latestReservation->birth_month, $latestReservation->birth_day)
                        : null,
                    'marketing_consent' => $latestReservation->marketing_consent,
                    'marketing_consent_at' => $latestReservation->consent_at,
                ])->save();
            }

            Reservation::query()
                ->where('phone', $phone)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            return $user;
        }, 3);

        $request->session()->forget('saklshi_pending_phone');
        $request->session()->put('saklshi_customer_id', $user->id);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['saklshi_customer_id', 'saklshi_pending_phone']);
        $request->session()->regenerateToken();

        return redirect()->route('reservation.index');
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '995'.$digits;
        }

        if (! preg_match('/^9955\d{8}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => 'შეიყვანეთ საქართველოს მობილურის ნომერი, მაგალითად +995 5XX XX XX XX.',
            ]);
        }

        return '+'.$digits;
    }
}
