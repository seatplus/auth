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

namespace Seatplus\Auth\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Services\GetSRequiredScopes;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

class GetRequiredScopesServiceTest extends TestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    private $character;

    /**
     * @var \Seatplus\Auth\Services\GetSRequiredScopes
     */
    private GetSRequiredScopes $action;

    public function setUp(): void
    {
        parent::setUp();

        $this->action = new GetSRequiredScopes();

        $this->test_user = $this->test_user->refresh();
    }

    /** @test */
    public function it_returns_minimal_scope()
    {
        $scopes = $this->action->execute()->toArray();

        $this->assertEquals($scopes, config('eveapi.scopes.minimum'));
    }

    /** @test */
    public function it_returns_setup_scopes()
    {
        // 2. Create SsoScope (Corporation)
        $this->createCorporationSsoScope([
            'character'   => ['b'],
            'corporation' => [],
        ]);

        $this->actingAs($this->test_user);

        $scopes = $this->action->execute()->toArray();

        $this->assertEquals($scopes, array_merge(config('eveapi.scopes.minimum'), ['b']));
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
