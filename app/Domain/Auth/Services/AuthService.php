<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\AccountBlockedException;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Exceptions\InvalidProviderTokenException;
use App\Domain\Auth\Exceptions\SocialEmailConflictException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use Throwable;

class AuthService
{
    private const TOKEN_NAME = 'api_token';

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken(self::TOKEN_NAME)->plainTextToken,
        ];
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws InvalidCredentialsException
     * @throws AccountBlockedException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->isBlocked()) {
            throw new AccountBlockedException;
        }

        return [
            'user' => $user,
            'token' => $user->createToken(self::TOKEN_NAME)->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * @return array{user: User, token: string}
     *
     * @throws InvalidProviderTokenException
     * @throws SocialEmailConflictException
     * @throws AccountBlockedException
     */
    public function loginWithGoogle(string $token): array
    {
        try {
            /** @var GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $providerUser = $provider->userFromToken($token);
        } catch (Throwable $e) {
            throw new InvalidProviderTokenException($e);
        }

        return $this->findOrCreateSocialUser('google_id', $providerUser);
    }

    /**
     * @return array{user: User, token: string}
     *
     * @throws InvalidProviderTokenException
     * @throws SocialEmailConflictException
     * @throws AccountBlockedException
     */
    public function loginWithApple(string $token): array
    {
        try {
            /** @var AppleProvider $provider */
            $provider = Socialite::driver('apple');
            $providerUser = $provider->userByIdentityToken($token);
        } catch (Throwable $e) {
            throw new InvalidProviderTokenException($e);
        }

        return $this->findOrCreateSocialUser('apple_id', $providerUser);
    }

    /**
     * Find the user already linked to this provider account, or create one on
     * first login. An email already owned by a different account (whether a
     * regular password account or one linked to another provider) is treated
     * as a conflict rather than silently merged, since we have no proof the
     * caller actually controls that existing account.
     *
     * @return array{user: User, token: string}
     *
     * @throws SocialEmailConflictException
     * @throws AccountBlockedException
     */
    private function findOrCreateSocialUser(string $providerColumn, SocialiteUser $providerUser): array
    {
        $providerId = (string) $providerUser->getId();

        $user = User::where($providerColumn, $providerId)->first();

        if (! $user) {
            $email = $providerUser->getEmail();

            if ($email && User::where('email', $email)->exists()) {
                throw new SocialEmailConflictException;
            }

            $user = User::create([
                'name' => $providerUser->getName() ?: $this->fallbackName($email),
                'email' => $email,
                'password' => null,
                'email_verified_at' => now(),
                $providerColumn => $providerId,
            ]);
        }

        if ($user->isBlocked()) {
            throw new AccountBlockedException;
        }

        return [
            'user' => $user,
            'token' => $user->createToken(self::TOKEN_NAME)->plainTextToken,
        ];
    }

    private function fallbackName(?string $email): string
    {
        return $email ? Str::before($email, '@') : 'User';
    }
}
