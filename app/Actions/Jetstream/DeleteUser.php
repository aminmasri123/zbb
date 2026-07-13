<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        throw new HttpException(403, 'Die direkte Konto-Loeschung ist deaktiviert. Bitte reichen Sie zuerst einen Loeschantrag ein.');
    }
}
