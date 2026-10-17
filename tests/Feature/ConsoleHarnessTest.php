<?php

declare(strict_types=1);

use App\Rtdb\Contracts\RtdbClient;

it('drives an artisan command that persists through RtdbClient', function (): void {
    $this->artisan('rtdb:probe', ['path' => 'probe/t1', '--value' => 'hello'])
        ->assertSuccessful();

    expect(app(RtdbClient::class)->get('probe/t1'))
        ->toMatchArray(['value' => 'hello']);
});
