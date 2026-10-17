<?php

declare(strict_types=1);

namespace App\Staff\Actions;

use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffDirectory;

/**
 * Deletes a staff account and evicts its cached role, so the removed account is
 * refused at `/cashier` and `/admin` on its next request.
 */
final readonly class DeleteStaff
{
    public function __construct(
        private RtdbClient $client,
        private StaffDirectory $directory,
    ) {}

    public function __invoke(string $id): void
    {
        $this->client->delete("staff/{$id}");

        $this->directory->evict($id);
    }
}
