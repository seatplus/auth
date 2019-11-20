<?php

namespace Seatplus\Auth\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Seatplus\Auth\Http\Middleware\CheckPermissionAndAffiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HasPermissionAndAffiliationMiddlewareTest extends TestCase
{
    protected $role;

    protected $permission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'writer']);
        $this->permission = Permission::create(['name' => 'edit articles']);

        $this->role->givePermissionTo($this->permission);
        $this->test_user->assignRole($this->role);
    }

    /** @test */
    public function actingAsGuestTest()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('User is not logged in.');

        $request = Request::create('/test');

        $middleware = new CheckPermissionAndAffiliation();

        $response = $middleware->handle($request, function ($req) {
        }, 'test_permission');
    }

    /** @test */
    public function missingPermissionTest()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [12345],
            ]),
        ]);

        $this->actingAs($this->test_user);

        // As this is the missingPermissionTest remove the permission from the role
        $this->role->revokePermissionTo($this->permission);

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => 'testing/12345']);

        $request->setRouteResolver(function () use ($request) {
            return (new Route('GET', 'testing/{character_id}', []))->bind($request);
        });

        $middleware = new CheckPermissionAndAffiliation();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Missing Permission: '.$this->permission->name);

        $response = $middleware->handle($request, function () {
        }, $this->permission->name);
    }

    /** @test */
    public function hasPermissionTest()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [12345],
            ]),
        ]);

        $this->actingAs($this->test_user);

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => 'testing/12345']);

        $request->setRouteResolver(function () use ($request) {
            return (new Route('GET', 'testing/{character_id}', []))->bind($request);
        });

        $middleware = new CheckPermissionAndAffiliation();

        $response = $middleware->handle($request, function () {
        }, $this->permission->name);

        $this->assertNull($response);
    }

    /** @test */
    public function accessOwnedCharactersTest()
    {
        // For testing create a forbidden affiliation
        $this->role->affiliations()->create([
            'forbidden' => collect([
                'character_ids' => [$this->test_user->characters->first()->character_id],
            ]),
        ]);

        $this->actingAs($this->test_user);

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => 'testing/'.$this->test_user->characters->first()->character_id]);

        $request->setRouteResolver(function () use ($request) {
            return (new Route('GET', 'testing/{character_id}', []))->bind($request);
        });

        $middleware = new CheckPermissionAndAffiliation();

        $response = $middleware->handle($request, function () {
        }, $this->permission->name);

        $this->assertNull($response);
    }
}
