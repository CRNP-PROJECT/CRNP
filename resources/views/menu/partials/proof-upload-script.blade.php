<script>
    (function () {
        var panel = document.querySelector('[data-proof-upload]');
        if (!panel) { return; }

        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.getAttribute('content') : '';
        var input = panel.querySelector('[data-proof-input]');
        var attach = panel.querySelector('[data-proof-attach]');
        var feedback = document.getElementById('proof-feedback');
        var spinner = panel.querySelector('[data-proof-spinner]');
        var message = panel.querySelector('[data-proof-message]');
        var amount = document.getElementById('proof-amount');
        var ref = document.getElementById('proof-ref');
        var startUrl = panel.getAttribute('data-start-url');
        var storeUrl = panel.getAttribute('data-store-url');

        // The proof bounds — the same ceiling the server re-encodes to:
        // adaptive JPEG ≤300 KB with a 720px legibility floor.
        var MAX_BYTES = 300 * 1024;
        var MAX_EDGE = 1600;
        var FLOOR_EDGE = 720;
        var START_QUALITY = 0.85;
        var QUALITY_STEP = 0.07;
        var MIN_QUALITY = 0.4;
        var EDGE_STEP = 40;

        var reduceMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function setFeedback(state, text) {
            if (!feedback) { return; }
            feedback.setAttribute('data-state', state);

            if (message) { message.textContent = text || ''; }
            if (spinner) { spinner.hidden = state !== 'uploading'; }
        }

        function busy() {
            if (attach) { attach.disabled = true; }
            setFeedback('uploading', reduceMotion ? 'Uploading…' : 'Uploading your screenshot…');
        }

        function idle() {
            if (attach) { attach.disabled = false; }
        }

        // Compress to the ceiling on a canvas before upload — a 3 MB
        // screenshot never leaves the phone.
        function compress(file, done) {
            var image = new Image();
            var objectUrl = URL.createObjectURL(file);

            image.onload = function () {
                URL.revokeObjectURL(objectUrl);

                var sourceLong = Math.max(image.width, image.height);
                var edge = Math.min(sourceLong, MAX_EDGE);
                if (edge < FLOOR_EDGE) { edge = Math.min(FLOOR_EDGE, sourceLong); }

                var canvas = document.createElement('canvas');
                var context = canvas.getContext('2d');
                var result = null;

                while (true) {
                    var scale = edge / sourceLong;
                    var width = Math.max(1, Math.round(image.width * scale));
                    var height = Math.max(1, Math.round(image.height * scale));
                    canvas.width = width;
                    canvas.height = height;

                    // JPEG has no alpha; flatten onto white first.
                    context.fillStyle = '#ffffff';
                    context.fillRect(0, 0, width, height);
                    context.drawImage(image, 0, 0, width, height);

                    var quality = START_QUALITY;
                    var dataUrl = '';

                    do {
                        dataUrl = canvas.toDataURL('image/jpeg', quality);
                        // base64 is ~4/3 of the byte size.
                        if (dataUrl.length * 0.75 <= MAX_BYTES) { break; }
                        quality -= QUALITY_STEP;
                    } while (quality >= MIN_QUALITY);

                    result = dataUrl;

                    if (dataUrl.length * 0.75 <= MAX_BYTES || edge <= FLOOR_EDGE) {
                        break;
                    }

                    edge = Math.max(FLOOR_EDGE, edge - EDGE_STEP);
                }

                done(result);
            };

            image.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                done(null);
            };

            image.src = objectUrl;
        }

        function dataUrlToBlob(dataUrl) {
            var parts = dataUrl.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var binary = atob(parts[1]);
            var bytes = new Uint8Array(binary.length);

            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            return new Blob([bytes], { type: mime });
        }

        // A request sent with no terminal response counts as in flight: the
        // marker is persisted the instant the upload begins, so the expiry /
        // lapse sweep holds the order or booking across the payment deadline.
        function markInFlight() {
            return fetch(startUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            }).catch(function () { /* a blip on the marker must not block the upload */ });
        }

        function send(file, attempt) {
            compress(file, function (dataUrl) {
                if (!dataUrl) {
                    fail();
                    return;
                }

                var body = new FormData();
                body.append('image', dataUrlToBlob(dataUrl), 'proof.jpg');
                body.append('amount', amount ? amount.value : '');
                body.append('ref', ref ? ref.value : '');

                fetch(storeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: body,
                }).then(function (response) {
                    if (response.ok) {
                        idle();
                        setFeedback('received', 'Screenshot received — checking your payment…');
                        return;
                    }

                    throw new Error('upload failed');
                }).catch(function () {
                    // One automatic retry, then the manual "tap to retry".
                    if (attempt < 1) {
                        send(file, attempt + 1);
                        return;
                    }

                    fail();
                });
            });
        }

        function fail() {
            idle();
            setFeedback('failed', 'Upload failed — tap to retry');
        }

        function begin() {
            if (!input || !input.files || input.files.length === 0) { return; }

            busy();
            markInFlight();
            send(input.files[0], 0);
        }

        if (attach && input) {
            attach.addEventListener('click', function () { input.click(); });
            input.addEventListener('change', begin);
        }

        if (feedback) {
            feedback.addEventListener('click', function () {
                if (feedback.getAttribute('data-state') === 'failed') { begin(); }
            });
        }
    })();
</script>
