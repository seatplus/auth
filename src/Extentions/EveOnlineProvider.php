<?php

namespace Seatplus\Auth\Extentions;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Class EveOnlineProvider.
 * @package Seatplus\Web\Extentions
 */
class EveOnlineProvider extends AbstractProvider implements ProviderInterface
{

    /**
     * Base URL to the Eve Online Image Server.
     *
     * @var string
     */
    protected $imageUrl = 'https://image.eveonline.com/Character/';

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Laravel\Socialite\Contracts\User
     */
    public function user()
    {

        if ($this->hasInvalidState())
            throw new InvalidStateException;

        $tokens = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject(
            array_merge(
                $this->getUserByToken($tokens['access_token']), [
                    'RefreshToken' => $tokens['refresh_token'],
                ]
            )
        );

        return $user->setToken($tokens['access_token']);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param  array $user
     *
     * @return \Laravel\Socialite\Two\User
     */
    protected function mapUserToObject(array $user): User
    {

        return (new User)->setRaw($user)->map([
            'character_id'         => $user['CharacterID'],
            'name'                 => $user['CharacterName'],
            'character_owner_hash' => $user['CharacterOwnerHash'],
            'scopes'               => $user['Scopes'],
            'refresh_token'        => $user['RefreshToken'],
            'expires_on'           => Carbon($user['ExpiresOn']),
            'avatar'               => $this->imageUrl . $user['CharacterID'] . '_128.jpg',
        ]);
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string $token
     *
     * @return array
     */
    protected function getUserByToken($token)
    {

        $response = $this->getHttpClient()
            ->get('https://login.eveonline.com/oauth/verify', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string $state
     *
     * @return string
     */
    protected function getAuthUrl($state)
    {

        return $this->buildAuthUrlFromBase(
            'https://login.eveonline.com/oauth/authorize', $state);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        $tokenFields = parent::getTokenFields($code);

        $tokenFields['grant_type'] = 'authorization_code';

        return $tokenFields;
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {

        return 'https://login.eveonline.com/oauth/token';
    }

    /**
     * Get the access tokens from the token response body.
     *
     * @param  string $body
     *
     * @return array
     */
    protected function parseAccessToken($body): array
    {

        $jsonResponse = json_decode($body, true);

        return [
            'access_token'  => $jsonResponse['access_token'],
            'refresh_token' => $jsonResponse['refresh_token'],
        ];
    }
}
