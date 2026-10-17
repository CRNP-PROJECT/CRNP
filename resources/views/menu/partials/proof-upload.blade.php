{{--
    The proof-of-payment surface (#24), shared by the order tracker and the
    rental tracker so both use the SAME GCash gate: one Attach button opens the
    native picker on the newest image, the selection auto-uploads with no Submit,
    the amount prefills with the total — edit only what differs — and the GCash
    ref# is typed. A failure shows "Upload failed — tap to retry" plus one
    automatic retry, and the upload is marked in flight the instant it begins so
    it holds across the payment deadline. The image is compressed client-side
    (adaptive JPEG, ≤300 KB, ≥720 px floor) and re-encoded server-side to the
    same ceiling.

    Expects: $proofStartUrl, $proofStoreUrl, $proofTotal, and the shared script
    partial below.
--}}
<section
    class="proof"
    data-proof-upload
    data-start-url="{{ $proofStartUrl }}"
    data-store-url="{{ $proofStoreUrl }}"
    aria-labelledby="proof-heading"
>
    <h2 class="proof__title" id="proof-heading">Proof of payment</h2>

    <input
        class="visually-hidden"
        type="file"
        id="proof-input"
        accept="image/*"
        data-proof-input
    >

    <button class="primary-button proof__attach" type="button" data-proof-attach>
        Attach payment screenshot
    </button>

    <p class="proof__feedback" id="proof-feedback" role="status" aria-live="polite" data-state="idle">
        <span class="proof__spinner" data-proof-spinner aria-hidden="true" hidden></span>
        <span data-proof-message></span>
    </p>

    <label class="field proof__field">
        <span class="field__label">Amount paid</span>
        <input
            class="field__input"
            type="number"
            name="amount"
            id="proof-amount"
            inputmode="decimal"
            step="0.01"
            min="0"
            value="{{ number_format($proofTotal, 2, '.', '') }}"
        >
    </label>

    <label class="field proof__field">
        <span class="field__label">GCash ref#</span>
        <input
            class="field__input"
            type="text"
            name="ref"
            id="proof-ref"
            inputmode="numeric"
            autocomplete="off"
            maxlength="64"
        >
    </label>
</section>
