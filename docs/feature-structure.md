# Feature Structure

Application behavior is organized under `app/Features/<FeatureName>`:

- `Authentication` contains Fortify actions and authentication views.
- `Dashboard` contains the dashboard route and view.
- `Invitations` contains invitation controllers, requests, mail, routes, and views.
- `Settings` contains settings controllers, requests, notifications, routes, and views.
- `UserManagement` contains user administration controllers, requests, routes, and views.

Shared application types remain in their conventional locations: models in `app/Models`, enums in `app/Enums`, data transfer objects in `app/DataTransferObjects`, policies in `app/Policies`, and value objects in `app/ValueObjects`.

## Adding Files

Put a new class beside the feature behavior that owns it. Use `app/Models`, `app/Rules`, `app/Http`, `app/Console`, or `resources/views/components` only when the code is genuinely shared or required by Laravel or a package. Do not create a `Shared` feature for convenience.

Feature-specific jobs and actions belong in that feature's `Jobs` or `Actions` directory. A feature may import code from another feature when that dependency reflects the real behavior.

## Routes and Views

Put feature routes in `<Feature>/Routes/web.php` and explicitly require that file from `routes/web.php`. Keep the feature's middleware, prefixes, and route names in its route file.

Register a feature's Blade directory in `app/Providers/FeatureServiceProvider.php` with `View::addNamespace()`, then reference views with the namespace, for example `settings::profile.edit`.
