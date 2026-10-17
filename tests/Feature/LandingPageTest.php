<?php

declare(strict_types=1);

it('serves the landing page without a build step', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Crates N&#039; Plates', false)
        ->assertSee(asset('css/app.css'), false)
        ->assertSee(asset('images/logo.png'), false)
        ->assertDontSee('@vite', false);
});

it('serves the plain stylesheet from the public directory', function (): void {
    $css = file_get_contents(public_path('css/app.css'));

    expect($css)->toBeString()
        ->and($css)->toContain('--color-brand')
        ->and($css)->not->toContain('@tailwind');
});
