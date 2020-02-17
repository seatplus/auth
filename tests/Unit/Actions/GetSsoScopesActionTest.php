<?php


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
            'character_id' => $this->test_user->character_users->first()->character_id
        ]);

        $this->character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id
        ]);

        $this->test_user = $this->test_user->refresh();
    }

    /** @test */
    public function it_returns_minimal_scope()
    {
        $scopes = $this->action->execute();

        $this->assertEquals($scopes,config('eveapi.scopes.minimum'));
    }

    /** @test */
    public function it_does_not_add_scopes_to_unauthed()
    {

        $scopes = $this->action->execute($this->character->character_id, ['test scope']);

        $this->assertEquals($scopes,config('eveapi.scopes.minimum'));
    }

    /** @test */
    public function it_does_add_scopes()
    {
        $this->actingAs($this->test_user);

        $scopes = $this->action->execute($this->character->character_id, ['test scope']);

        $this->assertNotEquals($scopes, config('eveapi.scopes.minimum'));

        $this->assertTrue(in_array('test scope', $scopes));
    }

    /** @test */
    public function it_does_not_add_scopes_if_character_does_not_belong_to_user()
    {
        $this->actingAs($this->test_user);

        $scopes = $this->action->execute($this->character->character_id+ 1, ['test scope']);

        $this->assertEquals($scopes, config('eveapi.scopes.minimum'));

        $this->assertFalse(in_array('test scope', $scopes));
    }

}
