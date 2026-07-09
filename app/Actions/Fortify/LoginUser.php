<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginUser
{
    public function __invoke(Request $request)
    {
        $login = trim((string) $request->email);

        if ($login === '') {
            return null;
        }

        // Pruefen, ob die Eingabe eine gueltige E-Mail ist.
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::query()
            ->when(
                $field === 'email',
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [Str::lower($login)]),
                fn ($query) => $query->where($field, $login)
            )
            ->first();

        if ($user && Hash::check((string) $request->password, $user->password)) {
            return $user;
        }

        return null;
    }
}
