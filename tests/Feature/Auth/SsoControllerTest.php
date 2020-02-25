<?php


namespace Seatplus\Auth\Tests\Feature\Auth;


use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\Contracts\Provider;

class SsoControllerTest extends TestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    private $character;

    /**
     * @var \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    private $refresh_token;

    public function setUp(): void
    {

        parent::setUp();

        $this->refresh_token = factory(RefreshToken::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id
        ]);

        $this->character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id
        ]);

        $this->test_user = $this->test_user->refresh();
    }

    /** @test */
    public function it_works_for_non_authed_users()
    {
        $character_id = factory(CharacterInfo::class)->make()->character_id;

        $abstractUser = $this->createSocialiteUser($character_id);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

        $this->assertDatabaseMissing('refresh_tokens', [
            'character_id' => $character_id
        ]);

        $response = $this->get(route('auth.eve.callback'))
            ->assertRedirect();

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $character_id
        ]);
    }

    /** @test */
    public function it_returns_error_if_scopes_changed()
    {
        $character_id = factory(CharacterInfo::class)->make()->character_id;

        $abstractUser = $this->createSocialiteUser($character_id);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

        $this->actingAs($this->test_user);

        session([
            'sso_scopes' => ['test'],
            'rurl' => '/home'
        ]);

        $this->get(route('auth.eve.callback'));

        $this->assertEquals(
            'Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.',
            session('error')
            );
    }

    private function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
    {
        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->character_id = $character_id;
        $socialiteUser->refresh_token = $refresh_token;
        $socialiteUser->character_owner_hash = sha1($token);
        $socialiteUser->scopes = $scopes;
        $socialiteUser->token = $token;
        $socialiteUser->expires_on = carbon('now')->addMinutes(15);

        return $socialiteUser;
    }
}
