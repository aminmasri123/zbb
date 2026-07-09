<?php

namespace Tests\Unit;

use App\Http\Middleware\AuthorizeAnyPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthorizeAnyPermissionTest extends TestCase
{
    public function test_it_allows_the_request_when_the_user_has_any_listed_permission(): void
    {
        $middleware = new AuthorizeAnyPermission();
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => new class {
            public function can(string $permission): bool
            {
                return $permission === 'berechtigung.update';
            }
        });

        $response = $middleware->handle(
            $request,
            fn () => new Response('ok'),
            'berechtigung.zuweisen',
            'berechtigung.update',
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_it_denies_the_request_when_the_user_has_no_listed_permission(): void
    {
        $middleware = new AuthorizeAnyPermission();
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => new class {
            public function can(string $permission): bool
            {
                return false;
            }
        });

        $this->expectException(AuthorizationException::class);

        $middleware->handle(
            $request,
            fn () => new Response('ok'),
            'berechtigung.zuweisen',
            'berechtigung.update',
        );
    }
}
