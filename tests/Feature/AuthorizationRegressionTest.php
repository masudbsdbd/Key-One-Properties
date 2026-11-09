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

            if (str_starts_with($name, 'investor.') || str_starts_with($name, 'customer.')) {
                // Portal pages exist only for portal accounts; staff are told nothing.
                $this->assertContains($response->getStatusCode(), [403, 404], "Portal route [{$name}] answers a staff user.");
            } elseif (str_starts_with($name, 'options.')) {
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

    public function test_user_without_permissions_cannot_write_through_any_endpoint(): void
    {
        $user = $this->userWith([]);
        $checked = 0;

        foreach ($this->appRoutes() as $route) {
            $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true));
            if (! $method || $route->parameterNames() !== [] || str_starts_with($route->getName(), 'profile.') || str_starts_with($route->getName(), 'notifications.')) {
                continue;
            }

            $response = $this->actingAs($user)->ajax()->call($method, $this->uri($route));
            if (! in_array($response->getStatusCode(), [403, 404], true)) {
                $failures[] = "{$route->getName()} {$method} → {$response->getStatusCode()}";
            }
            $checked++;
        }

        $this->assertSame([], $failures ?? [], 'Routes that accept writes before a permission check.');
        $this->assertGreaterThan(20, $checked);
    }

    public function test_every_record_route_answers_not_found_for_an_unknown_public_id(): void
    {
        $admin = $this->superAdmin();
        $checked = 0;

        foreach ($this->appRoutes() as $route) {
            if ($route->parameterNames() === [] || str_starts_with($route->getName(), 'attachments.') || str_starts_with($route->getName(), 'profile.')) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $response = $this->actingAs($admin)->ajax()->call($method, $this->uri($route));

            if (! in_array($response->getStatusCode(), [404, 403], true) || str_contains($response->getContent(), 'App\\Models')) {
                $failures[] = "{$route->getName()} {$method} → {$response->getStatusCode()}";
            }
            $checked++;
        }

        $this->assertSame([], $failures ?? [], 'Routes that do not answer 404 for an unknown id.');
        $this->assertGreaterThan(100, $checked);
    }

    public function test_unknown_public_ids_return_not_found_without_details(): void
    {
        $response = $this->actingAs($this->superAdmin())->ajax()->get('/projects/doesnotexist0000/edit');

        $response->assertNotFound()->assertJsonPath('message', 'The requested item was not found.');
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }
}
