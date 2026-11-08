# Business Automation Starter Kit

A Laravel 13 starter kit for building business automation tools. It ships with authentication, queues, and real-time page updates via Hotwire. Production provisioning and deployment scaffolding come from [Metator for Laravel](https://github.com/jcergolj/metator-for-laravel).

## What’s included

- **Laravel 13** with PHP 8.4.1+
- **Hotwire (Turbo + Stimulus)** for server-rendered, reactive UIs
- **Laravel Fortify** for authentication scaffolding
- **Laravel Horizon** for queue monitoring and management
- **SQLite** for local development (MySQL/PostgreSQL ready in production)
- **Tailwind CSS** with the Laravel Tailwind package
- **Importmap** for JavaScript dependency management
- **Spatie Laravel Backup** for application backups
- **Laravel Pail, Pint, PHPStan, Rector** and PHPUnit for development quality

## Requirements

- PHP 8.4.1+ (check `composer.lock` for the installed dependencies' requirements)
- Composer
- Node.js is **not** required — Tailwind and importmap are handled by Laravel packages
- Git
- SQLite for local development, or MySQL/PostgreSQL for production

## Local development

1. Clone the repository:

```bash
git clone git@github.com:jcergolj/simple-starter-kit.git
cd simple-starter-kit
```

2. Install dependencies and create the environment file:

```bash
composer install
cp .env.example .env
```

3. Generate an application key and create the SQLite database:

```bash
php artisan key:generate
touch database/database.sqlite
```

4. Run migrations:

```bash
php artisan migrate
```

5. Download Tailwind and build assets:

```bash
php artisan tailwindcss:download --force
php artisan tailwindcss:build --no-tty
php artisan storage:link
```

6. Start the development processes, each in its own terminal from the project root. No additional process manager is required:

```bash
php artisan serve --host=0.0.0.0 --no-reload
```

```bash
php artisan pail --timeout=0
```

```bash
php artisan queue:listen --tries=1
```

```bash
php artisan tailwindcss:watch --no-tty
```

Stop each command with `Ctrl+C` in its terminal.

Open the URL printed by `php artisan serve`. Set `APP_URL` in `.env` to match the URL you use for local development.

## Testing and code quality

Run the test suite:

```bash
composer run test
```

Run static analysis and formatting checks:

```bash
composer run analyse
```

Individual tools:

```bash
composer run pint      # code formatting
composer run rector    # automated refactoring
composer run phpstan   # static analysis
```

## Project structure

Application behavior is organized by feature under `app/Features`:

```text
app/Features/
├── Authentication/   # Fortify actions and authentication views
├── Dashboard/        # Dashboard route and view
├── Invitations/      # Invitation controllers, actions, mail, routes, and views
├── Settings/         # Account settings controllers, requests, routes, and views
└── UserManagement/   # User administration controllers, requests, routes, and views
```

Shared application types stay in conventional Laravel locations such as `app/Models`, `app/Enums`, `app/DataTransferObjects`, `app/Policies`, and `app/ValueObjects`.

### Adding feature routes and views

Create the feature route file at `app/Features/<Feature>/Routes/web.php`, then register it in the `then` callback of `bootstrap/app.php`:

```php
Route::middleware('web')->group(
    base_path('app/Features/Billing/Routes/web.php')
);
```

Register the feature's views in `app/Providers/FeatureServiceProvider.php`:

```php
View::addNamespace('billing', base_path('app/Features/Billing/Views'));
```

Reference those views through the namespace, for example `view('billing::index')`. Keep feature middleware, route names, and prefixes in the feature route file. Tailwind scans `app/Features` automatically, so feature view classes are included in CSS builds.

## Theme Customization

This application uses:

- `tonysm/tailwindcss-laravel` for the standalone Tailwind build
- `tonysm/importmap-laravel` for JavaScript modules without Node bundling
- daisyUI in standalone plugin mode from local files in `resources/css/tailwind/`

### Where the theme lives

The authoritative theme definition is in `resources/css/app.css`.

Use the daisyUI standalone theme plugin block near the top of that file:

```css
@plugin "./tailwind/daisyui.js" {
    themes: false;
}

@plugin "./tailwind/daisyui-theme.mjs" {
    name: "light";
    default: true;
    color-scheme: light;

    --color-base-100: #ffffff;
    --color-base-200: #f8fafc;
    --color-base-300: #edf2f7;
    --color-base-content: #0f172a;
    --color-primary: #3b82f6;
    --color-primary-content: #eff6ff;
    --color-secondary: #475569;
    --color-secondary-content: #f8fafc;
    --color-accent: #3b82f6;
    --color-accent-content: #eff6ff;
    --color-neutral: #0f172a;
    --color-neutral-content: #f8fafc;
    --color-info: #3b82f6;
    --color-info-content: #eff6ff;
    --color-success: #0f766e;
    --color-success-content: #f0fdfa;
    --color-warning: #f59e0b;
    --color-warning-content: #451a03;
    --color-error: #dc2626;
    --color-error-content: #fef2f2;
}
```

### How to change the theme

1. Edit the daisyUI theme tokens in `resources/css/app.css`
2. Keep `@plugin "./tailwind/daisyui.js" { themes: false; }` so built-in daisyUI themes do not override the app theme
3. Keep the layout `<html>` tags on `data-theme="light"` unless you are intentionally adding more themes
4. Rebuild CSS with:

```bash
php artisan tailwindcss:build --no-tty
```

For local development you can keep the watcher running:

```bash
php artisan tailwindcss:watch --no-tty
```

### Important

- Do not edit `public/dist/css/app.css`; it is generated output and will be overwritten on every build
- The stylesheet link should include a build-based `?v=` query string in the shared head partial to prevent stale browser caches from making the old theme appear after rebuilds
- If a theme change does not appear immediately, do a hard refresh first

## Deployment

### Metator is the deployment source of truth

[Metator for Laravel](https://github.com/jcergolj/metator-for-laravel#quick-start) is the primary source for deployment tooling, provisioning definitions, and their documentation. Follow its current requirements and installation guide rather than maintaining a separate bootstrap workflow in this starter kit.

This repository provides the Laravel application, its Composer lockfile, environment template, migrations, and asset sources. From a clean checkout, complete **Local development** above before adding deployment scaffolding to your application:

```bash
composer require --dev jcergolj/metator-for-laravel
php artisan metator:install
```

Metator supplies Deployer as a dependency. Its installer generates the site configuration, editable `deploy.php`, and selected provisioning pipeline. These are generated for your application; they are not bundled with this starter kit, and `composer install` alone does not install Metator or provide `vendor/bin/dep`.

### Provision and deploy

Follow Metator's [server workflow](https://github.com/jcergolj/metator-for-laravel#server-workflow) to prepare the server, provision the site, and register its deploy key. Provisioning configures infrastructure; it does not deploy application code. Review the generated recipe, then use the separate Deployer release command documented by Metator.

Configure application-specific release tasks in the generated `deploy.php`. In particular, use Metator's [Tailwind deployment guidance](https://github.com/jcergolj/metator-for-laravel#custom-deployment-scripts) for this application's standalone Tailwind build; no npm build is needed. Do not assume that generating the recipe adds every application-specific task.

Validate the generated configuration and recipe in staging, including a deployment dry run if the installed tooling supports one. This starter kit has no deployment target or bundled recipe against which to run a deployment dry run.

### Production readiness checklist

Your hosting configuration and release process are responsible for:

- Providing a PHP runtime and extensions compatible with `composer.lock`, a web server serving only `public/`, and the required database, cache, and queue services.
- Managing the production environment and application key securely, with `APP_ENV=production`, `APP_DEBUG=false`, the correct `APP_URL`, and database, mail, queue, and backup settings.
- Installing production Composer dependencies, building Tailwind assets, and configuring writable storage and the public storage link.
- Backing up data, applying migrations, and refreshing Laravel caches as appropriate for the release.
- Configuring the scheduler and persistent workers, restarting workers after releases, and checking application health.
- Defining rollback/recovery procedures and validating the chosen automation in staging, including a dry run if that tooling supports one.

Use Metator's documentation and your generated configuration and recipe to determine which steps are automated. Verify the remaining operator responsibilities before going live. The local development commands above are not a production service configuration.

### Verify production mode before enabling traffic

Make this a required release check in your generated Metator/Deployer workflow, after refreshing Laravel's configuration cache and before routing public traffic to the release. Stop the rollout if any check fails; do not use `--env=production` to make a verification command appear to pass.

From the deployed release directory, as the application user, inspect the effective Laravel settings:

```bash
php artisan env --no-interaction
php artisan config:show app.env --no-interaction
php artisan config:show app.debug --no-interaction
```

Both environment checks must report `production`, and `app.debug` must be `false`. Checking `.env` alone is insufficient: a stale configuration cache or process-level environment can change the effective settings. Ensure PHP-FPM/web processes use the same release and configuration, reload persistent processes after deployment, and verify their effective environment/debug settings through existing restricted instrumentation if they differ from CLI. Do not publish a diagnostic endpoint or dump the full application configuration, which contains secrets.

Then probe the release through its real HTTPS ingress without authentication. Replace `BASE_URL` with the deployed origin; do not supply cookies, credentials, or follow redirects:

```bash
BASE_URL='https://your-production-host'
for path in horizon log-viewer log-viewer/api/hosts; do
    curl --silent --show-error --output /dev/null --write-out '%{http_code}\n' \
        --header 'Accept: application/json' "$BASE_URL/$path"
done
```

Each application response must be `401`. A `200`, connection error, or unexpected status needs investigation before rollout. If an upstream access control intercepts these requests, also verify application authorization from an allowed, restricted network. A fresh browser session must redirect dashboard visits to sign-in, not show operational data. Run these probes before the traffic switch using your restricted staging/canary ingress, and repeat after the switch.

The dashboard middleware already requires authentication, including in local mode. Horizon's development-environment authorization shortcut is not a reason to deploy with `APP_ENV=local`. Local defaults are for development, not evidence of production exposure. This repository has no deployed target; completing this checklist on the actual release remains the operator's responsibility.

### HTTPS sessions and reverse proxies

With `APP_ENV=production`, session cookies default to `Secure`, even when TLS terminates at a reverse proxy and PHP receives HTTP. Set `SESSION_SECURE_COOKIE=true` explicitly in production and rebuild the configuration cache. Do not override it with `false` or `null`. Local development retains automatic HTTPS detection, so ordinary local HTTP sessions continue to work.

Cookie protection does not establish that Laravel recognizes the original request as HTTPS. For a reverse-proxied deployment:

1. Restrict direct access to the application backend and make the proxy overwrite client-supplied forwarding headers.
2. In the generated application's `bootstrap/app.php`, configure Laravel's `trustProxies(at: [...], headers: ...)` with the actual proxy IPs/CIDRs and only the headers that proxy supplies. Include `Request::HEADER_X_FORWARDED_PROTO` when it forwards the original scheme. Do not trust `*` or arbitrary client addresses; do not enable forwarded-host trust unless required and constrained by the host allowlist.
3. Verify through the real TLS endpoint that Laravel's request reports HTTPS. Check via temporary restricted diagnostics or existing internal instrumentation, then remove diagnostics; forcing generated URLs to HTTPS is not proof that request detection works.
4. Inspect a fresh HTTPS `/login` response in browser developer tools: the configured session cookie must include `Secure`, `HttpOnly`, and the expected `SameSite` attribute. Verify that sign-in and subsequent authenticated navigation work.
5. Confirm HTTP is redirected to HTTPS at the web server/proxy and that an untrusted client cannot change the effective scheme, host, or client IP with forwarded headers.

No production proxy topology is bundled with this starter kit. These live checks must be completed against your generated Metator/server configuration; application tests cannot verify a deployed proxy's behavior.

### SFTP backup host identity

The `sftp-backup` disk requires `SFTP_HOST_FINGERPRINT` before it can connect, in every environment. Missing fingerprints raise a configuration error; mismatched host keys fail the connection. Other application commands remain available when SFTP is not configured.

Obtain the fingerprint through the backup server's trusted console or an independently authenticated operator. For example, run this **on the backup server** for its configured Ed25519 host key:

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Set `SFTP_HOST_FINGERPRINT` to the complete `SHA256:...` value from that output. Do not trust an unverified `ssh-keyscan` result or simply copy the key offered by a failed connection. Verify planned host-key changes through the same independent channel before replacing the fingerprint. Rebuild Laravel's configuration cache after changing production configuration.

Configure `BACKUP_NOTIFICATION_EMAIL` and check backup failure/monitor notifications and scheduler output. Verify the destination in staging with `php artisan backup:monitor` before enabling production backups.

If backups previously used an unverified destination, investigate possible interception. Rotate potentially exposed SFTP passwords, and revoke/replace SSH keys if their private material may have been exposed. Treat transferred backup archives as potentially copied; assess their contents, archive-password protection, and whether credentials or other secrets inside them need rotation. Preserve and verify trusted recovery copies before changing retention.

The isolated host-identity regression uses a temporary loopback-only OpenSSH server and transfers no backups. It requires `sshd` and `ssh-keygen`; the connection tests explicitly skip when those executables are unavailable. The missing-fingerprint tests run without OpenSSH.

### GitHub Actions

If you want GitHub Actions to commit changes, update the repository permissions:

**Settings -> Actions -> General -> Workflow permissions** and choose **Read and write permissions**.

## Environment variables

Copy `.env.example` to `.env` and configure the values for your environment. Key variables include:

- `APP_NAME` and `APP_URL`
- `DB_CONNECTION` — defaults to SQLite for local development
- `QUEUE_CONNECTION` — defaults to `database`
- `CACHE_STORE` — defaults to `database`
- `MAIL_*` and `BREVO_API_KEY` for transactional email
- `AWS_*` for S3 storage
- `SFTP_*` for SFTP storage
- `SUPERADMIN_EMAIL` — the email address of the initial superadmin user
- `HORIZON_DOMAIN` and `HORIZON_NAME` for Horizon dashboard access
- `BACKUP_NOTIFICATION_EMAIL` and `BACKUP_ARCHIVE_PASSWORD` for Spatie Backup

## License

This project is open-source software licensed under the [MIT license](LICENSE).
