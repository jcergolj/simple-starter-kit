# Bugs, Security, and Code Improvements

Reviewed: 2026-09-09. Scope: current working tree, including application PHP, routes, authentication, Blade/Stimulus, configuration, provisioning scripts, and deployment configuration. Installed versions: PHP 8.4.25, Laravel 13.26.1, Fortify 1.38.0, PHPUnit 13.3.1.

Report only, as requested. No application code or dependency changes were made. Existing edits to Composer files, deployment configuration, the workflow, and phpmd.xml were preserved. Line references describe the reviewed working tree.

This is a source review with targeted existing tests, not a penetration test or a guarantee that all vulnerabilities have been found. Findings below are source-confirmed unless explicitly marked conditional; proposed regression scenarios have not been implemented or executed.

## Priority Order

1. Protect recovery-email changes and revoke compromised sessions after credential changes.
2. Throttle authenticated password checks and restrict private database permissions.
3. Repair provisioning, database backups, and OTP submission.
4. Correct invitation and email-verification lifecycles.
5. Address remaining operational bugs, accessibility, and hardening.

## Security Findings

### S1. High: An authenticated session can replace the recovery email without reauthentication

**Evidence:** `routes/web.php:51-58`, `app/Http/Requests/Settings/UpdateProfileRequest.php:13-25`, `app/Http/Controllers/Settings/ProfileController.php:23-33`.

Profile updates accept a new email without the current password or recent password confirmation. That email immediately becomes the password-reset destination. An attacker with a stolen verified session can change the address, request a reset from another browser, and set a password. Clearing email verification does not prevent password recovery. This enables persistent takeover of accounts without 2FA; it does not independently bypass an enabled second factor.

**Fix instructions:** Require reauthentication specifically for email changes. Prefer storing a pending replacement address and retaining the existing login/recovery email until ownership of the replacement is verified. Bind confirmation to the intended address and expire it. Notify the old address of the security change. Keep ordinary name updates separate from sensitive changes.

**Regression tests:** Extend `tests/Feature/Http/Controllers/Settings/ProfileControllerTest.php`. Verify missing/wrong current passwords cannot change the address; a pending address cannot receive a reset for the account; correct reauthentication and valid confirmation complete the change. Existing tests currently permit email updates without reauthentication.

### S2. High: Password changes and resets do not revoke existing authenticated sessions

**Evidence:** `bootstrap/app.php:19-30`, `app/Http/Controllers/Settings/PasswordController.php:16-20`, `app/Actions/Fortify/ResetUserPassword.php:15-23`.

There is no `auth.session` middleware or explicit session revocation. The session guard retrieves the user by ID, so another logged-in browser remains authenticated after a password change/reset. Fortify's reset completion rotates the remember token, but that is not equivalent to revoking authenticated sessions. The custom password-change path also does not rotate the remember token.

**Fix instructions:** Apply Laravel's authenticated-session checking consistently to authenticated web access, including Fortify endpoints. Implement and test other-device logout for password changes and all-existing-session revocation for recovery. Rotate remember tokens on credential changes. Decide how to revoke pre-existing sessions that lack the password hash used by `AuthenticateSession`; simply enabling middleware is not a complete migration strategy. Do not assume deleting database session rows covers every supported session driver.

**Regression tests:** Extend the password-change and password-reset controller tests. Use independent real session cookies: change the password in browser A and verify browser B loses access; reset from browser C and verify existing sessions lose access. Test old remember-me cookies. `actingAs()` alone does not prove this behavior.

### S3. Medium: Authenticated password checks bypass the login attempt limit

**Evidence:** `routes/web.php:54-58`, `app/Http/Requests/Settings/UpdatePasswordRequest.php:14-16`, `vendor/laravel/fortify/routes/routes.php:128-130`, `vendor/laravel/fortify/src/Actions/ConfirmPassword.php:18-25`.

Password confirmation, password updates, and account deletion validate a password without a shared throttle. A session thief can repeatedly guess the current password without encountering the login limiter. Successful password confirmation can unlock sensitive 2FA management. This requires an authenticated session; it is not an unauthenticated login bypass.

**Fix instructions:** Add a shared, account-scoped failed-password-attempt limiter to all password-checking endpoints, including Fortify's confirmation route. Check the limit before expensive password hashing and record failures consistently. Add IP-based protection as a supplementary control, not a replacement for account scoping. Avoid separate limits that allow switching endpoints to multiply guesses.

**Regression tests:** Exhaust attempts across confirmation, update, and deletion routes. Subsequent attempts should receive 429 without setting the password-confirmation timestamp or mutating the account. Verify other accounts remain usable.

### S4. Medium: Provisioned SQLite and private shared files are readable by unrelated local users

**Evidence:** `scripts/steps/05-database.sh:6-9`, `scripts/steps/06-permissions.sh:4-7`.

The scripts create shared directories with mode 2775 and files with mode 664, including the SQLite database. Where parent directories are traversable, unrelated OS users can read password hashes, session data, and application records. The recursive permissions step reapplies these broad permissions to existing shared files. This is a local confidentiality issue, not demonstrated remote web access.

**Fix instructions:** Restrict the database and private storage to the deployment owner and required web-server group, typically directories 2770 and files 660. Keep secrets more restrictive where possible. Separate deliberately public assets from private storage instead of applying one recursive mode to everything. Ensure subsequent deployments preserve the restrictions.

**Regression tests:** On a sandbox host, verify an unrelated OS account cannot read the database/private storage while deployment and web users retain necessary access. Repeat after rerunning bootstrap and deployment.

## Functional and Operational Bugs

### B1. High: Database provisioning constructs nonexistent PHP extension package names

**Evidence:** `scripts/server-bootstrap.sh:12-15`, `scripts/steps/05-database.sh:5,15`.

For a service named `php8.4-fpm`, `${PHP_FPM_SERVICE/php/php}-sqlite3` produces `php8.4-fpm-sqlite3`; the MySQL branch similarly produces `php8.4-fpm-mysql`. Standard Debian/Ubuntu extension packages omit `-fpm`, so either branch fails.

**Fix instructions:** Derive the PHP package prefix by stripping the `-fpm` suffix, for example `${PHP_FPM_SERVICE%-fpm}`, then append `-sqlite3` or `-mysql`. Validate the detected service and align CLI/FPM versions.

**Regression tests:** Mock `apt-get` in shell tests; cover both database options and multiple PHP versions, asserting exact arguments. Do not run privileged provisioning on the development machine to test this.

### B2. High: MySQL installations are configured to back up SQLite instead

**Evidence:** `config/backup.php:93-95`, `scripts/lib/common.sh:137-142`, `config/database.php:35-38`, `routes/console.php:11`.

Backup configuration hardcodes the `sqlite` connection although bootstrap supports MySQL. With MySQL selected, the backup does not target the live database. The SQLite connection also receives the MySQL database name through `DB_DATABASE`, generally causing the dump to fail.

**Fix instructions:** Make the backup connection explicit and consistent with the selected application database. If using an environment override, read it only in configuration. Install the matching dump utility. Check scheduled backup failure notifications rather than assuming a scheduled command proves recoverability.

**Regression tests:** Verify resolved backup connection for SQLite and MySQL, including cached configuration. In an isolated environment, back up a known record, restore it, and verify its contents.

### B3. Medium: Cloudflare DNS creation calls an undefined function

**Evidence:** `scripts/steps/02-cloudflare.sh:36`, `scripts/server-bootstrap.sh:4-10`, `scripts/lib/common.sh:15-17,58`.

The new-record path calls `step`, but the bootstrap sources do not define it. A similarly named helper exists only in the separate deploy script. Selecting Cloudflare with no existing A record aborts before the creation request.

**Fix instructions:** Replace the call with an existing common logging helper or define one shared helper in the common library. Do not source an executable deployment script merely to obtain its logger.

**Regression tests:** Mock zone lookup and an empty DNS-record response; assert the POST is made and bootstrap continues. Also test existing records and API errors.

### B4. Medium: Visible OTP digits can differ from the submitted code

**Evidence:** `resources/js/controllers/otp_controller.js:51-53,95-99`, `resources/views/components/form/otp-input.blade.php:14-23`.

The `input` handler sanitizes visible digits but does not synchronize the hidden submitted code. Keyboard handling delays synchronization by 100 ms. Input-only mobile/autofill changes can submit an empty/stale value, and immediate desktop submission can race the delayed update.

**Fix instructions:** Synchronize the hidden code immediately on the canonical input event and whenever keyboard handlers set values programmatically. Remove the delayed dependency. Handle multi-digit paste/autofill, replacement, and deletion explicitly. Consider a single accessible code field if segmented inputs add no necessary value.

**Regression tests:** Browser tests must inspect submitted `FormData`, not just visible fields. Cover input events without keydown, submission immediately after the final digit, paste, deletion, and autofill. Existing PHP tests that post codes directly do not cover this bug.

### B5. Medium: Invitation validation and database uniqueness disagree

**Evidence:** `app/Http/Requests/SendInvitationRequest.php:16-22`, `app/Http/Controllers/InvitationController.php:18,25,34-38`, `database/migrations/2026_03_14_000000_create_invitations_table.php:11-17`.

Expired, unaccepted invitations block another invitation because validation checks only `accepted_at`. They are absent from the pending list and cannot be revoked through the pending-only delete path. Accepted invitations pass invitation-email validation, but the unconditional unique email index rejects inserting a replacement. The latter occurs when the old user has been deleted or changed email.

**Fix instructions:** Choose one lifecycle. The smallest option is reusing the existing email's invitation row, rotating the token/expiry and clearing acceptance, while still rejecting an existing user. Alternatively, preserve history and redesign uniqueness deliberately. Permit expired-record replacement or cleanup. Make old links invalid after replacement and handle concurrent sends consistently.

**Regression tests:** Extend `InvitationControllerTest.php` for expired replacement and accepted-email reuse after user deletion/email change. Assert successful persistence and invalidation of the previous token. The accepted-email Form Request unit test does not exercise the failing insert.

### B6. Medium: Mixed-case email storage can make accounts inaccessible on SQLite

**Evidence:** `app/Http/Requests/SendInvitationRequest.php:16-22`, `app/Http/Requests/UpdateUserRequest.php:15-21`, `app/Http/Controllers/AcceptInvitationController.php:33-39`, `app/Console/Commands/CreateUserCommand.php:60-64,97-114`, `config/fortify.php:65`.

Invitation/admin/CLI paths can store mixed-case emails, while Fortify lowercases login and reset input. The default SQLite comparison is case-sensitive: a stored `Alice@example.com` does not match `alice@example.com`. This is collation-dependent; a case-insensitive MySQL deployment may mask it.

**Fix instructions:** Normalize emails consistently before uniqueness validation and persistence in every entry point. Before normalizing existing data, detect case-colliding accounts/invitations and resolve them explicitly. Keep storage and authentication lookup conventions aligned.

**Regression tests:** On SQLite, exercise mixed-case invitation acceptance, CLI creation, and admin changes. Assert canonical storage and working login/password recovery using either capitalization. Test collisions rather than silently merging accounts.

### B7. Medium: Admin email changes retain verification of the old address

**Evidence:** `app/Http/Controllers/UserController.php:29-33`, `app/Http/Requests/UpdateUserRequest.php:15-21`.

Admin updates replace email without clearing `email_verified_at`, unlike the profile controller. The account retains verified access for an address whose ownership has not been demonstrated. An authorized admin action is required; this is not regular-user privilege escalation.

**Fix instructions:** Invalidate verification only when the canonical email actually changes, and initiate replacement verification. Prefer the same pending-email rules as self-service changes. If admins are intentionally allowed to attest ownership, make that an explicit separate capability rather than an incidental side effect of editing text.

**Regression tests:** Changed email must lose verified access and receive the intended notification; unchanged email must preserve verification.

### B8. Medium: A mistyped profile email prevents the user correcting it

**Evidence:** `app/Http/Controllers/Settings/ProfileController.php:27-37`, `routes/web.php:51-57`, `resources/views/auth/verify-email.blade.php:11-26`.

Updating email clears verification, but both profile edit and update require verified status. A syntactically valid typo sends the user to a verification page with only resend/logout options. The controller also does not send verification mail even though the page says mail was just sent.

**Fix instructions:** Prefer pending-email confirmation that preserves the existing address. Otherwise, permit a narrowly scoped authenticated, reauthenticated email-correction flow for unverified users. Send verification mail when initiating the change and make status text reflect actual delivery attempts. Do not remove verification from all settings indiscriminately.

**Regression tests:** Change to an inaccessible address and follow the real redirect chain; the user must still be able to correct it securely. Assert the notification recipient and unchanged-email behavior.

### B9. Medium: First deployment does not activate the generated Supervisor program

**Evidence:** `scripts/steps/09-workers.sh:37-43`, `scripts/server-bootstrap.sh:117-119`, `scripts/steps/10-deployer-instructions.sh:13-26,46-48`.

Bootstrap writes the worker configuration but skips `supervisorctl reread`/`update` when `current/artisan` does not yet exist. The first-deployment instructions do not subsequently activate it. Restarting queue workers or terminating Horizon does not register an unknown Supervisor program.

**Fix instructions:** Add an explicit activation step after the first release and `current` symlink exist. Run Supervisor reread/update and verify the expected program is running. Keep activation idempotent.

**Regression tests:** Provision and deploy a sandbox from no `current` symlink; verify the worker runs and processes a sentinel job.

### B10. Medium: Queue timeout equals the default retry interval

**Evidence:** `scripts/steps/09-workers.sh:18`, `config/queue.php:44,72`.

The generated worker uses `--timeout=90`; database/Redis `retry_after` defaults to 90. A job can become available to a second worker at the boundary before the first process finishes terminating, risking duplicate execution.

**Fix instructions:** Keep the effective job/worker timeout several seconds below `retry_after`, with an appropriate operational margin. Audit per-job overrides and any Horizon supervisor separately. Make side effects idempotent because queues can redeliver even with correct timeout settings.

**Regression tests:** Assert timeout ordering for supported configurations and exercise a near-timeout job with two workers in an isolated queue environment.

### B11. Medium: Environment serialization corrupts some valid secrets

**Evidence:** `scripts/lib/common.sh:111-123,142,164`.

One escape sequence is used both for dotenv encoding and sed replacement. Sed consumes quote escapes on the update path, whereas the append path preserves replacement-specific escapes. Quotes, backslashes, ampersands, and dotenv interpolation syntax can produce malformed or altered values; updating and appending are not equivalent.

**Fix instructions:** Separate dotenv serialization from line replacement. Prefer a tested serializer and safe replacement strategy. Preserve literal values, reject unsupported multiline input explicitly if necessary, and never print real secrets in diagnostics.

**Regression tests:** Round-trip synthetic values with quotes, backslashes, `&`, `|`, `$`, `${NAME}`, spaces, and empty strings through both existing-key and absent-key paths. Parse with the installed dotenv library and assert exact equality.

### B12. Medium: Example logging configuration selects an unavailable Bugsnag driver

**Evidence:** `bootstrap/providers.php:11,17`, `composer.json:13-31`, `config/logging.php:78-80`, `.env.example:16-17`.

The provider and channel are configured, and the example stack includes Bugsnag, but the package is absent. Logging through that channel cannot provide Bugsnag reporting and falls back to Laravel's emergency handling when driver resolution fails.

**Important qualification:** This is not a confirmed boot blocker. Installed Laravel explicitly filters nonexistent bootstrap providers in `vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterProviders.php:47-54`; the targeted application tests booted successfully.

**Fix instructions:** Either add/configure Bugsnag with explicit dependency approval, or remove its provider, channel, and example stack entry together. Avoid leaving a default integration partially configured.

**Regression tests:** In an isolated clean environment using example configuration, resolve the default logging stack and write a test event without emergency fallback. If retained, fake the external transport and assert reporting.

### B13. Medium: SFTP root reads the wrong environment variable

**Evidence:** `config/filesystems.php:75` uses `env('SFTP_ROOT'.config('app.name'), '/home/backup')`.

Setting `SFTP_ROOT` has no effect: the lookup appends the application name to the variable name and silently defaults to `/home/backup`. Backups can fail or use an unintended destination.

**Fix instructions:** Read `SFTP_ROOT` directly. If an application-specific subdirectory is required, append it to the resulting path, not the environment key. Validate the intended remote path before enabling scheduled backups.

**Regression tests:** Supply a nondefault root and assert the resolved SFTP disk root before and after configuration caching.

### B14. Low: Blocked users cannot log out normally

**Evidence:** `bootstrap/app.php:27-30`, `app/Http/Middleware/EnsureUserIsNotBlocked.php:13-16`.

The block check runs across the entire web group and aborts before logout, also blocking public pages while the session remains authenticated. A user blocked during a session cannot normally switch accounts without clearing cookies.

**Fix instructions:** Allow logout and intentionally public routes while denying protected functionality. Reject blocked users during login as well, retaining request-time checks for accounts blocked after login.

**Regression tests:** Log in, block the account, assert protected access fails, then assert logout succeeds and invalidates the session. Public pages should be reachable afterward.

### B15. Low: Direct 2FA setup/recovery visits can produce server errors

**Evidence:** `app/Http/Controllers/Settings/ConfirmedTwoFactorController.php:14-20`, `app/Http/Controllers/Settings/RecoveryCodesController.php:12-17`.

These controllers decrypt nullable secrets/recovery codes without checking enrollment state. A password-confirmed user without 2FA, or a stale tab after disabling it, can trigger a decryption exception.

**Fix instructions:** Validate the state required by each page before rendering. Redirect users without a setup secret to enrollment and prevent recovery-code rendering when codes are absent. Keep password-confirmation protection intact.

**Regression tests:** Cover never-enabled and enable-then-disable accounts with confirmation middleware enabled; expect a deliberate redirect or controlled response, not 500.

### B16. Medium: Authentication and navigation controls have accessibility failures

**Evidence:** `resources/views/auth/two-factor-challenge.blade.php:3-9,44-48,89-93`, `resources/views/components/form/password-input.blade.php:8-13`, `resources/views/components/layouts/app/header.blade.php:81-86`, `resources/js/controllers/mobile_menu_controller.js:6-21`.

Recovery-code mode relies on a hidden checkbox and non-focusable labels, preventing normal keyboard-only operation. The password visibility button is focusable but marked `aria-hidden`. The mobile menu never updates its initially false `aria-expanded` state.

**Fix instructions:** Use real keyboard-operable buttons for mode switching, provide accessible names, remove `aria-hidden` from interactive controls, and synchronize expanded/pressed state. Preserve focus when switching login modes.

**Regression tests:** Complete the login/recovery flow using only the keyboard. Assert accessible button names and expanded-state changes in browser tests on desktop and mobile layouts.

## Suggested Code Improvements and Hardening

These items are recommendations or conditional risks, not additional demonstrated exploits.

1. **Make invitation acceptance atomic.** `app/Http/Controllers/AcceptInvitationController.php:27-42` creates a user and then separately marks the invitation accepted. Use a transaction, re-read/lock the invitation where supported, recheck validity, and handle concurrent acceptance or pre-existing users deliberately. Test that a failure marking acceptance does not leave a newly created account and that duplicate requests do not produce an unhandled constraint error.
2. **Remove duplicate Composer script keys.** `composer.json:93-96,103` defines `test` twice; Composer validation confirms the duplicate. Keep one intentional definition so the earlier configuration-clear behavior is not silently discarded. Re-run `composer validate --no-check-publish`.
3. **State the actual supported PHP version.** `composer.json:14` advertises `^8.2`, while Laravel 13 and the selected Symfony packages require newer PHP. Align the root requirement with the tested minimum, and test that version in CI. Dependency constraints currently prevent incompatible installation; this is misleading metadata rather than an execution bypass.
4. **Select the production worker restart hook.** `deploy.php:47-53` leaves both restart choices commented out. If workers run in production with no external release automation, they will continue running old code. Enable the hook matching the worker type and verify the running release after deployment.
5. **Pin and constrain reusable workflows.** `.github/workflows/main.yml:7-36,49-50` references mutable workflows and inherits deployment secrets. Pin reviewed commit SHAs, explicitly pass required secrets, declare least-privilege permissions, and verify deployment concurrency/approval in the called workflow and repository settings. The caller alone does not prove a race or excessive effective permissions. Add pull-request checks if external contributions are supported.
6. **Avoid retaining 2FA setup secrets in navigation caches.** `resources/views/settings/confirmed-two-factor/edit.blade.php:1-41` lacks the Turbo cache exemption used by `resources/views/settings/recovery-codes/edit.blade.php:2`. Apply equivalent protection, review HTTP cache headers, and test direct navigation plus Back/Forward after leaving setup. Cross-user disclosure was not demonstrated.
7. **Pin the SFTP host identity.** `config/filesystems.php:65-76` does not specify a host fingerprint. Configure the expected server fingerprint using the installed adapter's supported option and test rejection of a different key. This is protection against server impersonation, not evidence of interception.
8. **Add explicit controller return types.** For example, the profile and 2FA controllers omit return types. Add the actual `View`/`RedirectResponse` types incrementally and keep static analysis aligned with real request/user types. Avoid broad style-only refactors while fixing security behavior.
9. **Expand tests at system boundaries.** Add browser coverage for OTP/accessibility, mocked shell tests for provisioning, clean-install checks for optional integrations, and isolated restore/session-revocation tests. Passing controller tests cannot validate these boundaries.

## Verification Performed

- `composer validate --no-check-publish`: valid with a duplicate `test` key warning.
- `composer audit --locked --format=json`: no known advisories and no abandoned packages reported at review time. This does not certify application security or untracked/frontend dependencies.
- Autoload check for `Bugsnag\\BugsnagLaravel\\BugsnagServiceProvider`: false. Framework source inspection confirmed missing bootstrap providers are skipped.
- `vendor/bin/phpunit --do-not-cache-result tests/Feature/Http/Controllers/Settings/ProfileControllerTest.php`: 14 tests, 48 assertions passed.
- A multi-path PHPUnit invocation beginning with `tests/Feature/Http/Controllers/InvitationControllerTest.php` reported 20 tests and 77 assertions passed. Password tests were subsequently rerun individually to avoid assuming multi-path coverage.
- `vendor/bin/phpunit --do-not-record-test-run-history tests/Feature/Http/Controllers/Settings/PasswordControllerTest.php`: 9 tests, 33 assertions passed.
- `vendor/bin/phpunit --do-not-record-test-run-history tests/Feature/Http/Controllers/Auth/NewPasswordControllerTest.php`: 2 tests, 16 assertions passed.
- The initial `--do-not-cache-result` runs emitted a PHPUnit 13 CLI deprecation; subsequent runs used its replacement. This warning came from the review command, not a demonstrated application defect.
- Laravel 13 documentation was consulted for authenticated-session invalidation, email verification, and worker timeout/retry ordering.

No full-suite run, production deployment, privileged bootstrap execution, external backup transfer, restore drill, or browser exploit test was performed. No regression tests were added because this deliverable is report-only. Existing passing tests do not invalidate the uncovered scenarios above.

## Fix Delivery Guidance

For each selected finding, first add the indicated regression test and verify it fails for the expected reason. Apply the smallest coherent fix, rerun that test and adjacent coverage, and review the diff. Keep security changes separate from optional cleanup. Follow the workspace's one-file review pauses unless explicitly waived. Obtain approval before changing dependencies or production configuration.
