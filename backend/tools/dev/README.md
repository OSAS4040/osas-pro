# Dev tools (not web-accessible)

Loose PHP diagnostics and one-off scripts live here. They are **not** Composer-autoloaded and must not be exposed via HTTP.

- **List:** `php artisan system:tools list`
- **Run (non-production only):** `php artisan system:tools run <basename>`  
  Example: `php artisan system:tools run check_tables`

Scripts moved from the repository and backend root are stored here for isolation.
