<?php

declare(strict_types=1);

namespace App\Staff\Actions;

use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use InvalidArgumentException;

/**
 * Creates a staff account from an admin-supplied name + Google email.
 *
 * There is no public staff signup: this action is the only way a cashier or a
 * second admin is added. The row is keyed by the email-derived staff id and the
 * account's cached role is evicted so a freshly granted role takes effect on the
 * account's next request.
 */
final readonly class CreateStaff
{
    public function __construct(
        private RtdbClient $client,
        private StaffDirectory $directory,
    ) {}

    public function __invoke(string $name, string $email, StaffRole $role): string
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            throw new InvalidArgumentException('A staff member needs a name.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("The email [{$email}] is not valid.");
        }

        $id = StaffDirectory::idForEmail($email);

        if (is_array($this->client->get("staff/{$id}"))) {
            throw new InvalidArgumentException("A staff member with the email [{$email}] already exists.");
        }

        $this->client->set("staff/{$id}", [
            'name' => $name,
            'email' => $email,
            'role' => $role->value,
        ]);

        // A fresh account may already be signed in as a customer; drop any
        // cached "not staff" read so its role is re-resolved on the next request.
        $this->directory->evict($id);

        return $id;
    }
}
