<?php

namespace Tests\Unit;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class SanctumCompatibilityTest extends TestCase
{
    public function test_sanctum_csrf_middleware_configuration_supports_laravel_10_and_11_plus(): void
    {
        $this->assertSame(EncryptCookies::class, config('sanctum.middleware.encrypt_cookies'));

        if (class_exists(ValidateCsrfToken::class)) {
            $this->assertSame(ValidateCsrfToken::class, config('sanctum.middleware.validate_csrf_token'));
            $this->assertNull(config('sanctum.middleware.verify_csrf_token'));

            return;
        }

        $this->assertNull(config('sanctum.middleware.validate_csrf_token'));
        $this->assertSame(VerifyCsrfToken::class, config('sanctum.middleware.verify_csrf_token'));
    }
}
