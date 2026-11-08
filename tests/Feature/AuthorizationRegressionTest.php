<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Sweeps the route table so a new endpoint cannot ship without authentication
 * or without a permission check.
 */
class AuthorizationRegressionTest extends TestCase
{
    private const PUBLIC_ROUTES = [
        'login', 'login.store', 'two-factor.challenge', 'two-factor.verify',
        'password.request', 'password.request.store', 'logout',
    ];

    /**
     * @return array<int, Route>
     */
    private function appRoutes(): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => $r->getName() && ! str_starts_with($r->getName(), 'dev.') && ! str_starts_with($r->uri(), 'up'))
            ->reject(fn (Route $r) => in_array($r->getName(), self::PUBLIC_ROUTES, true))
            ->values()->all();
    }

    private function uri(Route $route): string
    {
        // Use a value that satisfies the route's own constraints (e.g. {action} limited to reject|reverse).
        $uri = preg_replace_callback('/\{([^}?]+)\??\}/', function (array $m) use ($route) {
            $pattern = $route->wheres[$m[1]] ?? null;

            return $pattern ? explode('|', trim($pattern, '()'))[0] : 'aaaaaaaaaaaaaaaa';
        }, $route->uri());

        return '/'.ltrim($uri, '/');
    }

    public function test_every_application_route_requires_authentication(): void
    {
        foreach ($this->appRoutes() as $route) {
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $response = $this->ajax()->call($method, $this->uri($route));

            $this->assertContains($response->getStatusCode(), [401, 302], "Route [{$route->getName()}] {$method} {$route->uri()} is reachable without signing in.");
        }
    }

    public function test_user_without_permissions_is_forbidden_on_module_pages(): void
    {
        $user = $this->userWith([]);
        $checked = 0;

        foreach ($this->appRoutes() as $route) {
            $name = $route->getName();
            if (! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) {
                continue;
            }
            if (in_array($name, ['dashboard', 'search', 'profile.edit'], true)) {
                continue;
            }

            $response = $this->actingAs($user)->ajax()->get($this->uri($route));

            if (str_starts_with($name, 'options.')) {
                // Option sources answer 403 or an empty, authorisation-filtered list.
                $this->assertTrue(
                    $response->getStatusCode() === 403 || $response->json('results') === [],
                    "Option source [{$name}] leaks data to a user without permissions.",
                );
            } else {
                $this->assertSame(403, $response->getStatusCode(), "Route [{$name}] is not permission-checked.");
            }
            $checked++;
        }

        $this->assertGreaterThan(15, $checked);
    }

    public function test_unknown_public_ids_return_not_found_without_details(): void
    {
        $response = $this->actingAs($this->superAdmin())->ajax()->get('/projects/doesnotexist0000/edit');

        $response->assertNotFound()->assertJsonPath('message', 'The requested item was not found.');
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }
}
