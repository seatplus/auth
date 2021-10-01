<?php

use Faker\Factory;
use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Containers\EveUser;
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
            $helper_token = RefreshToken::factory()->scopes($scopes)->make([
                'character_id' => $refresh_token->character_id
            ]);

            $refresh_token->token = $helper_token->token;
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

function createSocialiteUser($character_id = null, array $scopes = ["esi-skills.read_skills.v1", "esi-skills.read_skillqueue.v1",])
{

    $refresh_token = RefreshToken::factory()->scopes($scopes)->make();

    $socialiteUser = test()->createMock(SocialiteUser::class);
    $socialiteUser->character_owner_hash = faker()->sha256;
    //name - we don't care for that
    $socialiteUser->character_id = $character_id ?? $refresh_token->character_id;
    $socialiteUser->token = $refresh_token->token;
    $socialiteUser->refreshToken = $refresh_token->refresh_token;
    $socialiteUser->expiresIn = 12*60; //let's just say 12 minutes
    $socialiteUser->user = [
        'scp' => $scopes
    ];

    return $socialiteUser;
}

function faker()
{
    if(!isset(test()->faker)) {
        test()->faker = Factory::create();
    }

    return test()->faker;
}

function createEveUser(int $character_id = null, string $character_owner_hash = null): EveUser
{

    $faker = faker();

    return new EveUser([
        'character_id' => $character_id ?? $faker->numberBetween(90000000, 98000000),
        'character_owner_hash' => $character_owner_hash ?? sha1($faker->text),
        'token' => sha1($faker->text),
        'refreshToken' => sha1($faker->text),
        'expiresIn' => $faker->numberBetween(1,20),
        'user' => ['user'],
    ]);
}