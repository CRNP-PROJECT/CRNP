<?php

declare(strict_types=1);

namespace App\Staff\Actions;

use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use InvalidArgumentException;

/**
 * Changes a staff member's role (create/demote) and evicts the cached role so
 * the change is enforced on that account's next request, not at logout.
 */
final readonly class SetStaffRole
{
    public function __construct(
        private RtdbClient $client,
        private StaffDirectory $directory,
    ) {}

    public function __invoke(string $id, StaffRole $role): void
    {
        if (! is_array($this->client->get("staff/{$id}"))) {
            throw new InvalidArgumentException("No staff member with id [{$id}].");
        }

        $this->client->update("staff/{$id}", ['role' => $role->value]);

        $this->directory->evict($id);
    }
}
