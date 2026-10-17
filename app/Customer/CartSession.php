<?php

declare(strict_types=1);

namespace App\Customer;

use App\Orders\Cart;
use Illuminate\Contracts\Session\Session;

/**
 * The customer's cart selection held in the session between requests.
 *
 * The session stores only `productId => {quantity, addOns[]}` — never a price.
 * The cart is rebuilt against the current catalog on every request
 * ({@see Storefront}), so the price, promo and add-on figures the customer
 * sees, the order total the server persists, and the receipt all agree. Keeping
 * the selection in the session means the inline steppers survive a refresh
 * without an edit form.
 */
final readonly class CartSession
{
    /**
     * The session key the selection lives under.
     */
    public const KEY = 'cart';

    public function __construct(private Session $session) {}

    /**
     * Build the cart from the session selection and the current menu.
     */
    public function cart(Storefront $storefront): Cart
    {
        return Cart::fromInput($this->session->get(self::KEY), $storefront->products());
    }

    /**
     * Set a product's quantity and add-on picks, replacing its row. A quantity
     * below one removes the row, so the stepper's `−` can empty the cart.
     *
     * @param  list<string>  $addOns
     */
    public function put(string $productId, int $quantity, array $addOns = []): void
    {
        $selection = $this->raw();

        if ($quantity < 1) {
            unset($selection[$productId]);
        } else {
            $selection[$productId] = [
                'quantity' => min(99, $quantity),
                'addOns' => $addOns,
            ];
        }

        $this->session->put(self::KEY, $selection);
    }

    /**
     * Forget the selection — after an order is placed, the cart is spent.
     */
    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(): array
    {
        $raw = $this->session->get(self::KEY);

        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
