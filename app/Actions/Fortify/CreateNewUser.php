<?php

namespace App\Actions\Fortify;

use App\Models\Personen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $person = Personen::create([
                'vorname' => $input['first_name'],
                'nachname' => $input['last_name'],
                'geschlecht' => 'd',
                'typ' => 'mitarbeiter',
                'aktiv' => true,
            ]);

            return User::create([
                'person_id' => $person->id,
                'username' => $this->uniqueUsername($input),
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
        });
    }

    private function uniqueUsername(array $input): string
    {
        $base = Str::of($input['email'])
            ->before('@')
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '.')
            ->trim('.-_')
            ->limit(40, '')
            ->toString();

        if ($base === '') {
            $base = Str::slug($input['first_name'] . '.' . $input['last_name']) ?: 'user';
        }

        $candidate = $base;
        $counter = 1;

        while (User::where('username', $candidate)->exists()) {
            $suffix = '-' . $counter++;
            $candidate = substr($base, 0, 50 - strlen($suffix)) . $suffix;
        }

        return $candidate;
    }
}
