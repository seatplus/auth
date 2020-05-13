<?php


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

        Event::fakeFor(function () {

            $this->test_character = factory(CharacterInfo::class)->create([
                'character_id' => $this->test_user->character_users->first()->character_id,
            ]);
        });

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
