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

**Decision:** Configurable 14 calendar days including today, five days displayed by default, extendable to 14; reuse server-side cached dataset. Retains five-day UI requirement. **Reasoning:** Greater flexibility with measured payload under 48 KiB. **Alternatives:** Five or seven days only. **Trade-offs:** Lower long-range forecast confidence, greater data volume and quota considerations.

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

## API investigation evidence

- Forecast response supplied by user: current 15:00 Europe/London on 27 November 2026, interval 900 seconds; 120 hourly records starting local midnight and five daily dates including today. Current 10.2°C, humidity 95%, WMO 51, wind 12.3 mph. Separate unit metadata; wind unit returned as `mp/h`. Returned coordinates 50.61837, -2.443344 differ from requested 50.61, -2.45. Generation time approximately 0.55 ms is provider processing time, not network latency.
- Initial forecast request validated only a subset of approved fields. Expanded forecast request subsequently validated; forecast error cases, DST and null handling remain unverified. Geocoding edge cases are validated below.
- Git checkpoint: user confirmed decision-log commit `2312260` pushed to origin/main with clean working tree. This revision extends the available local working copy; commit/push pending.

- Geocoding success response supplied by user at 15:16 UTC: five Weymouth matches across US, GB, AU and NZ; first result Massachusetts, second Dorset (ID 2634202, coordinates 50.61448, -2.45991). Optional fields vary: GB lacks postcodes; NZ lacks population and admin3. Negative elevation and coordinates occur. This validates ADR-011 explicit location selection. Error and empty-result behaviour subsequently validated at 15:19 UTC.
- Accepted location data contract (ADR-014): require provider ID, name and coordinates; retain nullable country, country code, administrative regions, timezone, elevation and population; missing postcodes as empty array. Deduplicate display label regions. Country flags proposed for presentation. Contract and naming accepted at 15:20 UTC.

- Geocoding edge-case tests supplied by user at 15:19 UTC: `Weym` returns five fuzzy matches (including Weuimél), unknown `zzzinvalidxyz` and one-character `W` return successful responses containing only `generationtime_ms` (no `results` property); omitted `name` returns HTTP 400 with `error: true` and a textual `reason`; `countryCode=GB` returns the Dorset Weymouth only. All five test scenarios exercised successfully.
- Accepted implementation refinements under ADR-014: preserve provider ranking initially; support optional country filter in service with worldwide default; distinguish successful empty results from transport/HTTP errors; default absent results to an empty array only after successful response and schema checks; validate minimum search length before requests; log technical errors without exposing raw provider messages. These refinements and the location contract were accepted at 15:20 UTC. Tests should cover absent results, fuzzy matches, country filtering, malformed responses and HTTP failures.

- Comprehensive Weymouth forecast test supplied at 15:27 UTC: HTTP 200, 469 ms end-to-end, 48,786 bytes decoded JSON, provider generation 3.8159 ms. All 25 current, 26 hourly (336 timestamps) and 32 daily (14 timestamps) fields present; 9,209 values, zero nulls. Europe/London offset 0; current 15:15 UTC, interval 900 seconds; hourly 27 Nov 00:00 to 10 Dec 23:00 UTC; daily 27 Nov to 10 Dec inclusive. Units confirmed including snowfall cm, snow depth m, visibility m, sunshine seconds, wind mp/h, sunrise/sunset Unix time and dimensionless empty unit strings. Single-location snapshot, not a global availability or latency guarantee.

- International timezone tests supplied at 15:32 UTC: Weymouth (offset 0), New York (-18000), New Delhi (+19800), Sydney (+39600) all returned 336 hourly and 14 daily timestamps. Current UTC 2026-11-27 15:30 in all cases. First hourly/daily UTC boundaries respectively 27 Nov 00:00, 27 Nov 05:00, 26 Nov 18:30 and 27 Nov 13:00, each corresponding to local midnight. Sydney local current time 28 Nov 02:30; sunrise/sunset local 05:38/19:48. All four cases consistent with UTC instants and local calendar boundaries. Actual DST transition not tested.

## Decision history

- 27 November 2026, 15:34 UTC: User explicitly accepted ADR-019 through ADR-022. Initial architecture review complete; proceed to backend implementation. All policies remain subject to implementation and verification.

- 27 November 2026, 15:32 UTC: User explicitly accepted ADR-018. DST-transition validation remains outstanding. ADR-019 through ADR-022 proposed for cache, HTTP resilience, failure handling and logging.

- 27 November 2026, 15:29 UTC: User explicitly accepted ADR-015, ADR-016 and ADR-017 (83-variable contract, 14-day acquisition and weather metadata). Implementation and international timezone validation remain outstanding.

- 27 November 2026, 15:20 UTC: User explicitly approved all six geocoding refinements (location data contract, search ranking, country filtering, empty results, input validation and error handling). Recorded together as ADR-014; prior ADRs retained.

- 27 November 2026, 15:14 UTC: User explicitly approved ADR-006 through ADR-011 without additional comments. All six changed from Proposed to Accepted; their rationale, alternatives and trade-offs are retained. Acceptance does not imply implementation or completion of outstanding technical investigations.

## Open decisions and investigations

- Verify Unix timestamp and DST behaviour, daily local dates and detailed normalised data contracts under accepted ADR-008 and ADR-010; approved measurement selection is recorded in ADR-012.
- Expanded forecast request and geocoding edge cases completed; forecast error cases and actual DST-transition tests remain; non-zero timezone tests passed.
- ADR-001 through ADR-022 are accepted; exact expanded register validated. Implementation and automated verification pending.
- Detailed service/component structure and validation strategy.
- Implement and test accepted cache lifetime, failure behaviour, retry policy and logging (ADR-019 through ADR-022).
- UI design, accessibility and forecast presentation.
- Optional features, including alarm semantics and persistence.
- CI Node version alignment and final documentation/submission process.
