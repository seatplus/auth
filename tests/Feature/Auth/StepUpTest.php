<?php


namespace Seatplus\Auth\Tests\Feature\Auth;


use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

class StepUpTest extends TestCase
{
    public function setUp(): void
    {

        parent::setUp();

        Event::fake();

        $this->test_character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id,
        ]);

        //\Mockery::mock(Factory::class)->shouldReceive('driver')->andReturn('');
        Socialite::shouldReceive('driver->scopes->redirect')->andReturn('');
    }

    /** @test */
    public function one_can_request_another_scope()
    {

        // 1. Create refresh_token
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        /*$this->createCorporationSsoScope([
            'character'   => ['a'],
            'corporation' => [],
        ]);*/

        $add_scopes =  implode(',',['1','2']);

        $response = $this->actingAs($this->test_user)->get(route('auth.eve.step_up', [
            'character_id' => $this->test_character->character_id,
            'add_scopes' => $add_scopes
        ]));

        $this->assertEquals($this->test_character->character_id,session('step_up'));
        $this->assertEquals(['a', 'b', '1','2'], session('sso_scopes'));
    }

    private function createCorporationSsoScope(array $array)
    {
        factory(SsoScopes::class)->create([
            'selected_scopes' => $array,
            'morphable_id'    => $this->test_character->corporation->corporation_id,
            'morphable_type'  => CorporationInfo::class,
        ]);
    }

    private function createRefreshTokenWithScopes(array $array)
    {
        Event::fakeFor(function () use ($array) {
            factory(RefreshToken::class)->create([
                'character_id' => $this->test_character->character_id,
                'scopes'       => $array,
            ]);
        });
    }
}
