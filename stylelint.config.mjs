/** @type { import('stylelint').Config } */
export default {
  extends: ['stylelint-config-standard'],
  rules: {
    // The stylesheet is BEM: a kebab-case block, an optional `__element`, and
    // an optional `--modifier` (e.g. `.kitchen-card__clock--age`). The standard
    // config's default pattern rejects `__` / `--`, so it is widened here
    // rather than rewriting every selector.
    'selector-class-pattern':
      '^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:__[a-z0-9]+(?:-[a-z0-9]+)*)?(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?$',

    // A font-family token lists proper nouns (`Roboto`), which the rule would
    // lowercase as if they were CSS keywords. Scope the exemption to the font
    // tokens so every other value keeps the case check.
    'value-keyword-case': ['lower', { ignoreProperties: ['/^--font-/'] }],

    // Every colour token is declared in 6-digit hex in DESIGN.md (the design
    // system's source of truth) and only `#ffffff` is shortenable, so the file
    // stays uniformly long rather than mixing 3- and 6-digit hexes.
    'color-hex-length': 'long',

    // The stylesheet already uses prefix notation (`min-width`) throughout, and
    // range notation (`width >=`) is unsupported on Safari 15.4–16.3 — a band
    // where `@layer` (already in use) works but the range query would be
    // dropped, silently losing the two-column layouts. Keep the convention.
    'media-feature-range-notation': 'prefix',
  },
};
