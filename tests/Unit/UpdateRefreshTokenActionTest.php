<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Seatplus\Auth\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\RefreshToken;

class UpdateRefreshTokenActionTest extends TestCase
{
    /** @test */
    public function CreateRefreshToken()
    {
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        Event::fakeFor(function () use ($eve_data) {
            (new UpdateRefreshTokenAction())->execute($eve_data);
        });

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id,
        ]);
    }

    /** @test */
    public function it_does_update_refresh_token_active_sessions()
    {
        $this->actingAs($this->test_user);

        // create RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        Event::fakeFor(function () use ($eve_data) {
            (new UpdateRefreshTokenAction())->execute($eve_data);
        });

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id'  => $this->test_user->id,
            'refresh_token' => 'refresh_token',
        ]);

        $this->actingAs($this->test_user);
        // Change RefreshToken

        $eve_data = $this->createSocialiteUser($this->test_user->id, 'new_refreshToken');

        (new UpdateRefreshTokenAction())->execute($eve_data);

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id'  => $this->test_user->id,
            'refresh_token' => 'new_refreshToken',
        ]);
    }

    /** @test */
    public function it_does_not_update_refresh_token_for_new_session_of_a_valid_refresh_token_user()
    {
        // create RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        Event::fakeFor(function () use ($eve_data) {
            RefreshToken::factory()->create([
                'character_id'  => $this->test_user->id,
                'refresh_token' => 'refresh_token',
            ]);

            (new UpdateRefreshTokenAction())->execute($eve_data);
        });

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id'  => $this->test_user->id,
            'refresh_token' => 'refresh_token',
        ]);

        // Change RefreshToken

        $eve_data = $this->createSocialiteUser($this->test_user->id, 'new_refreshToken');

        (new UpdateRefreshTokenAction())->execute($eve_data);

        $this->assertDatabaseMissing('refresh_tokens', [
            'character_id'  => $this->test_user->id,
            'refresh_token' => 'new_refreshToken',
        ]);
    }

    /** @test */
    public function RestoreTrashedRefreshToken()
    {
        // create RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id);

        Event::fakeFor(function () use ($eve_data) {
            (new UpdateRefreshTokenAction())->execute($eve_data);
        });

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $this->test_user->id,
        ]);

        // Assert if RefreshToken was created
        $refresh_token = RefreshToken::find($this->test_user->id);

        $this->assertNotEmpty($refresh_token);

        // SoftDelete RefreshToken
        $refresh_token->delete();

        $this->assertSoftDeleted(RefreshToken::find($this->test_user->id));

        // Recreate RefreshToken
        $eve_data = $this->createSocialiteUser($this->test_user->id, 'newRefreshToken');
        (new UpdateRefreshTokenAction())->execute($eve_data);
        $this->assertNotEmpty(RefreshToken::find($this->test_user->id));
        $this->assertDatabaseHas('refresh_tokens', [
            'character_id'  => $this->test_user->id,
            'refresh_token' => 'newRefreshToken',
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
