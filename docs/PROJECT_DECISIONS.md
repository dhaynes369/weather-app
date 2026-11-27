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
| FR-09 | Seven-day forecast (revised from five days) | Should |
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

**Decision:** Show matching locations with regional/country context and allow explicit selection; propose three-character minimum for suggestions and debouncing. **Reasoning:** Avoid incorrect forecasts for duplicate place names and excessive requests. **Alternative:** Automatically select first result. **Trade-off:** Additional interaction; geocoding response validated at 15:16 UTC (see investigation evidence).

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

**Evidence:** Initial forecast response supplied on 27 November 2026 returned current conditions at 15:00 Europe/London, a 900-second current interval, 120 hourly timestamps and five daily dates. It confirmed parallel arrays, separate unit metadata, local timestamps without explicit offsets and returned model-grid coordinates differing from requested coordinates. Only the initial forecast subset was exercised; expanded field selection remains to be tested.

**References:** [Open-Meteo forecast documentation](https://open-meteo.com/en/docs), [usage limits](https://open-meteo.com/en/pricing).

### ADR-013 — Comprehensive acquisition and progressive disclosure
**Status:** Accepted; 27 November 2026, 15:13 UTC

**Decision:** Acquire comprehensive relevant general-purpose weather data independently of what the main dashboard displays. Use expandable detail sections or equivalent drill-down UI; reuse the acquired dataset for dashboard, details, charts and potential alarms. All previously approved fields in ADR-012 remain valid. No explicit exclusion requirement for soil moisture, soil temperature or evapotranspiration is needed: these were examples outside the selected dataset, not removed fields.

**Reasoning:** Richer weather exploration, future functionality and fewer separate requests on expansion; keep the main dashboard intuitive. **Alternatives:** Minimal dashboard-only acquisition or fetch details on demand. **Trade-offs:** Larger payloads, API call-equivalent accounting and validation effort; use caching and explicit field selection. Approval does not require implementing all optional features.

**Historical candidate additions (superseded by accepted ADR-015):** Dew point, rain, showers, snowfall, sea-level pressure, visibility, daylight duration, sunshine duration and other applicable interval-specific variants of already selected measurements. Supported intervals and exact names subsequently validated in the expanded request and accepted in ADR-015. Preserve ADR-012 as the approved baseline; no fields removed.

### ADR-014 — Geocoding data contract and search behaviour
**Status:** Accepted; 27 November 2026, 15:20 UTC

**Decision:** Require provider ID, name, latitude and longitude. Retain nullable country, countryCode, region (admin1), district (admin2), localAuthority (admin3), timezone, elevation and population; represent missing postcodes as an empty array. Preserve valid negative coordinates and elevations, and do not substitute zero for missing population. Deduplicate administrative names in display labels. Preserve provider search ranking; support an optional country filter with worldwide search as default. Enforce a minimum of three characters before issuing search requests. After successful HTTP and response-schema validation, normalise an absent results property to an empty array. Distinguish empty results from API failures, log technical errors and display user-friendly messages without exposing raw provider errors.

**Reasoning:** Live tests confirmed fuzzy matching, ambiguous names, omitted optional fields, omitted results for unknown/short searches, HTTP 400 for missing name and working country filtering. These refinements support predictable search behaviour and independently testable data contracts.

**Alternatives:** Raw provider objects, custom ranking, UK-only default, treating absent results as an error, or forwarding raw provider errors. **Trade-offs:** Three-character minimum prevents two-character exact searches; provider ranking may be imperfect; validation and mapping require implementation effort. Country filter UI and country flags remain optional presentation choices. Automated tests and implementation remain outstanding.

### ADR-015 — Comprehensive forecast measurement contract
**Status:** Accepted; 27 November 2026, 15:29 UTC; live validation completed at 15:27 UTC.

**Decision:** Expand ADR-012 to the following exact register (no previously approved fields removed). Preserve units, timestamps and null values.

- Current (25): `temperature_2m,apparent_temperature,dew_point_2m,wet_bulb_temperature_2m,wind_speed_10m,wind_direction_10m,wind_gusts_10m,precipitation,rain,showers,snowfall,snow_depth,relative_humidity_2m,pressure_msl,surface_pressure,cloud_cover,cloud_cover_low,cloud_cover_mid,cloud_cover_high,visibility,uv_index,uv_index_clear_sky,sunshine_duration,weather_code,is_day`.
- Hourly (26): all current field names plus `precipitation_probability`.
- Daily (32): `temperature_2m_max,temperature_2m_min,temperature_2m_mean,apparent_temperature_max,apparent_temperature_min,apparent_temperature_mean,wind_speed_10m_max,wind_speed_10m_mean,wind_speed_10m_min,wind_gusts_10m_max,wind_direction_10m_dominant,precipitation_sum,rain_sum,showers_sum,snowfall_sum,precipitation_hours,precipitation_probability_max,precipitation_probability_mean,precipitation_probability_min,relative_humidity_2m_mean,relative_humidity_2m_max,relative_humidity_2m_min,cloud_cover_mean,visibility_mean,pressure_msl_mean,sunrise,sunset,daylight_duration,sunshine_duration,uv_index_max,uv_index_clear_sky_max,weather_code`.

**Reasoning:** One successful comprehensive request returned every expected value. **Alternatives:** Minimal acquisition or on-demand detail requests. **Trade-offs:** Larger payload and weighted API quota; availability in other locations/models is not guaranteed.

### ADR-016 — 14-day forecast acquisition
**Status:** Accepted; 27 November 2026, 15:29 UTC.

**Decision:** Configurable 14 calendar days including today, seven days displayed by default (revised from five), extendable to 14; reuse server-side cached dataset. Satisfies and extends the original five-day UI requirement. **Reasoning:** Greater flexibility with measured payload under 48 KiB. **Alternatives:** Five or seven days only. **Trade-offs:** Lower long-range forecast confidence, greater data volume and quota considerations.

### ADR-017 — Weather data metadata
**Status:** Accepted; 27 November 2026, 15:29 UTC.

**Decision:** Retain measurement and retrieval timestamps, current interval, timezone, units and provider grid coordinates separately from selected location. Display dimensionless values appropriately and normalise display labels (e.g. mp/h to mph). Filter elapsed hourly timestamps for upcoming forecasts. **Reasoning:** Traceability and correct presentation. **Alternatives:** Discard metadata after mapping. **Trade-offs:** Additional mapping; non-zero timezone and DST handling remain to be validated.

### ADR-018 — Timestamp normalisation

**Status:** Accepted; 27 November 2026, 15:32 UTC.

**Decision:** Store current, hourly, sunrise/sunset and retrieval timestamps as UTC instants; normalise daily forecast dates to the selected location's local calendar date. Retain IANA timezone and provider offset metadata. Use Carbon for timezone conversion and UTC comparisons for upcoming forecasts. **Reasoning:** Avoid ambiguous local timestamps and incorrect day boundaries. **Alternative:** Store local timestamps throughout. **Trade-offs:** Explicit daily date mapping and DST testing required; provider behaviour across an actual offset transition remains unverified.

### ADR-019 — Cache strategy

**Status:** Accepted; 27 November 2026, 15:34 UTC.

**Decision:** Use Laravel server-side cache, with configurable forecast freshness of 15 minutes and stale retention of 2 hours from retrieval; geocoding successful results cached 24 hours and empty results 5 minutes. Cache keys include provider, schema version, normalized query/country or selected coordinates and forecast request parameters. Cache only validated successful responses; UI changes reuse cached data. **Reasoning:** Reduce weighted API consumption and latency while matching current model timestep. **Alternative:** No cache or permanent persistence. **Trade-offs:** Freshness versus quota; stale data must be labeled; cache storage overhead.

### ADR-020 — HTTP timeouts and retries

**Status:** Accepted; 27 November 2026, 15:34 UTC.

**Decision:** Configurable 2-second connection timeout, 5-second per-attempt request timeout, at most one retry after 250 ms for transient connection failures or HTTP 500/502/503/504. Do not immediately retry 429 or other 4xx; honor Retry-After through a bounded provider cooldown where available. **Reasoning:** Recover transient failures without excessive UI delays or quota usage. **Alternative:** Unlimited retries or no retry. **Trade-offs:** Worst-case request time roughly 10.25 seconds plus overhead; tuning and mocked tests required.

### ADR-021 — Failure and stale-data handling

**Status:** Accepted; 27 November 2026, 15:34 UTC.

**Decision:** Distinguish empty search results, invalid input, upstream failures, malformed responses and rate limiting. Serve last validated forecast within stale retention on transient upstream failure, visibly label stale data and retrieval time; otherwise show friendly error and retry action. Never represent missing values as zero or treat stale forecasts as fresh alarm evidence. **Reasoning:** Graceful degradation without misleading users. **Alternative:** Blank dashboard on any failure. **Trade-offs:** Stale data can be outdated; clear UI status required.

### ADR-022 — Logging and verification

**Status:** Accepted; 27 November 2026, 15:34 UTC.

**Decision:** Structured Laravel logs for upstream failures, status, elapsed time, attempt count and cache outcome where useful; avoid raw payloads, secrets and unnecessary user search/location details. Mock HTTP responses and test cache hits, expiry, retry eligibility, stale fallback, malformed data and rate limiting. **Reasoning:** Observable and repeatable integration. **Alternative:** Manual live testing only. **Trade-offs:** Additional tests and logging configuration.

### ADR-023 — Central weather configuration

**Status:** Accepted at 15:52 UTC, 27 November 2026; configuration implemented and locally verified.

**Decision:** Centralise provider endpoints, measurement registers, units, forecast duration, cache lifetimes and HTTP settings in `config/weather.php`. Reuse the current measurement array for hourly fields using array unpacking, adding precipitation probability. Initial defaults: maximum search length 100, result limit 10, English search language, 500 ms debounce, cache schema version 1. Seven-day default display and 14-day acquisition follow revised ADR-016.

**Reasoning:** Avoid duplicated constants and enable configuration overrides in tests. **Alternative:** Hardcode settings in each service. **Trade-offs:** Shared current/hourly list assumes continued overlap; split if provider support diverges. Search defaults were initially selected by the assistant and accepted by the user at 15:52 UTC; debounce and search validation are not yet implemented.

### ADR-024 — Typed weather integration exception

**Status:** Accepted at 15:52 UTC, 27 November 2026; implemented and locally verified.

**Decision:** `App\Exceptions\WeatherApiException` extends `RuntimeException`; use read-only typed category and nullable HTTP status, with exception chaining. Categories: connection (including timeout), HTTP error, rate limit and invalid response. Services catch this specific exception for controlled recovery.

**Reasoning:** Preserve failure context and distinguish provider failures from application defects. **Alternative:** Generic exceptions or separate subclasses for every category. **Trade-offs:** Shared connection category does not distinguish timeout from other transport failures; category strings are not an enum.

### ADR-025 — Shared HTTP client implementation

**Status:** Accepted at 15:52 UTC, 27 November 2026, subject to outstanding refinements and testing; code supplied at 15:51 UTC. Live geocoding smoke test, PHP syntax and Pint passed at 15:57 UTC; static analysis, cooldown refinements and mocked tests pending.

**Decision:** `App\Services\Weather\OpenMeteoClient` provides a shared GET method for configured endpoints, using Laravel HTTP, Cache and Log facades. Use an explicit bounded retry loop rather than Laravel's retry helper, retaining approved timeouts and retry statuses. Validate JSON object shape and reject provider error envelopes before handing data to domain services. Preserve TLS verification; log endpoint, category, status, attempt and elapsed time without raw payloads or query parameters.

**Reasoning:** Centralise transport policy and make retry branches and diagnostics explicit. **Alternatives:** Laravel retry helper; duplicated transport in each service. **Trade-offs:** Manual loop adds maintenance; current JSON implementation decodes twice; domain schema validation and cache fallback are separate outstanding tasks.

**Cooldown implementation defaults (provisional):** Per-endpoint cache marker, parse Retry-After seconds or date, default 60 seconds, clamp to 1–900 seconds. No immediate retry on 429. Move bounds/default into configuration; review shared provider quota scope and whether a 900-second cap could resume requests before a longer Retry-After expires. These defaults were initially selected by the assistant; acceptance of ADR-025 is conditional on the outstanding refinements and testing, not final approval of the provisional cooldown policy. Test cooldown expiry, no outbound request during cooldown, malformed JSON, retries and failure logging. Live success path verified; resilience behaviour remains unverified.

## API investigation evidence

- Forecast response supplied by user: current 15:00 Europe/London on 27 November 2026, interval 900 seconds; 120 hourly records starting local midnight and five daily dates including today. Current 10.2°C, humidity 95%, WMO 51, wind 12.3 mph. Separate unit metadata; wind unit returned as `mp/h`. Returned coordinates 50.61837, -2.443344 differ from requested 50.61, -2.45. Generation time approximately 0.55 ms is provider processing time, not network latency.
- Initial forecast request validated only a subset of approved fields. Expanded forecast request subsequently validated; forecast error cases, DST and null handling remain unverified. Geocoding edge cases are validated below.
- Git checkpoint: user confirmed decision-log commit `2312260` pushed to origin/main with clean working tree. Subsequent architecture checkpoint `6fe3936` was confirmed pushed to origin/main at 15:36 UTC with clean working tree. Later seven-day revision and implementation changes remain uncommitted as of 15:51 UTC.

- Geocoding success response supplied by user at 15:16 UTC: five Weymouth matches across US, GB, AU and NZ; first result Massachusetts, second Dorset (ID 2634202, coordinates 50.61448, -2.45991). Optional fields vary: GB lacks postcodes; NZ lacks population and admin3. Negative elevation and coordinates occur. This validates ADR-011 explicit location selection. Error and empty-result behaviour subsequently validated at 15:19 UTC.
- Accepted location data contract (ADR-014): require provider ID, name and coordinates; retain nullable country, country code, administrative regions, timezone, elevation and population; missing postcodes as empty array. Deduplicate display label regions. Country flags proposed for presentation. Contract and naming accepted at 15:20 UTC.

- Geocoding edge-case tests supplied by user at 15:19 UTC: `Weym` returns five fuzzy matches (including Weuimél), unknown `zzzinvalidxyz` and one-character `W` return successful responses containing only `generationtime_ms` (no `results` property); omitted `name` returns HTTP 400 with `error: true` and a textual `reason`; `countryCode=GB` returns the Dorset Weymouth only. All five test scenarios exercised successfully.
- Accepted implementation refinements under ADR-014: preserve provider ranking initially; support optional country filter in service with worldwide default; distinguish successful empty results from transport/HTTP errors; default absent results to an empty array only after successful response and schema checks; validate minimum search length before requests; log technical errors without exposing raw provider messages. These refinements and the location contract were accepted at 15:20 UTC. Tests should cover absent results, fuzzy matches, country filtering, malformed responses and HTTP failures.

- Comprehensive Weymouth forecast test supplied at 15:27 UTC: HTTP 200, 469 ms end-to-end, 48,786 bytes decoded JSON, provider generation 3.8159 ms. All 25 current, 26 hourly (336 timestamps) and 32 daily (14 timestamps) fields present; 9,209 values, zero nulls. Europe/London offset 0; current 15:15 UTC, interval 900 seconds; hourly 27 Nov 00:00 to 10 Dec 23:00 UTC; daily 27 Nov to 10 Dec inclusive. Units confirmed including snowfall cm, snow depth m, visibility m, sunshine seconds, wind mp/h, sunrise/sunset Unix time and dimensionless empty unit strings. Single-location snapshot, not a global availability or latency guarantee.

- International timezone tests supplied at 15:32 UTC: Weymouth (offset 0), New York (-18000), New Delhi (+19800), Sydney (+39600) all returned 336 hourly and 14 daily timestamps. Current UTC 2026-11-27 15:30 in all cases. First hourly/daily UTC boundaries respectively 27 Nov 00:00, 27 Nov 05:00, 26 Nov 18:30 and 27 Nov 13:00, each corresponding to local midnight. Sydney local current time 28 Nov 02:30; sunrise/sunset local 05:38/19:48. All four cases consistent with UTC instants and local calendar boundaries. Actual DST transition not tested.

## Backend implementation verification

- 15:45 UTC: `config/weather.php` syntax, runtime values and Pint passed. Initial empty unsaved file caused PHP include to return 1; resolved by saving. Windows line endings corrected using Pint.
- 15:46 UTC: Artisan generated exception and HTTP client scaffolds.
- 15:51 UTC: Exception syntax, Pint and Tinker instantiation passed; category `rate_limit`, status/code 429 and message verified. Initial whitespace before PHP opening tag caused namespace parsing error; removed successfully.
- 15:51 UTC: Shared HTTP client code supplied, not yet confirmed saved or executed. At that point no live Laravel API request or mocked HTTP tests had been verified.
- 15:57 UTC: HTTP client PHP lint and Pint passed; live Laravel Tinker geocoding request for Weymouth with countryCode GB returned one result (ID 2634202, 50.61448, -2.45991, Europe/London). HTTPS, dependency resolution, query handling and JSON decoding exercised successfully. Retry, rate-limit, malformed response and static analysis checks remain pending.
- Git checkpoint recommended at 15:54 UTC for configuration, exception and decision log independently of HTTP client; commit/push not yet confirmed.

## Decision history

- 27 November 2026, 15:34 UTC: User explicitly accepted ADR-019 through ADR-022. Initial architecture review complete; proceed to backend implementation. All policies remain subject to implementation and verification.

- 27 November 2026, 15:32 UTC: User explicitly accepted ADR-018. DST-transition validation remains outstanding. ADR-019 through ADR-022 proposed for cache, HTTP resilience, failure handling and logging.

- 27 November 2026, 15:29 UTC: User explicitly accepted ADR-015, ADR-016 and ADR-017 (83-variable contract, 14-day acquisition and weather metadata). Implementation and international timezone validation remain outstanding.

- 27 November 2026, 15:20 UTC: User explicitly approved all six geocoding refinements (location data contract, search ranking, country filtering, empty results, input validation and error handling). Recorded together as ADR-014; prior ADRs retained.

- 27 November 2026, 15:14 UTC: User explicitly approved ADR-006 through ADR-011 without additional comments. All six changed from Proposed to Accepted; their rationale, alternatives and trade-offs are retained. Acceptance does not imply implementation or completion of outstanding technical investigations.

- 27 November 2026, 15:52 UTC: Reconciled decision log against backend implementation conversation. ADR-023 and ADR-024 record implemented choices awaiting explicit review of assistant-selected defaults; ADR-025 records proposed HTTP client. All 22 prior accepted ADRs retained. User requested continuous updates rather than deferral until commits.

- 27 November 2026, 15:52:56 UTC: User explicitly accepted ADR-023, ADR-024 and ADR-025 as presented, with ADR-025 subject to outstanding refinements and testing. All 25 ADRs now accepted; implementation verification remains separate from acceptance.

## Open decisions and investigations

- Verify Unix timestamp and DST behaviour, daily local dates and detailed normalised data contracts under accepted ADR-008 and ADR-010; approved measurement selection is recorded in ADR-012.
- Expanded forecast request and geocoding edge cases completed; forecast error cases and actual DST-transition tests remain; non-zero timezone tests passed.
- ADR-001 through ADR-025 are accepted (ADR-025 subject to refinements and testing); exact expanded register validated. Configuration and exception locally verified; remaining implementation and automated verification pending.
- Detailed service/component structure and validation strategy.
- Implement and test accepted cache lifetime, failure behaviour, retry policy and logging (ADR-019 through ADR-022).
- UI design, accessibility and forecast presentation.
- Optional features, including alarm semantics and persistence.
- CI Node version alignment and final documentation/submission process.

- 27 November 2026, 15:41 UTC: User revised ADR-016 and FR-09 to a seven-day default display for weekly planning; 14-day acquisition and extended display remain unchanged. Configuration runtime verification subsequently passed at 15:45 UTC (14, 7, 25, 26, 32); Pint line endings corrected and rechecked at 15:45 UTC.

- ADR-023 through ADR-025 reviewed and accepted; validate HTTP client, refine cooldown policy, run static analysis and mocked tests before committing.

## Implementation checkpoint — 27 November 2026, 16:02 UTC

- 15:58 UTC: Commit `7ae5df5` (`Implement weather API configuration and resilient HTTP client`) pushed successfully to `origin/main`; clean working tree confirmed. Four files changed, 428 insertions and four deletions. This supersedes the earlier pending checkpoint notes; HTTP client live success was verified before commit, resilience tests and static analysis remain outstanding.
- 16:02 UTC: Pest feature test scaffold created at `tests/Feature/Weather/OpenMeteoClientTest.php`. Existing `tests/Pest.php` and `phpunit.xml` reviewed: Laravel TestCase, RefreshDatabase, array cache and in-memory SQLite already configured. Retain these defaults; no additional test infrastructure required.
- ADR-022 / ADR-025 implementation verification approach: use Laravel HTTP fakes, response sequences, request-count assertions and simulated clock advancement. Disable unmatched outbound requests and override retry delay to zero in tests only. This avoids external dependencies and unnecessary waits while retaining production configuration. Alternative: live failure testing or real elapsed-time waits; rejected for nondeterminism and slower execution. Trade-off: mocked tests do not independently prove transport-level timeout behaviour or provider availability. Test code supplied; execution and CI results pending. Additional cooldown refinements remain outstanding.

## HTTP client automated verification — 27 November 2026, 16:03 UTC

- PHP lint passed for `tests/Feature/Weather/OpenMeteoClientTest.php`; Pint corrected formatting. Targeted Pest run passed all 17 cases (41 assertions, 1.57 seconds).
- Verified mocked success, transient 503 recovery, retry exhaustion, non-retryable 400/401/403/404, 429 cooldown creation, active cooldown suppression, cooldown expiry, connection failure recovery, malformed JSON, array/null/string responses, provider error envelope and unknown endpoint rejection.
- ADR-022 and ADR-025: targeted automated verification passed for the scenarios above. This supersedes the pending test execution note; full `composer test`, static analysis, CI confirmation and cooldown policy refinements remain outstanding. Mocked tests do not prove actual transport timeout timing or full code coverage. No new ADR or approval change.

## Full validation checkpoint — 27 November 2026, 16:07 UTC

- Initial PHPStan run exhausted the 128 MB PHP memory allowance; subsequent run completed and identified two redundant `is_string()` checks on the Laravel response header. Removed those checks without suppressing static analysis. No permanent memory configuration change made; monitor recurrence.
- Full `composer test` passed: Pint 31 files, PHPStan 24/24 with zero errors, 19 tests and 43 assertions in 1.43 seconds. HTTP client changes and tests remain uncommitted.
- ADR-025 refinement proposal (awaiting user approval): centralise cooldown defaults in configuration; use a provider-wide cooldown conservatively because quota independence is unverified; honour valid Retry-After durations without truncating them, using a bounded default only when the header is missing or invalid. Trade-off: a shared cooldown can temporarily block an otherwise available endpoint; a long provider-specified delay can reduce availability but avoids premature requests. Add regression tests for these behaviours before committing.
- Existing 25 ADRs retained; actual network timeout timing and latest remote CI status remain unverified.

## Accepted cooldown refinements — 27 November 2026, 16:11 UTC

- User explicitly accepted centralised cooldown configuration, provider-wide cooldown scope and honouring valid Retry-After durations. These refine ADR-020 and ADR-025 and supersede the provisional per-endpoint marker and 900-second cap.
- Cooldown is activated only following HTTP 429; normal requests and page reloads are not subject to a cooldown delay. During an active cooldown, fail locally immediately; eligible cached data will be served by the forecast service once implemented.
- Following the user's concern about the original 60-second fallback, use a configurable 10-second fallback as an assistant-selected implementation default pending explicit review. Valid Retry-After delta-seconds or HTTP dates take precedence; do not truncate a valid longer duration. Exponential backoff was proposed but not explicitly accepted and is deferred to keep scope manageable.
- Rationale: central configuration improves maintainability; shared scope conservatively protects potentially shared quotas; respecting Retry-After avoids premature requests. Trade-offs: shared scope may temporarily suppress an otherwise available endpoint, and a short fallback may be insufficient for longer quota windows. Published free-tier allowances previously discussed: 600/minute, 5,000/hour, 10,000/day and 300,000/month; exact weighting of the combined 83-variable forecast request remains unverified. Do not treat the illustrative 8.3-call estimate as confirmed provider accounting.
- Implementation and regression verification pending: shared cache marker, configured fallback, numeric/date Retry-After, missing/invalid header, long duration, expiry and cross-endpoint suppression. Previous full validation (19 tests, 43 assertions) applies to the pre-refinement implementation only.

## Cooldown refinement regression checkpoint — 27 November 2026, 16:18 UTC

- User reports successful PHP lint for all three modified files, Pint (31 files), PHPStan (24/24, zero errors), and all 19 existing tests (43 assertions, 1.46 seconds) after implementing the shared cooldown, configurable 10-second fallback and removal of the 900-second cap.
- Additional policy-specific tests remain pending. Tighten HTTP-date parsing to reject arbitrary relative date strings, use Laravel's clock for deterministic expiry tests, and verify missing/invalid headers, long durations, cross-endpoint suppression and zero duration. These are implementation refinements to ADR-020/025, not new architecture decisions.
- Latest git status output not supplied; commit and remote CI remain unverified. Existing decision history retained.


## HTTP client final validation — 27 November 2026, 16:23 UTC

- Step 5.2 complete for the agreed scope. PHP lint passed, Pint passed across 31 files, PHPStan completed 24/24 with zero errors, and full `composer test` passed 26 tests (67 assertions) in 1.61 seconds, including 24 dedicated HTTP client cases.
- ADR-020/025 refinements verified: shared provider cooldown, configured 10-second fallback for missing/invalid/relative-date headers, 1800-second provider duration, standard HTTP-date handling, expiry and zero-second duration. Use `DateTimeImmutable::createFromFormat(DATE_RFC7231, ...)` with round-trip validation and Laravel clock `now()->getTimestamp()`; the latter resolves PHPStan's ambiguous timestamp property type without suppressing checks. Obsolete HTTP date formats fall back to the configured duration.
- Remaining limitations: mocked tests do not establish actual network timeout timing, concurrent cooldown race behaviour or exhaustive coverage; adaptive backoff deferred. Latest remote CI and next commit not yet verified. Forecast cache/stale fallback remains to be implemented.
- Git status reports modified `app/Services/Weather/OpenMeteoClient.php`, `config/weather.php`, and untracked `tests/Feature/Weather/`. Include the updated decision log in the next commit. Next implementation milestone: Step 5.3 location data model and geocoding service under ADR-014.


## Geocoding implementation checkpoint — 27 November 2026, 16:27 UTC

- HTTP client milestone committed and pushed as `f692aa6`; user confirmed clean working tree and local/remote branch alignment. Remote CI not yet confirmed.
- ADR-014 implementation: immutable `final readonly` LocationData DTO with typed required and nullable optional fields; display label uses name, district, region and country, deduplicating exact repeated names. Local authority retained internally. User confirmed lint, Pint and PHPStan (26/26, zero errors).
- GeocodingService implementation supplied, pending local validation: constructor injection of OpenMeteoClient; trim and length-check search, optional uppercase two-letter country filter (format only); preserve ranking; validate required positive integer ID, nonempty name and finite coordinates within geographic bounds; unavailable or malformed optional values normalised to null or empty postcode list. Reject malformed required data rather than silently dropping locations.
- ADR-019 implementation detail: cache validated provider result arrays rather than PHP objects, reconstruct DTOs on retrieval; SHA-256 of serialised request parameters with lowercase search and schema-versioned provider namespace. Successful results use configured 24-hour TTL, empty results 5 minutes; failed requests are not cached. Rationale: avoid coupling cache contents to DTO class layout and distinguish search variants. Trade-offs: mapping repeated on cache hits; provider schema remains present in cache; country filter validates syntax only. These implementation choices are assistant-selected under accepted architecture, not independently approved.
- Remaining validation: dedicated geocoding tests, live integration, cache expiry and failure handling; missing results treated as empty after basic HTTP client validation, explicit null/non-list results rejected. No new ADR number required.


## Geocoding live verification — 27 November 2026, 16:30 UTC

- Geocoding implementation passed lint, Pint (33 files), PHPStan (26/26, zero errors) and existing regression suite (26 tests, 67 assertions, 1.58 seconds).
- Live worldwide Weymouth search returned 10 results; GB filter returned one DTO (ID 2634202, coordinates 50.61448/-2.45991, elevation -2.0, population 53416, timezone Europe/London, empty postcodes). Auckland label deduplicated repeated administrative names. Negative coordinates preserved.
- Two distinct Massachusetts results (IDs 4955336 and 7257589) share the same display label but have different coordinates. Retain both, keyed by provider ID; consider distinguishing information in frontend. Frontend presentation decision pending.
- Repeated GB search took 17.75 ms, consistent with caching but not definitive proof. Invalid search command produced no visible output; explicit count verification requested.
- Dedicated geocoding tests supplied, execution pending: mapping, null/zero handling, labels, distinct IDs, search parameters, validation, cache separation/expiry, failure recovery and malformed responses. No new ADR required.


## Geocoding final validation - 27 November 2026, 16:33 UTC

- Live invalid search explicitly returned count 0 and exit code 0. Displayed log warnings were from the testing environment.
- Full composer test passed: Pint 34 files, PHPStan 26/26 zero errors, 54 tests (146 assertions) in 2.17 seconds, including 28 geocoding cases and 24 HTTP client cases. Cache reuse, country isolation, positive/empty TTLs, failure recovery and malformed results verified with mocked HTTP.
- Two unexpected untracked files named after the invalid search command appeared; inspect and remove only if confirmed accidental. Their origin is suspected command redirection, not established. Stage explicit intended files only. Commit and remote CI pending.
- Remaining limitations: missing results key is accepted as empty even in an otherwise unexpected JSON object; associative JSON decoding can blur empty object/list distinctions. Consider tightening schema validation later; not covered by current tests. Country code syntax only, duplicate display labels and concurrent cache misses remain limitations.
- No new ADR required; next milestone forecast data model and service.


## Geocoding Git checkpoint — 27 November 2026, 16:35 UTC

- Accidental files inspected and confirmed to contain only PsySH parse errors; removed. Shell redirection is a suspected cause, not conclusively established.
- Geocoding code and tests committed and pushed as `6e27946`; clean working tree and local/remote alignment confirmed.
- Commit contained three code/test files only; updated decision log was not included. Synchronise this document into `docs/PROJECT_DECISIONS.md` and commit separately before proceeding. Remote CI remains unconfirmed.


## Forecast design approval — 27 November 2026, 16:41 UTC

- Geocoding decision log committed and pushed as `d96c11d`; clean working tree confirmed. Remote CI remains unconfirmed.
- ForecastData DTO passed PHP lint, Pint and PHPStan (28/28, zero errors).

### ADR-026 — Flexible measurement arrays within a typed forecast DTO
**Status:** Accepted by user, 16:41 UTC.
**Decision:** Use an immutable ForecastData envelope with typed metadata and configuration-driven measurement arrays, retaining all 83 interval-specific variables and units.
**Rationale:** Avoid 83 individually declared properties; support dashboard, charts and alarms from a common dataset. Alternative: separate strongly typed properties for every measurement. Trade-off: measurement keys and value types require runtime validation against configuration.

### ADR-027 — Row-oriented forecast normalisation
**Status:** Accepted by user, 16:41 UTC.
**Decision:** Transform provider parallel arrays into chronological hourly and daily records. Validate measurement presence, units and matching array lengths before transformation; retain nulls.
**Rationale:** Simplifies charting, rendering and alarm evaluation. Alternative: retain column-oriented provider arrays. Trade-off: transformation overhead and repeated keys in memory.

### ADR-028 — Immutable timestamps and explicit freshness metadata
**Status:** Accepted by user, 16:41 UTC.
**Decision:** Use CarbonImmutable for retrieval and current measurement instants, UTC epoch seconds for hourly instants and sunrise/sunset, and local calendar date strings for daily records, consistent with ADR-018. Retain provider timezone, offset, grid coordinates, current interval and stale flag; expose retrieval age in seconds.
**Rationale:** Avoid accidental timestamp mutation and distinguish observation time from retrieval freshness. Alternative: raw timestamps throughout. Trade-off: conversion and validation complexity, particularly around DST; DST-specific verification remains outstanding.

- Forecast acquisition and transformation will be implemented before forecast caching/stale fallback, to validate each independently. Forecast service tests and live verification remain pending.


## Forecast live validation — 27 November 2026, 16:47 UTC

- ForecastData and ForecastService implemented; PHP lint and Pint passed (36 files), PHPStan 28/28 zero errors, existing 54 tests/146 assertions passed in 2.42 seconds. Dedicated forecast tests pending.
- Live Weymouth GB geocoding-to-forecast pipeline succeeded: 25 current variables, 336 hourly records x 26 variables, 14 daily records x 32 variables (9,209 measurement slots). Daily dates 2026-11-27 through 2026-12-10; Europe/London, offset 0, current interval 900 seconds, stale false.
- Retrieved at 16:46:22 UTC; current measurement 16:45 UTC. Temperature 10.4 C, apparent 7.8 C, wind 10.7 mph, humidity 96%, WMO code 51. Grid coordinates 50.61838/-2.4716797, elevation -1. Units, zero and negative values preserved; daily date strings and hourly epoch timestamps confirmed in sampled records.
- Recorded interactive Tinker duration 571.9 ms includes shell overhead; not a precise API latency measurement. Large output was automatic object display, not an error.
- Remaining: dedicated forecast tests, international/DST verification, expected duration and unit semantics, forecast caching and stale fallback. Daily time unit metadata currently preserves provider unixtime despite normalised date strings; clarify before presentation. No new ADR required. Forecast code and updated log not yet committed.


## Forecast automated validation — 27 November 2026, 16:52 UTC

- Dedicated forecast suite passed 39 tests and 99 assertions in 1.99 seconds. Full composer test passed Pint (37 files), PHPStan (28/28, zero errors), 93 tests and 245 assertions in 3.19 seconds.
- Mocked tests cover request construction, normalisation, null/zero/negative values, units, metadata, international calendar dates, repeated local hour across DST, malformed responses and provider failure propagation. DST test verifies UTC conversion, not actual provider behaviour across a transition. Two-record fixtures do not enforce full forecast duration.
- Proposed implementation correction under ADR-028: explicitly override normalised daily time unit to YYYY-MM-DD at the daily units call site; retain provider units for hourly and solar timestamps. Add assertion and rerun regression before commit. Correction not yet applied or verified. No new ADR required.
- Git status shows modified decision log and three untracked forecast code/test files. Unstaged diff stat excludes untracked files. Stage explicit files after final validation. Remote CI still unconfirmed.


## Forecast final validation — 27 November 2026, 16:56 UTC

- Daily timestamp metadata correction implemented under ADR-028: daily time unit now YYYY-MM-DD; hourly, sunrise and sunset units remain unixtime. Four additional assertions verify this contract.
- Full composer test passed: Pint 37 files, PHPStan 28/28 zero errors, 93 tests and 249 assertions in 3.23 seconds. Forecast suite comprises 39 tests and 103 assertions.
- Step 5.4 complete for agreed acquisition and normalisation scope. Exact unit semantics, full duration enforcement and live provider behaviour across DST remain limitations. Forecast caching and stale fallback remain scheduled for step 5.6.
- Stage the three forecast files and this decision log for the next Git checkpoint. Commit, push and remote CI confirmation pending.


## Weather code mapping — 27 November 2026, 17:00 UTC

- Forecast checkpoint committed and pushed as `ee8eb18`; HEAD and origin/main aligned, working tree clean.
- ADR-009 implemented in WeatherCodeMapper: 28 supported WMO codes, descriptive icon identifiers, day/night variants for clear and partly cloudy conditions, safe fallback for null, unsupported, fractional and non-finite values. Whole-number floats accepted. Icon rendering remains a frontend responsibility; no severity or alarm logic included.
- Dedicated mapper suite: 49 tests, 51 assertions, 0.44 seconds. Full regression: 142 tests, 300 assertions, 3.29 seconds; Pint 39 files and PHPStan 29/29 passed.
- Step 5.5.2 proposed under ADR-018 and ADR-028: reusable ForecastData helpers for local timestamp conversion and upcoming hourly/daily slices, preserving the full dataset. Filter against current time and local calendar date rather than retrieval time so cached data does not expose elapsed records. Default 24 hourly records and configured seven daily records; extended fourteen days. Implementation and validation pending.
- Remote CI and live provider DST behaviour remain unverified.


## Timestamp helper implementation — 27 November 2026, 17:02 UTC

- ForecastData now provides localCurrentTime, localRetrievedAt, localTime, upcomingHourly and upcomingDaily under ADR-018 and ADR-028. Existing regression passed: 142 tests, 300 assertions, 3.35 seconds; Pint 39 files and PHPStan 29/29 passed.
- Hourly selection includes timestamps at or after the current instant, defaults to 24 records, and excludes the elapsed portion of the current hour. Daily selection uses the forecast location's local calendar date and configured default seven days; explicit fourteen-day selection returns only available records. Non-positive limits return empty lists. Full underlying data remains unchanged.
- Dedicated ForecastDataTest proposed with 21 cases using Laravel clock/configuration and synthetic records, including international timezone conversion, DST repeated hour, midnight rollover, null solar timestamps and exhausted forecasts. Dedicated execution pending. Synthetic records do not establish live provider DST behaviour.


## Weather presentation helpers final validation — 27 November 2026, 17:04 UTC

- Dedicated ForecastDataTest passed 21 tests and 34 assertions in 1.50 seconds. Full composer test passed Pint (40 files), PHPStan (29/29, zero errors), 163 tests and 334 assertions in 4.68 seconds.
- Step 5.5 complete: WMO mapping, local timestamp conversion, hourly and daily selection, midnight rollover, null solar timestamps and DST conversion validated. Original forecast records remain unchanged.
- Live provider DST behaviour and frontend icon rendering remain unverified. No new ADR required. Commit and push pending; next step 5.6 implements forecast caching and stale fallback under ADR-019 and ADR-021.


## ADR-029 - Normalised forecast caching (accepted 27 November 2026, 17:06 UTC)

- Commit 18be34f pushed; local and remote aligned, working tree clean.
- Cache validated ForecastData objects rather than raw provider responses to avoid repeated normalisation. Trade-off: serialized objects depend on class structure; bump cache schema version on incompatible changes.
- One entry retained for 7200 seconds from successful retrieval; fresh while age is below 900 seconds. Refresh synchronously thereafter; on WeatherApiException only, return an immutable stale copy if still younger than retention. Never renew expiry or retrieval timestamp on failure.
- Key includes provider, schema version, request parameters and location ID to preserve selected identity; refresh and cache-hit results retain the caller location metadata. Log cache outcomes without coordinates or full payloads. Concurrent refresh duplication remains a limitation.
- Implementation and boundary tests pending.


## Forecast cache implementation proposal - 27 November 2026, 17:08 UTC

- Full replacement files derived from committed source supplied by user. Cache key includes location ID and exact request parameters; immutable forLocation copy preserves caller metadata and original retrieval time. Fresh and stale age checked against retention, including after failed acquisition. Only WeatherApiException triggers fallback; cache writes occur only after validation. Cache hit/update and stale fallback logged.
- PHP syntax validation performed locally; Laravel runtime, regression and dedicated cache tests pending. Existing forecast tests may need cache isolation where multiple calls are made.


## Forecast cache validation — 27 November 2026, 17:12 UTC

- Replacement source files passed syntax, Pint and PHPStan; initial regression 163 tests / 334 assertions in 3.97 seconds.
- Dedicated ForecastCacheTest passed 19 tests / 63 assertions in 1.58 seconds. Full regression passed 182 tests / 397 assertions in 4.40 seconds; Pint 41 files and PHPStan 29/29 with zero errors.
- ADR-029 validated with array cache and mocked provider: fresh hits, exact 900/7200-second boundaries, refresh replacement, server/connection/rate-limit/malformed-response fallback, unchanged retrieval timestamp and expiry, failed initial requests, location identity and configuration/schema isolation, recovery.
- Step 5.6 automated validation complete. Step 5.7 will verify live geocoding-to-forecast integration and persistent database cache across separate processes. Concurrent refresh duplication and live provider DST remain limitations. Commit and remote CI pending.


## Persistent cache integration discrepancy — 27 November 2026, 17:18 UTC

- Runtime configuration confirmed database cache and SQLite. Two separate Tinker processes successfully retrieved Weymouth forecasts (336 hourly and 14 daily records), but retrieval timestamps changed from 17:17:15 to 17:17:48 UTC, age remained zero and measurement hashes differed. Combined geocoding/forecast durations were 884.38 ms and 395.10 ms. Persistent forecast cache acceptance failed; timing alone is inconclusive.
- Root cause not established. Inspect actual local service implementation, cache rows and expiry, and cache hit/update logs before modifying code. Automated array-cache results remain valid but do not establish SQLite persistence. Step 5.7 remains open.


## Persistent cache root cause and proposed ADR-029 revision — 27 November 2026, 17:20 UTC

- SQLite contains a single forecast entry with correct expiry (7151 seconds remaining at inspection). Cache::get returns __PHP_Incomplete_Class rather than ForecastData.
- Local config/cache.php sets serializable_classes=false; CacheManager passes this to DatabaseStore, which invokes unserialize with allowed_classes=false. Array testing cache has serialize=false and therefore bypassed this restriction. Root cause confirmed from installed framework source and runtime evidence.
- Proposed revision (pending approval): preserve default security settings, store validated normalised primitive arrays with UTC epoch timestamps, reconstruct immutable ForecastData using caller LocationData, bump cache schema version, retain existing freshness/retention/fallback semantics. Reject incompatible cache representations as misses. No vendor modifications or unrestricted class deserialization.
- Add database-backed regression coverage with mocked HTTP and repeat separate-process live verification. Existing 182 passing tests do not establish persistent cache compatibility. Step 5.7 remains open.


## ADR-029 revision approved - 27 November 2026, 17:21 UTC

- User approved primitive-array cache representation, explicit DTO reconstruction, UTC epoch timestamps, caller location metadata, schema version 2 and database-backed regression coverage. Preserve Laravel secure deserialization defaults and existing freshness, retention and fallback policy.
- Implementation and validation pending. Shared schema version increment also invalidates existing geocoding entries; they will expire naturally.

## Persistent cache correction implementation — 27 November 2026, 17:24 UTC

- Full replacement files prepared from supplied source: ForecastData primitive-array conversion and reconstruction, ForecastService cache boundary, shared schema version 2, and ForecastCacheTest parameterised across array and database stores. Existing 19 cases retained on each driver; additional incompatible-array regression and full cache representation equality assertion added. Test fixture temperature unit encoding corrected.
- Cache reconstruction checks required top-level fields and timezone; nested arrays rely on previously validated application-owned data, not a second complete provider validation. UTC timestamps stored at second precision. Security configuration unchanged.
- PHP syntax checks passed in PHP 8.4 container. Laravel/Pint/PHPStan and Pest execution pending in user's environment, followed by separate-process live verification. No claim of successful runtime correction yet.


## Persistent cache correction validation — 27 November 2026, 17:30 UTC

- User confirmed all four replacement PHP files pass syntax checks. Dedicated cache regression suite passed 39 tests / 131 assertions in 2.15 seconds, covering array and database stores.
- Full composer test (Pint, PHPStan and regression), live separate-process SQLite persistence verification, commit and remote CI remain pending. Expected full suite: 202 tests / 465 assertions, assuming no other changes.


## Persistent cache full regression validation - 27 November 2026, 17:33 UTC

- Initial composer test stopped at Pint: fully_qualified_strict_types and ordered_imports in ForecastCacheTest.php. Targeted Pint formatting resolved both.
- Full composer test passed: Pint 41 files, PHPStan 29/29 with zero errors, 202 tests / 465 assertions in 5.01 seconds. All 39 cache cases passed, including database-backed scenarios.
- Separate-process live persistence verification, Git commit and remote CI remain pending.


## Persistent cache live acceptance — 27 November 2026, 17:34 UTC

- Separate Tinker processes retrieved Weymouth, GB with identical retrieval timestamp 2026-11-27T17:33:33+00:00 and measurement hash 596ebdec08000a7d87d2afe732f6090234db2482085325a5b027c8a69fac1720. Age increased from 1 to 21 seconds; both results were fresh, temperature 10.5 C, 336 hourly and 14 daily records.
- Combined geocoding and forecast execution time decreased from 590.11 ms to 37.20 ms (93.7% reduction, 15.9x speed ratio). Single observed comparison, not a formal benchmark.
- Persistent cache acceptance passed; ADR-029 revised implementation validated without weakening Laravel deserialization defaults. Backend milestone complete for agreed core scope. Stale fallback validated with mocked provider on both cache drivers; live outage, concurrent refresh and provider DST limitations remain. Git commit/push and remote CI pending; frontend is next.

## Backend milestone closure — 27 November 2026, 17:37 UTC

- Commit 11 `d178e32` (Implement persistent forecast caching and resilient stale data fallback) pushed to origin/main; working tree clean at confirmation. GitHub Actions tests workflow #11 passed in 30 seconds, confirmed by user screenshot.
- Step 5 accepted and closed for core scope. Previously documented limitations remain; no new validation claims beyond the observed workflow success.
- Step 6.1 proposed dashboard: location autocomplete, current conditions and key metrics, 24-hour temperature chart, seven-day default with fourteen-day extension, expandable details, freshness indicators and alerts. Layout and visual direction pending user approval; alerts remain optional scope.

## ADR-030 — Nested weather dashboard and measurement presentation (proposed, 27 November 2026, 17:39 UTC)

- User reaffirmed ADR-013 progressive disclosure: explicitly map all acquired measurements to presentation locations before implementation. Proposed three levels: overview, category/period detail, individual measurements and technical metadata.
- Categories: temperature (actual/apparent/dew point/wet bulb); wind (speed/gust/direction); precipitation (total/rain/showers/probability/hours); humidity (relative humidity and shared dew point/wet bulb); cloud and visibility (total/low/mid/high/visibility); pressure (MSL/surface); sun and UV (UV/clear-sky UV/sunshine/daylight/sunrise/sunset/day-night); snow (snowfall/depth). WMO codes drive conditions and icons across periods. Preserve every configured current/hourly/daily field, including daily min/max/mean/sum/dominant variants as applicable.
- Proposed category cards drill into relevant current, hourly and daily data; hourly and daily overview rows also drill into selected periods using the same measurement groups. No additional provider requests for drill-down within retained forecast. Metadata panel distinguishes selected location from model grid, local measurement time from retrieval time, and freshness from forecast validity. Display unavailable values explicitly; never substitute zero. Label accumulation intervals and probability periods; do not imply precipitation probability is rain-only.
- Prefer reusable detail views and one expanded category at a time initially; avoid separate bespoke charts for every measurement. Proposed 24-hour default, seven-day default and fourteen-day extension, with available hourly records bounded by actual dataset. Theme, exact layout, chart implementation and interaction details pending approval. No implementation yet. Trade-off: comprehensive access increases UI/testing scope; progressive disclosure and reusable components reduce clutter and effort.

## ADR-030 refinement — category summary values (27 November 2026, 17:41 UTC)

- User approved the nested information architecture in principle and requested representative values in each collapsed detailed forecast category title, so information remains useful without expansion. Current view uses current values; selected hour uses hourly values; selected day uses provider daily means where available and meaningful aggregates otherwise.
- Proposed summary mapping: temperature actual/mean; wind speed and direction/mean speed and dominant direction; precipitation interval amount/daily total and mean hourly probability; humidity relative/mean; cloud and visibility current/mean; pressure MSL current/mean; sun and UV current UV/daily maximum UV and daylight duration; snow interval snowfall/daily total. Label probability mean as mean hourly probability, not probability of any precipitation during the entire day. Label units, accumulation periods and aggregate types explicitly. Preserve null as unavailable.
- Expanded categories expose all relevant configured fields for the selected period. No new provider measurements or API calls required. Avoid arithmetic averaging of direction or replacing meaningful totals and maxima with means. Proposed exact mapping and visual interaction remain subject to confirmation; no implementation yet.

## ADR-030 refinement — unified period-driven dashboard (27 November 2026, 17:43 UTC)

- User requested retaining the seven-day forecast as a drill-down navigation control and three Current / Hourly / Daily modes that change the main weather display at the top, rather than separate disconnected detail panels.
- Proposed interaction: Current restores latest current conditions; clicking a forecast day selects that local calendar date and switches to Daily, showing provider daily aggregates in the hero and all category summaries/details. Hourly uses the selected date and an available hour selector/timeline, updating the same hero and categories. Seven-day navigation remains visible in all modes, with fourteen-day extension. Preserve selected date when switching between Hourly and Daily; default to today on initial load. Current is independent of selected forecast date.
- Use actual provider mean temperature, not midpoint of min/max; prototype values are illustrative only. Hourly choices must reflect available timestamps and local timezone, including repeated/missing DST hours. Daily and hourly navigation use already acquired data without additional provider requests. Independently expandable categories proposed; no implementation yet. Exact placement of hourly timeline and responsive layout pending confirmation.

## ADR-030 refinement — selectable forecast tiles (27 November 2026, 17:44 UTC)

- User approved the current-conditions presentation and requested compact visual forecast tiles for Hourly and Daily modes, following the supplied seven-day card reference. Each tile provides basic information and selects the period for the shared expandable detail categories below.
- Proposed hourly tiles: local hour, weather icon, temperature and hourly precipitation probability; horizontally scrollable next 24 hours by default. Daily tiles: day/date, icon, maximum/minimum temperature and explicitly labelled maximum hourly precipitation probability; seven days by default with fourteen-day extension. Retain seven-day navigation in Current and Hourly modes; avoid duplicate daily navigation in Daily mode.
- Selected tile visibly highlighted; keyboard-accessible controls and responsive scrolling. Use provider means in daily category summaries, not midpoint of extrema. Hourly chart retained as complementary view. Prototype values are illustrative and not complete measurement mappings.
- Pending confirmation: hourly navigation beyond the next 24 hours and how selected future dates carry into Hourly mode. No code changes yet. Trade-off: tiles improve scanability but require responsive overflow and clear date/time labels, especially across midnight and DST.


## ADR-030 approval — 27 November 2026, 17:45 UTC

- User explicitly approved the final selectable-tile dashboard prototype. Step 6.1 complete. Current hero, Hourly tiles and chart, Daily seven-day tiles with fourteen-day extension, shared eight expandable categories with period-specific summaries and full relevant measurement detail, independent expansion, and responsive scrolling are accepted. Retain daily navigation in Current and Hourly modes without duplicating it in Daily mode.
- Actual provider means and aggregates must be used; prototype numbers are illustrative. No frontend implementation yet.

## ADR-031 — Livewire state and presentation architecture (approved direction, 27 November 2026, 17:46 UTC)

- User authorised proceeding with the proposed single Livewire dashboard and reusable Blade presentation components. Centralise location, mode, selected local date, selected UTC hour, forecast length and search state. Keep full forecast DTO server-side; do not expose the entire dataset as public Livewire state. Reuse existing backend services and cache; no duplicate provider client or persistence layer.
- Proposed implementation: resolve forecast through a server-side computed accessor per Livewire request; cache freshness policy remains authoritative, so navigation may refresh an expired forecast but does not require a provider call while fresh. Use validated/locked location selection and validate period identifiers against available records; never trust browser-supplied coordinates. Presentation layer maps measurements, units, summaries and icons; Blade components render them. Local expansion state can remain client-side. Avoid storing forecast DTO in session or browser snapshot.
- Hourly navigation default next 24 available hours; selecting a future day in Daily then switching to Hourly shows available hours for that local date. Use UTC timestamp identity, local display labels and offset disambiguation for repeated DST hours. Current mode independent of selected date. These detailed implementation defaults are proposed for verification, not separately approved requirements.
- Trade-offs: one component simplifies synchronisation but needs disciplined separation; server-side cache lookup per interaction adds small overhead and may trigger refresh at freshness boundary; client-side expansion avoids unnecessary round trips. Inspect installed Livewire starter structure before choosing exact component file conventions. Test selection, invalid inputs, missing values, stale/error states and provider call counts. No code changes yet.


## ADR-031 implementation refinement — frontend scaffold (27 November 2026, 17:48 UTC)

- Inspected the existing frontend: default welcome Blade view, home Route::view, Tailwind 4 configuration, empty JavaScript output and no existing Livewire components. Retain the home route and existing asset configuration initially; replace the welcome view with the application shell and embed a single dashboard component. No additional frontend dependencies initially.
- Implement incrementally: scaffold, location selection, current conditions, measurement presenter/categories, daily tiles, hourly tiles, chart, technical/status details and responsive validation. Validate and commit meaningful milestones. Prefer conventional class-based Livewire component for explicit separation and testability, subject to installed generator support. No implementation or test results yet.

## ADR-031 validation — application shell (27 November 2026, 17:58 UTC)

- Generated conventional class-based WeatherDashboard and Pest test using Livewire 4 `make:livewire WeatherDashboard --class --test`. Retained home route and conditional Vite loading; embedded component in replacement welcome view with Livewire assets and provider attribution.
- Initial mode is Current; locked mode property and allowlisted setMode action reject unsupported modes. Search input and empty state are present; geocoding is not yet connected. Provisional light slate and sky-blue styling, no new dependencies or fabricated weather data.
- Local composer test passed: 210 tests, 480 assertions, 6.47 seconds; eight new dashboard tests passed, Pint passed 43 files after formatting fixes, PHPStan 30/30 with zero errors. Browser appearance and actual client interaction remain pending. No commit yet for this milestone.

## ADR-031 refinement — location selection (27 November 2026, 18:00 UTC)

- User confirmed browser navigation works; application shell accepted. Proceed with geocoding integration.
- Use debounced search updates, configured minimum/maximum lengths, existing GeocodingService and its cache. Keep only small primitive location records in locked Livewire properties; select by provider ID from the server-generated result list, not browser-supplied coordinates. Retain selected location while editing a new search; replace only after valid selection. Clear suggestions on selection. Distinguish no matches from provider failure; no raw exception details displayed. Forecast retrieval follows in the next step.
- Trade-off: location metadata is visible in the Livewire snapshot but protected against client modification; no full forecast data is stored there. The 500 ms Blade debounce matches the current configuration; keep them aligned. Implementation and tests pending.


## ADR-031 validation — location search (27 November 2026, 18:02 UTC)

- Implemented debounced search, locked location suggestions and selection, validated provider IDs, friendly error messages and selected-location retention. Local composer test passed: 221 tests, 513 assertions, 6.04 seconds; all eleven new geocoding integration tests passed. Pint passed 43 files after formatting fixes; PHPStan 30/30 with zero errors.
- Browser search and selection, actual debounce timing and direct locked-property tampering remain unverified. Forecast retrieval is not yet connected. Commit after successful browser validation.

## ADR-031 acceptance — location search (27 November 2026, 18:03 UTC)

- User confirmed all six manual browser checks passed: minimum search length, worldwide suggestions, Weymouth selection, location retention across modes, no-match message and switching to London. Steps 6.3.1 and 6.3.2 accepted.
- Exact debounce timing and direct locked-property tampering remain unverified. Forecast retrieval is next. Commit the validated frontend milestone and decision log before proceeding.

## ADR-031 refinement — current weather presentation (27 November 2026, 18:07 UTC)

- Commit c5622c0 pushed successfully; remote and local main aligned. CI result not yet confirmed.
- Reconstruct selected LocationData from locked primitive metadata; use a request-scoped Livewire computed forecast accessor and existing ForecastService. No full forecast in public state. Render friendly error and retry through normal cache policy; stale warning and separate retrieval/measurement times. Introduce WeatherPresenter and reusable Blade current card/icon. Match today's high/low by location calendar date. Missing measurements display Unavailable. Initial icons are simplified SVGs, with some related conditions sharing artwork; condition descriptions remain distinct.
- Preserve existing frontend tests, update forecast-aware fixtures and request counts, add error/retry coverage. Implementation package prepared; local Laravel tests and browser validation pending. No new dependencies.


### ADR-031 implementation validation — computed property typing

- Pint passed 44 files after formatting corrections. PHPStan reported an undefined dynamic `$forecast` property in WeatherDashboard; automated runtime tests have not yet run.
- Add a class-level `@property-read ForecastData|null $forecast` annotation to describe the existing Livewire computed property. Retain request-scoped computed caching and static analysis checks; no runtime or backend changes intended. Validation pending rerun.

### ADR-031 automated validation — current weather (27 November 2026, 18:15 UTC)

- PHPDoc correction validated: Pint passed all 44 files, PHPStan completed 31/31 with zero errors, and all 222 tests passed (518 assertions, 6.02 seconds). Existing regression tests and new forecast error/retry test passed.
- Live browser rendering, visual layout, real provider measurements and location switching with forecast data remain pending manual validation. No additional architecture changes required at this stage.

### ADR-031 refinement — expandable categories (27 November 2026, 18:17 UTC)

- User confirmed current display is populated; hourly/daily placeholders expected. Detailed browser checklist not independently confirmed.
- Implement ADR-030 measurement mapping in WeatherPresenter for current, hourly and daily records. Use native independent HTML details/summary disclosures, keyboard-accessible without new dependencies or server requests. Current mode wired first; hourly/daily selection follows. Daily summaries use provider means/totals/maxima and dominant direction; probability explicitly refers to hourly probability. Visibility converted to km, solar duration to minutes/hours, missing values remain unavailable. Display units follow existing fixed acquisition configuration.
- Add regression coverage for current category labels. PHP syntax checked; full Laravel tests, daily/hourly presenter-specific coverage and browser disclosure behaviour remain pending. No backend changes.

### ADR-031 automated validation — expandable categories (27 November 2026, 18:19 UTC)

- User reported 223 passing tests, 527 assertions, 5.92 seconds. The category regression test and existing suite passed. Browser disclosure behaviour and hourly/daily presentation-specific coverage remain pending. Proceed to daily forecast tiles after browser confirmation.

### ADR-031 refinement — daily forecast (27 November 2026, 18:21 UTC)

- User confirmed expandable category browser checks passed. Implement daily tiles in all modes, with seven-day default and fourteen-day toggle, validated date selection from available cached records, locked selection state, and provider daily measurement categories. Selecting a location resets date and range; changing range resets selected date to first available day. Use horizontal scrolling for compact screens, shared icons and presenter, and explicitly label maximum hourly precipitation probability. No backend changes. PHP syntax checked; integration and browser validation pending. Fixture currently contains two days, so full fourteen-day rendering still needs validation.

### ADR-031 acceptance — daily forecast (27 November 2026, 18:24 UTC)

- User confirmed daily forecast appearance and reported 224 passing tests, 535 assertions, 6.17 seconds. Current weather, expandable categories and daily forecast accepted for the next Git milestone.
- Full fourteen-day layout has user visual approval at feature level, but individual range-boundary and mobile checks were not explicitly reported. Automated fixture remains limited to two days. Hourly tiles, chart and remaining frontend validation are next.
- Commit the three validated frontend features together with this decision log before further implementation.

### ADR-031 refinement — hourly forecast (27 November 2026, 18:26 UTC)

- Implement next 24 upcoming hourly records by default, with date-specific upcoming hours when switching from a selected daily tile. Provide return to next 24 hours. Use UTC epoch selection identity, location-local time with timezone abbreviation, validated available record selection, shared condition icons and categories. Reset hour selection on location/date/range change. No extra provider request while cache is fresh. Elapsed hours excluded; empty periods explicitly labelled.
- Add regression test for selection, invalid hour rejection, empty selected date and return to upcoming hours. PHP syntax checked; Laravel and browser validation pending. Chart remains separate. Commit 13 requested previously; push not yet confirmed.

### ADR-031 automated validation — hourly forecast (27 November 2026, 18:29 UTC)

- User reported 225 passing tests, 543 assertions, 6.36 seconds. Hourly selection regression coverage and existing suite passed. Browser checks for hourly navigation, date selection and horizontal scrolling remain pending. Proceed to the temperature chart after browser confirmation.

## ADR-032 — Forecast navigation and horizon uncertainty (27 November 2026, 18:38 UTC)

Status: Accepted by user; implementation prepared, validation pending.

- Preserve Hourly or Daily mode when selecting a date tile; Current transitions to Daily. Highlight the selected date in Hourly. Retain the Current tab and rename the conditional hourly reset control to “Back to next 24 hours”. Rationale: fewer clicks and consistent navigation. Existing range-change reset retained.
- Retain all available 14-day hourly predictions rather than imposing an arbitrary cutoff. Forecast availability is not a guarantee of accuracy. Show an uncertainty advisory for days 4–7 and an extended-range advisory for days 8–14 in selected daily/hourly details. Today is day 1, based on the forecast location calendar. These are UX thresholds, not calibrated accuracy statistics; no numeric confidence claims. No advisory in Current mode.
- Reuse existing cached data and shared Blade advisory component; no provider or dependency changes. Extend navigation regression and add four advisory boundary cases. PHP syntax checked; full Laravel tests and browser verification pending. Previous hourly browser feedback identified navigation improvements; commit 13 push remains unconfirmed.

### ADR-032 acceptance — navigation and forecast uncertainty (27 November 2026, 18:41 UTC)

- User reported 229 passing tests, 550 assertions, 6.53 seconds, and confirmed the UI looks good and behaves as expected. Accept hourly navigation refinements and uncertainty advisories at feature level.
- No reported regressions. Individual mobile and extended-range live boundary checks remain outstanding; commit 13 push not confirmed. Next step: hourly temperature chart.

### ADR-033 — Hourly temperature chart (27 November 2026)

- Accepted: lightweight server-rendered SVG rather than a chart dependency. Plot temperature and apparent temperature from the same selected hourly records as the tiles. Shared Celsius scale, local time labels, point tooltips, explicit empty state, missing values break lines rather than implying zero or interpolation. Horizontal scrolling on narrow screens; exact values remain available through hourly tiles. No additional provider calls or client state. Extend existing hourly regression assertions; integration and browser validation pending.

### ADR-033 validation — hourly chart (27 November 2026, 18:45 UTC)

- User confirmed all working. Exact automated counts not supplied; browser/feature acceptance reported.

## ADR-034 — Technical information and data status (27 November 2026)

- Implement a collapsed native HTML disclosure panel in all forecast modes, reusing the existing forecast DTO. Display provider, freshness/fallback status, retrieval age and timestamps, measurement interval, record counts, timezone and provider offset, selected location and grid coordinates/elevations, administrative metadata and population/postcodes. Distinguish model estimates from station observations and retrieval age from model run age. Missing optional metadata explicitly unavailable. No extra API calls or dependencies. Do not expose cache keys or raw responses.
- Retain existing stale warning and retrieval footer. Add a feature regression checking metadata rendering and provider request count. Implementation prepared; Laravel and browser validation pending.

### ADR-034 validation — technical information (27 November 2026, 18:53 UTC)

- User confirmed the technical information panel works and automated tests passed. Exact test and assertion counts were not supplied; do not record projected counts as observed results.
- Proceed to responsive layout and keyboard usability checks before the next Git checkpoint. No CSS changes proposed unless validation identifies a defect. Commit 13 push remains unconfirmed.

### ADR-031 responsive validation — 27 November 2026, 19:07 UTC

- Initial iPhone 16 emulation (393 × 852) showed a dashboard occupying approximately half the visible width. Switching Current → Daily resolved the issue. User subsequently reported layout working, including rotation between portrait and landscape.
- Accept responsive browser validation on the reported emulated device. Root cause of the transient issue remains unconfirmed; do not introduce speculative CSS changes. Revisit if reproducible. Physical-device and explicit keyboard accessibility verification have not been confirmed.
- Main frontend feature implementation accepted. Proceed to Git checkpoint before optional features; previous commit 13 push remains unconfirmed.


## ADR-035 — Browser-local favourite locations (27 November 2026, 19:12 UTC)

- Accepted by user. Commit 14 `7843b12` pushed, clean tree; commit 13 `c5ba505` confirmed. CI status not supplied.
- Store up to ten favourites in versioned browser localStorage using existing Livewire Alpine runtime. Deduplicate by provider ID; add/remove locally without server calls; select via authenticated encrypted location token issued by Laravel, rather than trusting arbitrary client coordinates or repeating geocoding. Normal forecast cache policy applies on selection.
- Trade-offs: no cross-device sync; clearing browser storage removes favourites; APP_KEY rotation invalidates saved tokens. Storage failures show a message. No new dependency, account or database table. Browser persistence and interaction require manual verification; add server tests for valid and tampered tokens. Implementation pending Laravel validation.

### ADR-035 revision — integrated favourites search (27 November 2026, 19:17 UTC)

- User confirmed initial favourites functionality works but rejected the separate section above weather data. Accepted integrated search dropdown: show saved favourites on focus, filter locally from first character, matching favourites first followed by provider results from three characters, deduplicate by provider ID, independent star toggles, close on selection/outside click/Escape. Retain selected-location star, encrypted tokens, ten-item limit and existing storage key.
- Replace dashboard Blade view only; Alpine state moves to dashboard root. Existing standalone component becomes unused and can be removed. Dropdown overlays content instead of shifting weather data. Server-side selection and tests unchanged. Browser and Laravel validation pending; existing saved favourites should remain compatible.

### ADR-035 refinement — search dropdown focus lifecycle

- User requested favourites only while interacting with the location search. Close the dropdown when focus leaves the search region, including keyboard navigation, in addition to existing outside-click and Escape handling. Keep pointer interactions with result and star buttons from prematurely blurring the input; allow keyboard focus within the dropdown for accessibility. No storage, token or API changes. Browser and regression validation pending.

### ADR-035 validation — 27 November 2026, 19:24 UTC

- User confirmed the revised favourites dropdown and focus behaviour are working. Accept the revised browser interaction; no further presentation changes required at this stage.
- Exact regression test results following the focus fix have not been supplied. Retain automated validation as unconfirmed until the test output is available. Proceed to automatic location detection design (FR-14).

## ADR-036 — Permission-based browser geolocation

- Accepted by user. Request browser geolocation only on explicit button activation. Use native browser API with low accuracy preference, ten-second timeout and one-minute cached position allowance. Handle denial, unavailable service and timeout with manual search fallback.
- Validate finite latitude/longitude and geographic ranges server-side. Round to three decimal places (approximately 111 m latitude resolution) to reduce precision and cache fragmentation. Use synthetic ID 0 and label Current location; forecast cache includes coordinates. No reverse geocoding dependency. Do not offer favourites for synthetic locations to avoid identity collisions and persistent coordinate storage. Existing named favourites unchanged.
- Browser permission requires secure context (HTTPS or localhost). Coordinates are sent to application and weather provider on cache miss; no explicit location tracking or background polling. Add tests for valid coordinates and invalid range. PHP syntax and archive integrity checked; Laravel and browser validation pending.

### ADR-036 validation and backlog decision — 27 November 2026, 19:30 UTC

- User confirmed browser geolocation works. Retain the label Current location for this release. Reverse geocoding was considered to display a recognisable town or city but explicitly deferred by the user as a nice-to-have, not a defect or release blocker.
- Backlog item: optional reverse geocoding of detected coordinates, subject to provider selection, privacy and usage-policy review, independent caching and graceful fallback to Current location. Continue forecasting at detected coordinates rather than town-centre coordinates. Avoid introducing an additional provider dependency for this release.
- Exact automated regression results remain unconfirmed. Proceed to FR-15 weather alarming.

## ADR-037 — Defer weather alarming and prioritise release readiness (27 November 2026, 19:32 UTC)

- Accepted by user: defer FR-15 weather alarming to the nice-to-have backlog. Retain its original Could Have priority and mark it deferred, not implemented; no change to mandatory requirements.
- Future scope includes configurable temperature, wind gust and precipitation thresholds, current and forecast evaluation, severity and expected occurrence time, local persistence, stale-data and forecast-horizon handling, and boundary tests. Clearly distinguish application-generated alarms from official weather warnings. Background monitoring and email notifications are further optional enhancements.
- Rationale: prioritise final regression testing, usability, reproducible installation, documentation and submission over additional optional functionality. No alarm code or dependencies added. Reverse geocoding remains deferred under ADR-036.
- Core feature development ends here. Final automated results for favourites and geolocation, release QA, Git checkpoint, CI confirmation, README and submission archive remain outstanding.

### Release regression validation — 27 November 2026, 19:33 UTC

- User supplied full `composer test` summary: 234 tests passed, 572 assertions, 6.69 seconds. No test failures reported. This closes the outstanding automated regression validation for ADR-035 and ADR-036.
- Dependency audits, production asset build, final source review, Git/CI confirmation and clean installation verification remain pending. No claim of security sign-off at this stage.

### Release dependency and build validation — 27 November 2026, 19:35 UTC

- User supplied successful composer audit (no security vulnerability advisories), npm audit (zero vulnerabilities), and production build (224 ms). These checks cover known dependency advisories, not a comprehensive application security audit.
- Production build emitted a non-blocking warning about optional Fontaine font fallback optimisation. Recommendation: retain existing dependencies rather than add a package solely to remove this warning; no build changes made.
- Git status and diff summary showed four expected modified files: dashboard class, dashboard Blade view, feature tests and decision log. Full source review, CI and clean installation verification remain outstanding.
