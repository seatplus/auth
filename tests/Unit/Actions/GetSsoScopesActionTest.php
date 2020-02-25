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

namespace Seatplus\Auth\Tests\Unit\Actions;

use Seatplus\Auth\Http\Actions\Sso\GetSsoScopesAction;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

class GetSsoScopesActionTest extends TestCase
{
    /**
     * @var \Seatplus\Auth\Http\Actions\Sso\GetSsoScopesAction
     */
    private GetSsoScopesAction $action;

    /**
     * @var \Seatplus\Eveapi\Models\RefreshToken
     */
    private RefreshToken $refresh_token;

    /**
     * @var \Seatplus\Eveapi\Models\Character\CharacterInfo
     */
    private CharacterInfo $character;

    public function setUp(): void
    {
        parent::setUp();

        $this->action = new GetSsoScopesAction();
        $this->refresh_token = factory(RefreshToken::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id,
        ]);

        $this->character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id,
        ]);

        $this->test_user = $this->test_user->refresh();
    }

    /** @test */
    public function it_returns_minimal_scope()
    {
        $scopes = $this->action->execute();

        $this->assertEquals($scopes, config('eveapi.scopes.minimum'));
    }

    /** @test */
    public function it_does_not_add_scopes_to_minimal_if_unauthed()
    {
        $scopes = $this->action->execute($this->character->character_id, ['test scope']);

        $scopes_expected = array_merge(config('eveapi.scopes.minimum'), ['test scope']);

        $this->assertEmpty(array_diff($scopes_expected, $scopes));
    }

    /** @test */
    public function it_does_add_scopes()
    {
        $this->actingAs($this->test_user);

        $this->character->refresh_token->scopes = ['publicData','another already assigned scope'];
        $this->character->refresh_token->save();

        $scopes = $this->action->execute($this->character->character_id, ['test scope']);

        $this->assertNotEquals($scopes, config('eveapi.scopes.minimum'));

        $this->assertTrue(in_array('test scope', $scopes));
    }
}
