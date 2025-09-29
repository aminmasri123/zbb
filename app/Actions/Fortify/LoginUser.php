<?php

namespace App\Actions\Fortify;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginUser
{
    public function __invoke(Request $request)
    {
          $login = $request->email;

            // Prüfen, ob die Eingabe eine gültige E-Mail ist
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $user = User::where($field, $login)->first();

            if ($user &&
                Hash::check($request->password, $user->password)) {
                return $user;
            }
    }

}
