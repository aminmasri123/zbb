<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ApiTokenFeatureDisabledTest extends TestCase
{
    public function test_personal_api_token_management_is_disabled(): void
    {
        $this->assertFalse(Features::hasApiFeatures());
        $this->assertFalse(Route::has('api-tokens.index'));
        $this->assertFalse(Route::has('api-tokens.store'));
        $this->assertFalse(Route::has('api-tokens.update'));
        $this->assertFalse(Route::has('api-tokens.destroy'));
    }
}
