# Business Automation Starter Kit

A Laravel 13 starter kit for building business automation tools. It ships with authentication, queues, real-time page updates via Hotwire, and a production-ready deployment pipeline for Ubuntu servers.

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

- PHP 8.4.1+ (production uses 8.5 FPM)
- Composer
- Node.js is **not** required — Tailwind and importmap are handled by Laravel packages
- Git
- SQLite for local development, or MySQL/PostgreSQL for production

## Local development

1. Clone the repository:

```bash
git clone git@github.com:jcergolj/starter-kit.git
cd starter-kit
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

6. Start the development server:

```bash
composer run dev
```

The application will be available at `http://starter-kit.test` (or the URL configured in `APP_URL`).

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

This project is designed to be deployed on an Ubuntu server with Caddy. Server provisioning is managed by [Metator for Laravel](https://github.com/jcergolj/metator-for-laravel), and application releases are deployed with Deployer using `deploy.php`.

### Local prerequisites

- PHP 8.5+
- Composer
- Git
- SSH access to the target Ubuntu server

### Install Metator

Install Metator in the application that will manage the server and site configuration:

```bash
composer require jcergolj/metator-for-laravel
php artisan metator:install --no-interaction
```

Review the generated Metator configuration before provisioning. The repository's server bootstrap definition is in `scripts/server-bootstrap.sh`; update its site values and capabilities for the target server when necessary.

Metator expects the server bootstrap files, including `scripts/server-bootstrap.sh`, `scripts/steps/`, `scripts/lib/`, and the related metadata files, to be available in the configured repository.

### Prepare and provision the server

Prepare the Ubuntu server first:

```bash
php artisan metator:prepare-server --config=metator.production.php
```

Then provision the configured site:

```bash
php artisan metator:provision --config=metator.production.php
```

The provisioning flow installs and configures the services selected in the Metator configuration, including PHP-FPM, Caddy, the scheduler, Redis, and Supervisor/Horizon when enabled. It also creates the deploy user, configures repository access, DNS, application directories, permissions, and service definitions.

Generate a deploy key for the configured deploy user and add its public key to the GitHub repository as a deploy key if Metator has not already configured repository access:

```bash
ssh-keygen -t ed25519 -C "deploy@server"
cat ~/.ssh/id_ed25519.pub
```

### Configure the application environment

```bash
php artisan metator:update-environment --config=metator.production.php
```

Ensure the production environment contains the required values, including:

- `APP_URL`
- database and cache settings
- mail and Brevo settings
- queue/Horizon settings
- backup settings

### Deploy application releases

Install the deployment dependencies locally:

```bash
composer install
```

Deploy the `deploy` branch with Deployer:

```bash
vendor/bin/dep deploy production
```

The deployment defined in `deploy.php` verifies the Metator PHP runtime, installs Composer dependencies, builds Tailwind, runs migrations, caches Laravel configuration, and restarts/verifies configured queue or Horizon workers.

For a dry run:

```bash
vendor/bin/dep deploy production --dry-run
```

### Server services

Metator configures these services according to the site configuration:

- scheduler cron running `php artisan schedule:run` every minute
- Caddy serving the application domain
- PHP-FPM using the configured PHP version
- Supervisor running either `queue:work` or Horizon when enabled
- Redis when required by the queue configuration

Check worker status on the server with:

```bash
sudo supervisorctl status <site-id>-worker:*
```

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
