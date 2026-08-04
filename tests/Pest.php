<?php

use Faker\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;
use Spatie\Permission\PermissionRegistrar;

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
uses(TestCase::class)->in('Unit', 'Feature');
uses(LazilyRefreshDatabase::class)->in('Unit', 'Feature');

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
function createRefreshTokenWithScopes(array $scopes): void
{
    Event::fakeFor(function () use ($scopes) {
        if (test()->test_character->refreshToken) {
            $refresh_token = test()->test_character->refreshToken;
            $helper_token = RefreshToken::factory()->scopes($scopes)->make([
                'character_id' => $refresh_token->character_id,
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
        'morphable_id' => test()->test_character->corporation->corporation_id,
        'morphable_type' => CorporationInfo::class,
        'type' => $type,
    ]);
}

function createSocialiteUser($character_id = null, array $scopes = ['esi-skills.read_skills.v1', 'esi-skills.read_skillqueue.v1'])
{
    $refresh_token = RefreshToken::factory()->scopes($scopes)->make();

    $socialiteUser = Mockery::mock(SocialiteUser::class)->makePartial();

    // Socialite's AbstractUser::map() merges into an array, so the real provider
    // hands us an array here — mirror that shape.
    $socialiteUser->attributes = [
        'character_id' => $character_id ?? $refresh_token->character_id,
        'character_owner_hash' => faker()->sha256,
    ];
    $socialiteUser->token = $refresh_token->token;
    $socialiteUser->refreshToken = $refresh_token->refresh_token;
    $socialiteUser->expiresIn = 12 * 60; // let's just say 12 minutes
    $socialiteUser->user = [
        'scp' => $scopes,
    ];

    return $socialiteUser;
}

function faker()
{
    return Factory::create();
}

function createEveUser(?int $character_id = null, ?string $character_owner_hash = null): EveUser
{
    $faker = faker();

    return new EveUser(
        character_id: $character_id ?? $faker->numberBetween(90000000, 98000000),
        character_owner_hash: $character_owner_hash ?? sha1((string) $faker->text),
        token: sha1((string) $faker->text),
        refreshToken: sha1((string) $faker->text),
        expiresIn: $faker->numberBetween(1, 20),
        user: ['user'],
    );
}

function assignPermissionToTestUser(array|string $permission_strings)
{
    $permission_strings = is_array($permission_strings) ? $permission_strings : [$permission_strings];

    foreach ($permission_strings as $string) {
        $permission = Permission::findOrCreate($string);

        test()->test_user->givePermissionTo($permission);
    }

    // now re-register all the roles and permissions
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}
