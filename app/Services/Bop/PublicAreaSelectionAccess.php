<?php

namespace App\Services\Bop;

use App\Models\BereichsauswahlSetting;

class PublicAreaSelectionAccess
{
    public function activeSetting(string $token, array $relations = []): BereichsauswahlSetting
    {
        $tokenSetting = BereichsauswahlSetting::query()
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

        $setting = BereichsauswahlSetting::query()
            ->forContext($tokenSetting->projekt_id, $tokenSetting->partner_id, $tokenSetting->schuljahr, $tokenSetting->teil)
            ->preferConfigured()
            ->firstOrFail();

        // An old alias token must also respect the current access switch.
        abort_unless($setting->zugang_aktiv, 404);

        return $setting->load($relations);
    }
}
