<?php


namespace Seatplus\Auth\Tests\Unit;

use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;

class UpdateRefreshTokenActionTest extends TestCase
{
    /** @test */
    public function CreateRefreshToken()
    {
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        (new UpdateRefreshTokenAction)->execute($eve_data);

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id
        ]);

    }

    /** @test */
    public function UpdateRefreshToken()
    {
        // create RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        (new UpdateRefreshTokenAction)->execute($eve_data);

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id,
            'refresh_token' => 'refresh_token'
        ]);

        // Change RefreshToken

        $eve_data = $this->createSocialiteUser($this->test_user->id,'new_refreshToken');

        (new UpdateRefreshTokenAction)->execute($eve_data);

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id,
            'refresh_token' => 'new_refreshToken'
        ]);

    }

    /** @test */
    public function RestoreTrashedRefreshToken()
    {
        // create RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        (new UpdateRefreshTokenAction)->execute($eve_data);

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id
        ]);

        // Assert if RefreshToken was created
        $refresh_token = RefreshToken::find($this->test_user->id);

        $this->assertNotEmpty($refresh_token);

        // SoftDelete RefreshToken
        $refresh_token->delete();

        $this->assertSoftDeleted(RefreshToken::find($this->test_user->id));

        // Recreate RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id, 'newRefreshToken');
        (new UpdateRefreshTokenAction)->execute($eve_data);
        $this->assertNotEmpty(RefreshToken::find($this->test_user->id));
        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id,
            'refresh_token' => 'newRefreshToken'
        ]);

    }

    private function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
    {
        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->character_id = $character_id;
        $socialiteUser->refresh_token = $refresh_token;
        $socialiteUser->scopes = $scopes;
        $socialiteUser->token = $token;
        $socialiteUser->expires_on = carbon('now')->addMinutes(15);


        return $socialiteUser;
    }

}
