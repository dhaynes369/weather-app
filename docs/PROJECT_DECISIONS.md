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
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Use Blade/Livewire/Tailwind for presentation, a Livewire component for interaction and state, and a dedicated PHP weather service for HTTP integration and response normalisation. Use geocoding to resolve locations before requesting forecasts. Introduce Laravel caching when implementing FR-10.

**Reasoning:** Isolate provider-specific details, improve testability and maintainability, and avoid coupling UI directly to raw external JSON.

**Alternatives:** Call the API directly from the component; simpler initially but couples presentation and integration. Separate frontend/backend deployments would add unnecessary complexity for this scope.

**Trade-offs:** Additional classes and boundaries require some effort. Detailed class structure, data contracts, cache keys/TTL and retry policy remain open. Avoid unnecessary abstractions, authentication, custom database tables or Docker unless justified by requirements. Existing starter-kit SQLite infrastructure remains available.

### ADR-007 — Separate geocoding and forecast services
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Separate location resolution from forecast retrieval in dedicated services. **Reasoning:** Different endpoints, contracts and failure modes; independent tests. **Alternative:** One combined service. **Trade-off:** Additional class boundary.

### ADR-008 — Normalised weather data
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Convert parallel hourly/daily arrays into timestamp/date-indexed records; validate required fields and array lengths, preserve nulls and original selected location separately from model-grid coordinates. **Reasoning:** Prevent misaligned measurements and simplify UI, charts and alarms. **Alternative:** Pass raw JSON to UI. **Trade-off:** Mapping and validation effort; exact contracts remain open.

### ADR-009 — Weather condition mapping
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Centralise WMO code-to-description/icon mapping, with an unknown-code fallback and day/night presentation where applicable. **Reasoning:** Consistent rendering and unit testing. **Alternative:** Inline UI conditionals. **Trade-off:** Maintain mapping as provider evolves.

### ADR-010 — Units and time handling
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Explicit Celsius, mph and mm; location timezone for display; investigate Unix timestamps for UTC processing, with daily local calendar dates handled separately. Filter upcoming hourly data by time rather than taking the first 24 entries. **Reasoning:** Consistent units and daylight-saving correctness. **Alternative:** Local ISO strings throughout. **Trade-off:** Conversion complexity; Unix/daily semantics and DST require verification. Do not treat generationtime_ms as end-to-end HTTP latency.

### ADR-011 — Ambiguous location selection
**Status:** Accepted; 27 November 2026, 15:14 UTC

**Decision:** Show matching locations with regional/country context and allow explicit selection; propose three-character minimum for suggestions and debouncing. **Reasoning:** Avoid incorrect forecasts for duplicate place names and excessive requests. **Alternative:** Automatically select first result. **Trade-off:** Additional interaction; geocoding response still untested.

### ADR-012 — Weather measurement selection
**Status:** Accepted; 27 November 2026, 15:09 UTC

**Decision:** Request the following explicitly approved Open-Meteo variables, grouped by response interval.

| Interval | Approved API fields |
|---|---|
| Current (11) | `temperature_2m`, `apparent_temperature`, `relative_humidity_2m`, `weather_code`, `wind_speed_10m`, `wind_gusts_10m`, `wind_direction_10m`, `precipitation`, `is_day`, `cloud_cover`, `surface_pressure` |
| Hourly (8) | `temperature_2m`, `apparent_temperature`, `precipitation_probability`, `precipitation`, `wind_speed_10m`, `wind_gusts_10m`, `weather_code`, `relative_humidity_2m` |
| Daily (9) | `weather_code`, `temperature_2m_max`, `temperature_2m_min`, `precipitation_probability_max`, `precipitation_sum`, `wind_gusts_10m_max`, `sunrise`, `sunset`, `uv_index_max` |

**Reasoning:** Current variables support a richer dashboard, including comfort, wind, cloud and pressure context and day/night presentation. Hourly variables support charts, short-term forecasts and potential temperature, rainfall and wind threshold evaluation. Daily aggregates support the five-day outlook, daylight information and UV exposure context. Select explicit fields rather than requesting unrelated specialist variables. Approval of data selection does not commit the project to implementing optional alarms or displaying every variable immediately.

**Alternatives:** Request only core variables and expand later (smaller payload but additional integration changes); request all available variables (unnecessary payload and processing). The approved set balances feature coverage and integration effort.

**Trade-offs:** 28 interval-specific field selections increase payload size and may increase metered API call equivalents; caching and request efficiency remain important. Precipitation accumulation and probability have different meanings; pressure is surface pressure, not sea-level pressure. Missing values must not be interpreted as zero. Units and timezone direction accepted in ADR-010; timestamp implementation remains subject to verification. The complete expanded request still requires live validation.

**Evidence:** Initial forecast response supplied on 27 November 2026 returned current conditions at 15:00 Europe/London, a 900-second current interval, 120 hourly timestamps and five daily dates. It confirmed parallel arrays, separate unit metadata, local timestamps without explicit offsets and returned model-grid coordinates differing from requested coordinates. Only the initial subset was exercised; geocoding and expanded field selection remain to be tested.

**References:** [Open-Meteo forecast documentation](https://open-meteo.com/en/docs), [usage limits](https://open-meteo.com/en/pricing).

### ADR-013 — Comprehensive acquisition and progressive disclosure
**Status:** Accepted; 27 November 2026, 15:13 UTC

**Decision:** Acquire comprehensive relevant general-purpose weather data independently of what the main dashboard displays. Use expandable detail sections or equivalent drill-down UI; reuse the acquired dataset for dashboard, details, charts and potential alarms. All previously approved fields in ADR-012 remain valid. No explicit exclusion requirement for soil moisture, soil temperature or evapotranspiration is needed: these were examples outside the selected dataset, not removed fields.

**Reasoning:** Richer weather exploration, future functionality and fewer separate requests on expansion; keep the main dashboard intuitive. **Alternatives:** Minimal dashboard-only acquisition or fetch details on demand. **Trade-offs:** Larger payloads, API call-equivalent accounting and validation effort; use caching and explicit field selection. Approval does not require implementing all optional features.

**Candidate additions for validation (not yet an approved exact field register):** Dew point, rain, showers, snowfall, sea-level pressure, visibility, daylight duration, sunshine duration and other applicable interval-specific variants of already selected measurements. Confirm supported intervals and exact names against documentation and a live expanded request before implementation. Preserve ADR-012 as the approved baseline; no fields removed.

## API investigation evidence

- Forecast response supplied by user: current 15:00 Europe/London on 27 November 2026, interval 900 seconds; 120 hourly records starting local midnight and five daily dates including today. Current 10.2°C, humidity 95%, WMO 51, wind 12.3 mph. Separate unit metadata; wind unit returned as `mp/h`. Returned coordinates 50.61837, -2.443344 differ from requested 50.61, -2.45. Generation time approximately 0.55 ms is provider processing time, not network latency.
- Initial forecast request validated only a subset of approved fields. Expanded request, geocoding, error cases, DST and null handling remain unverified.
- Git checkpoint: user confirmed decision-log commit `2312260` pushed to origin/main with clean working tree. This revision extends the available local working copy; commit/push pending.

## Decision history

- 27 November 2026, 15:14 UTC: User explicitly approved ADR-006 through ADR-011 without additional comments. All six changed from Proposed to Accepted; their rationale, alternatives and trade-offs are retained. Acceptance does not imply implementation or completion of outstanding technical investigations.

## Open decisions and investigations

- Verify Unix timestamp and DST behaviour, daily local dates and detailed normalised data contracts under accepted ADR-008 and ADR-010; approved measurement selection is recorded in ADR-012.
- Validate geocoding and the expanded forecast request.
- ADR-001 through ADR-013 are accepted. Exact additional measurement fields/intervals under ADR-013 remain pending.
- Detailed service/component structure and validation strategy.
- Cache lifetime, failure behaviour, retry policy and logging.
- UI design, accessibility and forecast presentation.
- Optional features, including alarm semantics and persistence.
- CI Node version alignment and final documentation/submission process.
