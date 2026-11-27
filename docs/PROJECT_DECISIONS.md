# Weather Application — Project Decision Log

Created: 27 November 2026

This living document records engineering decisions, their rationale, alternatives and consequences. Accepted decisions describe agreed direction, not necessarily completed implementation. Proposed decisions require confirmation. Superseded decisions remain in the history.

## Project constraints

- Laravel weather application; Tailwind CSS and Livewire recommended.
- Automated tests using Pest or PHPUnit.
- Approximately two evenings of development; prioritise a working submission.
- Public GitHub repository with incremental commits, source ZIP and installation/run README.
- Source: Interview Challenge.pdf; [suggested public APIs](https://github.com/public-apis/public-apis?tab=readme-ov-file#weather).

## Decision register

All entries below dated 27 November 2026.

### ADR-001 — Weather API provider
**Status:** Accepted

**Decision:** Use Open-Meteo's geocoding and forecast HTTPS GET endpoints with JSON responses and no API key on the free tier.

**Reasoning:** Included in the supplied API directory; supports current, hourly and daily weather, global location search, and variables suitable for threshold alarms. No registration or secret configuration is required for reviewers. Selectable variables reduce unnecessary payloads. HTTPS protects transport and authenticates the server through certificate validation; it does not authenticate the calling application.

**Alternatives:** OpenWeatherMap and WeatherAPI; both require API keys and their standard free forecast offerings are less aligned with our desired hourly/five-day scope.

**Trade-offs:** External availability dependency, no free-tier uptime guarantee, rate limits and attribution requirements. Free hosted access is intended for non-commercial use; assume this interview demonstration qualifies and reassess before commercial deployment. Keep TLS verification enabled. Plan caching, controlled timeouts/retries and graceful errors. Verify live response schemas before implementation.

**References:** [Forecast](https://open-meteo.com/en/docs), [Geocoding](https://open-meteo.com/en/docs/geocoding-api), [Pricing and terms](https://open-meteo.com/en/pricing).

### ADR-002 — Application technology stack
**Status:** Accepted; baseline verified

**Decision:** Laravel 13.44.1, Livewire 4.4.10, Tailwind CSS 4, PHP 8.5.13, Composer 2.11.2 and Node.js 24.22.3/npm 11.19.1 locally, using the official blank Livewire starter kit and Laravel Herd on Windows.

**Reasoning:** Aligns with the brief, uses framework conventions and supports interactive UI without a separate SPA. Starter kit reduces setup effort.

**Alternatives:** Plain Blade or a separate JavaScript SPA; neither preferred for this exercise.

**Trade-offs:** Recent framework versions may differ from older tutorials. Retain generated development dependencies for now, including Boost; no requirement to use Boost. Generated local AI guidance is excluded from version control. This does not imply that AI assistance was not used.

### ADR-003 — Testing and continuous integration
**Status:** Accepted; baseline verified

**Decision:** Retain Pest, Pint, Larastan and the generated GitHub Actions workflow. Write meaningful tests incrementally, mock external HTTP responses for repeatability, and validate changes through CI.

**Reasoning:** Pest satisfies the brief and provides readable tests; formatting and static analysis supplement behavioural tests. CI independently verifies installation and build.

**Alternatives:** PHPUnit directly; manual-only testing (insufficient for the brief).

**Trade-offs:** Mocked responses do not prove live provider availability or schema compatibility; separately exercise the live API. Baseline two example tests passed locally and CI passed; they do not validate weather functionality. CI uses Node 22 while local development uses Node 24; alignment proposed, not yet implemented.

### ADR-004 — Version control and reproducibility
**Status:** Accepted; baseline published

**Decision:** Public GitHub repository `dhaynes369/weather-app`, branch `main`, incremental descriptive commits. Retain dependency lockfiles and `.env.example`; exclude `.env`, local SQLite database, dependencies, generated assets and local AI configuration/guidance. Use repository-local professional author name and GitHub noreply email.

**Reasoning:** Meets submission requirements, enables traceability and reproducible dependency installation, and avoids exposing secrets or personal email.

**Alternatives:** Single final commit or committing generated dependencies; rejected for traceability and repository hygiene.

**Evidence:** Initial commit `fa84008`; successful push and baseline CI. Final README and ZIP remain outstanding.

### ADR-005 — Scope and prioritisation
**Status:** Accepted

**Decision:** Use MoSCoW priorities; aim to deliver all requirements, protecting the core if time is limited.

| ID | Requirement | Priority |
|---|---|---|
| FR-01 | Town/city search | Must |
| FR-02 | Real external weather API | Must |
| FR-03 | Current temperature and conditions | Must |
| FR-04 | Humidity and wind speed | Must |
| FR-05 | Invalid location handling | Must |
| FR-06 | API failure and timeout handling | Must |
| FR-07 | Automated tests with mocked API responses | Must |
| FR-08 | Responsive professional UI | Must |
| FR-09 | Five-day forecast | Should |
| FR-10 | API response caching | Should |
| FR-11 | Location suggestions | Should |
| FR-12 | Hourly temperature chart | Could |
| FR-13 | Favourite locations | Could |
| FR-14 | Automatic location detection | Could |
| FR-15 | Weather alarming and configurable thresholds | Could |

**Reasoning:** Balance breadth with reliability and the time constraint. Alarming could showcase business rules and IoT-related engineering strengths.

**Trade-offs:** Optional features may be deferred. Alarm implementation, persistence, scheduling and notifications are not yet agreed. Application-generated threshold alarms must not be represented as official weather warnings.

**Quality goals:** Maintainability, security, efficient API usage, repeatable tests, accessibility, useful error messages/logging, reproducible setup, meaningful commits and documentation.

### ADR-006 — Separation of responsibilities
**Status:** Proposed

**Decision:** Use Blade/Livewire/Tailwind for presentation, a Livewire component for interaction and state, and a dedicated PHP weather service for HTTP integration and response normalisation. Use geocoding to resolve locations before requesting forecasts. Introduce Laravel caching when implementing FR-10.

**Reasoning:** Isolate provider-specific details, improve testability and maintainability, and avoid coupling UI directly to raw external JSON.

**Alternatives:** Call the API directly from the component; simpler initially but couples presentation and integration. Separate frontend/backend deployments would add unnecessary complexity for this scope.

**Trade-offs:** Additional classes and boundaries require some effort. Detailed class structure, data contracts, cache keys/TTL and retry policy remain open. Avoid unnecessary abstractions, authentication, custom database tables or Docker unless justified by requirements. Existing starter-kit SQLite infrastructure remains available.

## Open decisions

- API response fields, units, timezone and normalised data contracts.
- Detailed service/component structure and validation strategy.
- Cache lifetime, failure behaviour, retry policy and logging.
- UI design, accessibility and forecast presentation.
- Optional features, including alarm semantics and persistence.
- CI Node version alignment and final documentation/submission process.
