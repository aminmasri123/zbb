<?php

namespace App\Services\Bop;

use App\Models\BereichsauswahlSetting;

class PublicAreaSelectionAccess
{
    public function activeSetting(string $token, array $relations = []): BereichsauswahlSetting
    {
        return BereichsauswahlSetting::query()
            ->with($relations)
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();
    }
}
