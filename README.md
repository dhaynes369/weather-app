# Weather App

A responsive weather dashboard built with Laravel, Livewire and Tailwind CSS for a technical interview challenge. The implementation focuses on reliable external API integration, data normalisation, caching and maintainable application architecture.

## Features

- Worldwide town and city search with debounced suggestions and regional context.
- Current conditions, hourly forecasts and seven-day forecasts, extendable to fourteen days.
- Interactive forecast selection, temperature and feels-like chart, and expandable weather measurements.
- Browser-local favourite locations and optional browser geolocation.
- Location-aware timestamps, forecast uncertainty guidance and technical data metadata.
- Cached responses, bounded retries, rate-limit handling and clearly labelled stale-data fallback.

## Installation

### Prerequisites

- PHP 8.5 recommended (the locked test dependencies require at least PHP 8.4.1).
- Composer 2, Node.js 24 and npm. Git is needed only when cloning.
- PHP extensions required by Composer, including PDO SQLite, SQLite3, OpenSSL, cURL, mbstring and XML support.
- Internet access for dependency installation and live weather requests. No weather API key is required for this non-commercial demonstration.

### First-time setup

Clone the repository, or extract the supplied source ZIP and open a terminal in its root directory.

```sh
git clone https://github.com/dhaynes369/weather-app.git
cd weather-app
```

For a ZIP installation, skip the clone commands. From the project root run:

```sh
composer setup
```

This installs PHP dependencies, creates the local environment and SQLite database if missing, generates an application key, applies migrations, installs frontend dependencies and builds assets. Use this script for a fresh installation only: regenerating the application key invalidates existing encrypted favourite tokens and sessions.

### Run the application

```sh
php artisan serve
```

Open http://localhost:8000. The compiled frontend assets are produced during setup. For development with live asset updates, use `composer run dev` instead. Browser geolocation requires localhost or HTTPS and explicit permission.

### Run quality checks

```sh
composer test
```

This runs Laravel Pint, PHPStan/Larastan and the Pest test suite. The latest local regression run passed **234 tests and 572 assertions**. Tests mock external API responses and cover validation, retries, rate limiting, cache expiry, stale fallback, timezone conversion and dashboard interactions. Persistent cache tests also exercise SQLite. GitHub Actions runs setup and the quality checks on pushes to main and pull requests.

## Architecture

```text
Browser (Blade / Tailwind / Alpine)
              |
       Livewire dashboard
              |
     Geocoding / Forecast services
              |             |
       OpenMeteoClient   Laravel cache (SQLite)
              |
          Open-Meteo
```

`app/Data/Weather` contains typed data objects; `app/Services/Weather` handles provider integration and normalisation; `app/Support/WeatherPresenter.php` formats weather measurements for display. The dashboard retains selection state rather than serialising the entire forecast into browser state. Central API settings and measurement definitions live in `config/weather.php`.

## Reliability and data handling

| Setting | Default |
|---|---|
| Forecast acquisition | 14 calendar days |
| Forecast freshness | 15 minutes |
| Maximum stale forecast age | 2 hours from retrieval |
| Successful location search cache | 24 hours |
| Empty location search cache | 5 minutes |
| Connection / request timeout | 2 / 5 seconds |
| Retry | One retry for connection failures and selected temporary server errors |
| HTTP 429 | Provider-wide cooldown using Retry-After, or 10 seconds when unavailable/invalid |

Forecasts are normalised into typed objects with explicit units and timestamps. Missing measurements remain unavailable rather than becoming zero. UTC instants are converted to the selected location timezone for display. Daily records use local calendar dates. Forecasts are model estimates, not necessarily live station observations; extended forecasts are indicative.

## Security and privacy

Input validation is performed server-side. Favourite tokens are authenticated and encrypted using the application key, and saved in browser local storage (up to ten locations). No user account is required. Geolocation is opt-in: browser coordinates are sent to the application, rounded to three decimal places before provider requests, and forecast responses may be cached. Detected locations are not offered as favourites. HTTPS should be used outside local development; disable debug mode for deployment. Do not commit the local `.env` file or application key.

## Limitations and future development

- Reverse geocoding: detected coordinates currently display as “Current location”.
- Configurable weather alarms, background monitoring and email notifications are deferred nice-to-have features.
- Favourites are browser-local, not synchronised between devices; clearing storage or changing the application key can invalidate them.
- Provider availability and rate limits affect live requests. Concurrent cache misses may duplicate requests. This is an interview prototype, not a production-hardened service.
- The optional Fontaine font optimisation warning does not prevent asset compilation.

## Engineering decisions and attribution

See [PROJECT_DECISIONS.md](docs/PROJECT_DECISIONS.md) for requirements, architecture decisions, trade-offs and validation history.

Weather and location search data are provided by [Open-Meteo](https://open-meteo.com/). Location data originates from GeoNames via Open-Meteo. See the provider's [documentation](https://open-meteo.com/en/docs) and [terms](https://open-meteo.com/en/terms) before commercial deployment.
