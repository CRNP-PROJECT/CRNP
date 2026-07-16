# Crop Modal — Polish & Harden Plan

> **For Hermes:** One final push to make the crop modal production-ready.

**Goal:** Fix all remaining crop-box edge cases (bounds, coordinate correctness, touch support) and clean up for commit.

**Architecture:** Client-side canvas crop in a flexbox-centered modal, 1:1 square crop, no dependencies, no GD.

**Tech Stack:** Vanilla JS, CSS flexbox, HTML5 Canvas, PHP 8.5 (base64 decode + file_put_contents).

**References:**
- [Croppr.js](https://github.com/jamesssooi/Croppr.js) — lightweight zero-dep cropper, aspectRatio option, touch device support, handle redraw pattern
- [Cropper.js](https://github.com/fengyuanchen/cropperjs) — full-featured cropper, `viewMode` option (constrain crop box to canvas/image), event model
- [PureJsCropper](https://github.com/shrwn14/PureJsCropper) — drag reposition + dynamic resize, canvas-based crop
- `box-shadow: 0 0 0 9999px` — established CSS-only overlay technique (Codrops, CSS-Tricks); no JS overlay needed
- `getBoundingClientRect()` over `offsetTop` — confirmed fix for flex-centered elements (offsetTop returns 0 in flex context)

---

## Known Issues (Current Code Review)

| # | Issue | Root Cause | Status |
|---|-------|-----------|--------|
| 1 | Box invisible on open | `img.onload` reads `offsetWidth/Height` while modal `display:none` → 0 dimensions | **Fixed** — modal shown before onload |
| 2 | Box resizes to rectangle | No aspect ratio enforcement after direction-specific resize | **Fixed** — `if (w !== start.ow) h = w` post-clamp |
| 3 | Box moves outside image | Constraint used `0` instead of image offset within flex-centered stage | **Fixed** — `getBoundingClientRect`-based clamp |
| 4 | Move first-pass constraint uses `dw/dh` not image-offset coords | `start.ox` includes 1px border offset; vertical image offset not accounted | **Half-fixed** — final clamp corrects it, but first pass conflicting |
| 5 | No touch events | Only `mousedown/mousemove/mouseup` handlers | **Open** — mobile breakpoint exists but touch doesn't work |
| 6 | Empty initial state when crop-skipped | `Apply` populates `croppedDataUrl`, but no guard for skipped crop | **Open** — submit handler sends `cropped` only when set |
| 7 | Stage border (1px) hard-coded in clamp | `ir.left - sr.left - 1` magic number | **Open** — minor, breaks if border changes |
| 8 | No visual resize handles (8 dots) | User only sees cursor change on edges | **Open** — works but not discoverable |

---

## Task 1: Fix move first-pass constraint to use image-space coords

**Objective:** Eliminate the conflicting first-pass move constraint that uses `[0, dw]` instead of image offset.

**Files:**
- Modify: `admin/products.php:366-367`

**Step 1: Read current code**

Current move constraint:
```js
x = Math.max(0, Math.min(start.ox + dx, dw - start.ow));
y = Math.max(0, Math.min(start.oy + dy, dh - start.oh));
```

This uses `0` (stage left) as the minimum, but the image is centered and starts at `ix` > 0. The final clamp (line 389-390) corrects it, but during the move the box can visually jump.

**Step 2: Replace with image-aware constraint**

```js
// First-pass using image bounds (final clamp at line 389 still runs)
var ir = img.getBoundingClientRect(), sr = stage.getBoundingClientRect();
x = Math.max(ir.left - sr.left - 1, Math.min(start.ox + dx, ir.right - sr.left - 1 - start.ow));
y = Math.max(ir.top - sr.top - 1, Math.min(start.oy + dy, ir.bottom - sr.top - 1 - start.oh));
```

Or simpler: just delete the first-pass constraints and rely entirely on the final clamp (line 389-390):

```js
if (mode === 'move') {
  x = start.ox + dx;
  y = start.oy + dy;
}
```

This is the ponytail approach — one less place for the constraint to be wrong. The final clamp handles everything.

**Step 3: Verify**
- PHP lint: `php -l admin/products.php` → no errors
- Trace: box dragged left in a centered image → starts at x≥ix, not 0

---

## Task 2: Add touch events for mobile

**Objective:** The mobile breakpoint (`≤540px`) makes the modal full-screen, but touch gestures don't trigger `mousedown/mousemove/mouseup`. Add touch equivalents.

**Files:**
- Modify: `admin/products.php` (add touch handlers alongside mouse handlers)

**Step 1: Add touch mousedown equivalent**

In the `box.addEventListener('mousedown', …)`, also listen for `touchstart`:

```js
function pointerStart(e) {
  if (e.button) return;
  var pt = e.touches ? e.touches[0] : e;
  dn = true; dir = getDir(pt); mode = dir ? 'resize' : 'move';
  var r = box.getBoundingClientRect(), pr = stage.getBoundingClientRect();
  start = { mx: pt.clientX, my: pt.clientY, ox: r.left - pr.left, oy: r.top - pr.top, ow: r.width, oh: r.height };
  e.preventDefault();
}
box.addEventListener('mousedown', pointerStart);
box.addEventListener('touchstart', pointerStart, { passive: false });
```

**Step 2: Add touch mousemove equivalent**

In the `document.addEventListener('mousemove', …)`, also listen for `touchmove`:

```js
function pointerMove(e) {
  var pt = e.touches ? e.touches[0] : e;
  // … rest of existing code using pt instead of e …
}
document.addEventListener('mousemove', pointerMove);
document.addEventListener('touchmove', pointerMove, { passive: false });
```

**Step 3: Add touch mouseup equivalent**

```js
document.addEventListener('mouseup', function() { dn = false; mode = null; dir = null; });
document.addEventListener('touchend', function() { dn = false; mode = null; dir = null; });
```

**Verification:**
- Open Chrome DevTools → Device Toolbar → select a mobile device
- Select an image → modal opens full-screen
- Touch-drag the crop box → moves within image bounds
- Touch the edge → cursor-appropriate resize → box stays square

---

## Task 3: Add visual resize handles

**Objective:** Currently the crop box only changes cursor on edges (no visible handles). Add 8 small white squares at the corners and midpoints for discoverability.

**Files:**
- Modify: `admin/products.php` (CSS + HTML)

**Step 1: Add handle HTML inside `.crop-box`**

The handles are absolutely positioned inside the crop box. Each is a 10×10 white square with a subtle border.

Using CSS pseudo-elements would be cleaner than adding 8 elements. But pseudo-elements can only create 2 per element. So add one container div with 8 span children:

```html
<div id="crop-box" class="crop-box">
  <div class="crop-handles">
    <span class="ch nw"></span><span class="ch n"></span><span class="ch ne"></span>
    <span class="ch w"></span><span class="ch e"></span>
    <span class="ch sw"></span><span class="ch s"></span><span class="ch se"></span>
  </div>
</div>
```

**Step 2: Add handle CSS**

```css
.crop-handles { position:absolute; inset:-5px; pointer-events:none; }
.crop-handles .ch { position:absolute; width:10px; height:10px; background:#fff; border:1px solid rgba(0,0,0,.3); border-radius:2px; }
.ch.nw { top:0; left:0; } .ch.n { top:0; left:50%; margin-left:-5px; } .ch.ne { top:0; right:0; }
.ch.w { top:50%; left:0; margin-top:-5px; } .ch.e { top:50%; right:0; margin-top:-5px; }
.ch.sw { bottom:0; left:0; } .ch.s { bottom:0; left:50%; margin-left:-5px; } .ch.se { bottom:0; right:0; }
```

`pointer-events:none` on the container ensures clicks pass through to the crop box (which handles its own edge detection via `getDir`).

**Ponytail skip:** This is pure UI polish — skip if user doesn't care about visual handles. The functional edge-detection via cursor change already works.

---

## Task 4: Pull stage border width from computed style instead of magic number

**Objective:** Replace `-1` (hardcoded stage border) with dynamic read.

**Files:**
- Modify: `admin/products.php:388, 390-391`

**Step 1: Read border once on init**

In the IIFE, add:
```js
var BS = 0; // border size, set after DOM ready
```

After all variables (line 290), compute border from stage's computed style:
```js
(function() {
  var inp = …, box = …, stage = …, img = …, modal = …;
  if (!inp || !modal || !stage || !img || !box) return;
  var dn = false, mode = null, dir = null, start = {}, croppedDataUrl = null;
  var MIN = 30, EDGE = 10;
  var BS = parseInt(getComputedStyle(stage).borderTopWidth) || 1;
```

**Step 2: Use BS instead of 1**

In the final clamp:
```js
var ix = ir.left - sr.left - BS, iy = ir.top - sr.top - BS;
```

**Verification:** Change stage border to 2px in devtools → box still constrains correctly.

**Ponytail note:** This is a durability fix for a value that won't change — skip unless other border values are planned.

---

## Task 5: Final code cleanup & commit

**Objective:** Run linter, remove dead code, verify all paths, commit.

**Files:**
- Modify: `admin/products.php`

**Step 1: Remove stale move constraints**

If task 1 chose the simple approach, verify the move handler is clean:
```js
if (mode === 'move') {
  x = start.ox + dx;
  y = start.oy + dy;
}
```

**Step 2: Verify PHP handler accepts missing `cropped` gracefully**

Current PHP (line 66-72) already guards with `if ($cropped)`. No change needed.

**Step 3: PHP lint + final review**

```bash
php -l admin/products.php
```

**Step 4: Commit**

```bash
git add admin/products.php
git commit -m "feat: crop modal with 1:1 square, touch support, image-bounds clamp"
```

---

## Verification Checklist

Run after all tasks:

1. **Select image** → modal opens at 480×580 (or fullscreen mobile), image centered, 200×200 white crop box centered
2. **Drag box** → constrained to image area (no letterbox overlap)
3. **Resize from edge** → box stays 1:1 square
4. **Resize from corner** → box stays 1:1 square
5. **Apply** → cropped image appears in preview, modal closes
6. **Cancel** → modal closes, preview unchanged, input cleared
7. **Mobile** (Chrome DevTools mobile mode) → modal full-screen, touch drag/resize works
8. **Submit form** → cropped image saved to `assets/img/products/` as JPG
9. **No crop selected** → form submits normally (no `cropped` field sent)
10. **PHP lint**: `php -l admin/products.php` → no errors

---

## Risks & Open Questions

- **Touch event passive listener**: `touchstart`/`touchmove` need `{ passive: false }` to call `preventDefault()` — Chrome requires this to prevent scroll while dragging on mobile
- **Image load timing**: `URL.createObjectURL(f)` loads async — `img.onload` fires correctly since set before `src` (current code) but what if a tiny file loads before JS sets the handler? Order: `modal.style.display = ''; img.onload = ...; prev.src = img.src = URL.createObjectURL(f);` — the `onload` is set BEFORE `src`, so handler catches the load. Safe.
- **Canvas crop quality**: `toDataURL('image/jpeg', 0.85)` — 0.85 is a reasonable default but may be lossy for text-heavy images. Add quality control if users complain.
- **Browser compatibility**: `getBoundingClientRect` is IE9+/all modern. `getComputedStyle` is IE9+/all modern. `URL.createObjectURL` is IE10+/all modern. No polyfills needed.
