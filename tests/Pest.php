<?php

use Illuminate\Support\Facades\Event;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

/** @link https://pestphp.com/docs/underlying-test-case */
uses(\Seatplus\Auth\Tests\TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/** @link https://pestphp.com/docs/expectations#custom-expectations */

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** @link https://pestphp.com/docs/helpers */
function createRefreshTokenWithScopes(array $scopes)
{
    Event::fakeFor(function () use ($scopes) {

        if(test()->test_character->refresh_token) {

            $refresh_token = test()->test_character->refresh_token;
            $token = json_decode($refresh_token->getRawOriginal('token'), true);
            data_set($token, 'scp', $scopes);
            $refresh_token->token = json_encode($token);
            $refresh_token->save();

            return;
        }

        RefreshToken::factory()->scopes($scopes)->create([
            'character_id' => test()->test_character->character_id,
        ]);
    });
}

function createCorporationSsoScope(array $array, string $type = 'default')
{
    SsoScopes::factory()->create([
        'selected_scopes' => $array,
        'morphable_id'    => test()->test_character->corporation->corporation_id,
        'morphable_type'  => CorporationInfo::class,
        'type' => $type
    ]);
}

function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
{
    $socialiteUser = test()->createMock(SocialiteUser::class);
    $socialiteUser->character_id = $character_id;
    $socialiteUser->refresh_token = $refresh_token;
    $socialiteUser->character_owner_hash = sha1($token);
    $socialiteUser->scopes = $scopes;
    $socialiteUser->token = $token;
    $socialiteUser->expires_on = carbon('now')->addMinutes(15);

    return $socialiteUser;
}