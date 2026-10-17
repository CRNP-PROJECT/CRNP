<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\GoogleIdentity;
use App\Auth\RtdbUser;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Turns a Google sign-in into the `users/{uid}` record and the signed-in
 * customer, creating the node on a first sign-in and reusing it thereafter.
 *
 * The key is the Google `sub`, never the email: one Google identity is one
 * customer node for life, and a changed email address cannot fork an account.
 */
final readonly class ProvisionCustomer
{
    public function __construct(private RtdbClient $client) {}

    public function __invoke(GoogleIdentity $identity): RtdbUser
    {
        $sub = trim($identity->sub);

        if ($sub === '') {
            throw new InvalidArgumentException('A Google identity must carry a `sub`.');
        }

        $existing = $this->client->get("users/{$sub}");

        if (! is_array($existing)) {
            $this->client->set("users/{$sub}", [
                'sub' => $sub,
                'email' => $identity->email,
                'name' => $identity->name,
                'avatar' => $identity->avatar,
                'createdAt' => now('UTC')->toIso8601ZuluString(),
            ]);

            return new RtdbUser($sub, $identity->email, $identity->name, $identity->avatar);
        }

        return RtdbUser::fromNode($sub, $existing);
    }
}
