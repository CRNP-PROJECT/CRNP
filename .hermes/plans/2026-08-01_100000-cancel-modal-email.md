# Cancel modal + email check plan

**Goal:** Confirm email-sending behaviour and fix the cancel reason UX.

---

## Finding: email on accept vs mark_paid

Traced `cashier/index.php:64-70` (accept) and `:92-105` (mark_paid). **Accept does NOT send a receipt.** Only `mark_paid` calls `sendOrderReceipt()`. **No fix needed** — the guard `if ($email !== '' && $email !== 'walk-in')` already ensures only paid orders with a real email get a receipt.

---

## Changes

### 1. Hide cancel-note input, show prompt on cancel

`cashier/index.php:346` — the `<input name="cancel_note">` is currently visible inline. Hide it and show a native `prompt()` when Cancel is clicked.

**Modify:** `cashier/index.php` — two changes:
- Add `style="display:none"` to the cancel_note input (line 346)
- Add a submit handler on the cancel form that `prompt()`s for the reason

### 2. Verify php -l after edit

```bash
php -l cashier/index.php
```
