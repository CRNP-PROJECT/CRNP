# CODING_STANDARD.md

Behavioral coding rules for the agent, applying to any language, framework, or repo.
Where a more specific rule exists it wins; §7 carries the rules specific to this
project's stack.

## 1. Change discipline

- Code the task does not require is left untouched.
- New code follows the surrounding conventions for naming, structure, error handling, and
  formatting.

## 2. Correctness

- Every fallible call's result is used, or explicitly discarded with a stated reason.
- A caught error is either handled or propagated; none is swallowed without a recorded
  reason.
- A failing command, package, or configuration is reported with its actual error, and the
  fix changes the cause rather than routing around it. Workarounds, mock drivers,
  polyfills, and alternative libraries are added only when the task requests them.

## 3. Verification

- After a change, run typecheck, lint, and the tests covering what you touched.
- The reported result names the command run and its outcome.
- A check that could not run is reported as not run with its reason; it is never reported
  as passed.
- The task is done when its acceptance criterion is demonstrably met.

## 4. Tests

- Each test asserts one behavior and is named for that behavior.
- A test's inputs and expected outputs are fixed; results do not depend on wall-clock
  time, network, or execution order.

## 5. Security and data

- Secrets, tokens, and credentials come from the environment or a secret store; none
  appear in source, logs, or committed config.
- External input is validated and encoded at the boundary before use.
- Data access uses parameterized queries and safe APIs; SQL, shell, and paths are never
  built by string interpolation.
- Logs and error messages contain no secrets, personal data, or full request bodies.

## 6. Comments and docs

- A comment explains a reason the code cannot express itself.
- A non-obvious decision is recorded where it is made, linked to its issue or ADR rather
  than restated.

## 7. Stack standards

The stack is chosen in [`docs/PLAN.md`](./docs/PLAN.md) §2, with the data layer in §4;
this section carries only the coding rules those choices imply and that the framework
defaults do not already supply. Authoritative references: the PHP manual and PER Coding
Style 3.1, the Laravel 12 documentation, the Firebase RTDB REST documentation, MDN, and
WCAG 2.2.

**PHP 8.2+ (runtime 8.5)**

- Open every PHP file with `declare(strict_types=1);`, and type every parameter, return,
  and property.
- Write a nullable type as `?T`, never as an implicit-nullable default.
- Model a closed value set as a backed `enum`, an immutable value object as a `readonly`
  class, and multi-way branching as `match`.
- Use a property hook where an accessor only guards a value, and `private(set)`
  visibility where a value is writable only during construction.
- Mark each intentional override `#[\Override]`.
- Format to PER Coding Style 3.1; Pint owns the exact rules.

**Laravel 12**

- Validate at the request boundary in a Form Request, then persist only
  `$request->validated()`.
- Read configuration with `config()`; `env()` appears only inside `config/*.php`.
- Escape output with `{{ }}`; reserve `{!! !!}` for HTML the app generated or sanitized.
- Embed server data in `<script>` with `{{ Js::from(...) }}` or `@js(...)`.
- A controller validates, authorizes, delegates, and responds; the business rule lives in
  an action that takes its inputs as arguments and throws on failure.
- A queued job that carries secrets implements `ShouldBeEncrypted`; one that must not run
  twice at once implements `ShouldBeUnique`.
- The Socialite Google flow runs stateful with `enablePKCE()`; a user is keyed by Google
  `sub`, never by email.

**Firebase Realtime Database**

- Authenticate server access with the service-account token in the
  `Authorization: Bearer` header.
- `PATCH` updates the named children; `PUT` replaces the whole node — choose
  deliberately.
- Check a node key against RTDB's forbidden set (`. $ # [ ] /` and control characters)
  before placing it in a path.

**Blade, CSS, and motion**

- Declare the `@layer` order once at the top of the stylesheet and hold design tokens as
  custom properties on `:root`.
- Animate only `transform` and `opacity`; drive one-off choreography with the Web
  Animations API.
- Honor `prefers-reduced-motion: reduce` in both CSS and script, toning motion down
  rather than removing needed feedback.
- Give auto-playing or auto-updating content a pause/stop control, and keep any
  flashing at or below three times a second (WCAG 2.2 SC 2.2.2, SC 2.3.1).

**Toolchain**

- The CI gate runs `vendor/bin/pint --test`, then `bun run lint:css`, then
  `vendor/bin/phpstan analyse`, then `vendor/bin/pest --ci`. The CSS step is the only
  one that needs `bun`; its rules and the conventions they hold live in
  `stylelint.config.mjs`.
- A test freezes time with `travel()`/`freezeTime()` and releases it with `travelBack()`,
  and blocks stray network calls with `Http::preventStrayRequests()`.
- `--tia` runs locally only, never in the CI test command.
