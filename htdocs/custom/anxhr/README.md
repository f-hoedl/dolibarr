# ANX HR for [Dolibarr ERP & CRM](https://www.dolibarr.org)

HR module for Austrian companies.

## Features

- **Employment contracts**: historised contracts (hours, working time model, collective agreement, probation, fixed term, home office).
- **Deadlines**: probation end, fixed-term end, certificates, permits, reviews with reminders (agenda event and notification).
- **On/offboarding**: checklists from templates, equipment, keys, tokens and system access handovers.
- **HR tab on the user card** with contract, deadlines, emergency contacts, handovers, checklists and current month time balance.
- **HR cockpit** with open deadlines, checklists in progress, open time corrections, monthly periods to approve and missing clock-outs.
- **Time tracking** according to the Austrian working time act (AZG): clock in/out, breaks, daily and weekly limits, rest periods, monthly closing.
- **Travel allowances** (Austrian Taggeld/Naechtigungsgeld and mileage) inside the core expense report module.

Leave requests and expense reports remain in the Dolibarr core modules (`holiday`, `expensereport`).

## Installation

1. Copy the directory `anxhr` into `htdocs/custom/` of your Dolibarr installation.
2. Go to *Home - Setup - Modules*, find **ANX HR** in the family *Human Resources* and enable it.
3. Grant the permissions to the user groups (employee, supervisor, HR, accounting).
4. Check the settings in *Setup - Modules - ANX HR*.

## Scheduled jobs

| Job | Default |
|---|---|
| Send deadline reminders | enabled, daily |
| Compute time days of the previous day | enabled, daily |
| Create monthly time periods | disabled |
| Delete expired time records (retention) | disabled |

## Settings

| Constant | Default | Meaning |
|---|---|---|
| `ANXHR_DEFAULT_KV` | `SWOE` | Default collective agreement code |
| `ANXHR_TIMEZONE` | `Europe/Vienna` | Company time zone for working days, night work, Sundays and public holidays |
| `ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAY` | `0` | Own correction of the current day applied without approval |
| `ANXHR_ALLOW_SELF_APPROVAL` | `0` | Supervisors / HR may approve their own corrections and monthly sheets (disables the four eyes principle) |
| `ANXHR_PERIOD_AUTOCREATE_DAY` | `1` | Day of month on which monthly periods are created |
| `ANXHR_RETENTION_YEARS` | `7` | Retention of time records after end of employment |

Planned, not implemented yet: `ANXHR_VACATION_IN_HOURS` (vacation entitlement in hours instead of days). It is therefore not offered in the setup page.

## Licenses

GPLv3 or (at your option) any later version. See <https://www.gnu.org/licenses/>.

## Employee PWA

A mobile web app for employees is served from `/custom/anxhr/pwa/` (no build step, plain HTML/JS/CSS).
It uses the REST API endpoints of this module (`/api/index.php/anxhr/...`, class `class/api_anxhr.class.php`):

- `GET anxhr/me`: state (`in`/`break`/`out`), worked/target minutes today, month balance, today's entries, last 7 days.
- `POST anxhr/clock` `{type: in|out|break_start|break_end, homeoffice: 0|1, client_time?: 'Y-m-d H:i:s'}`: same rules as `time_clock.php` (shared `TimeEntry::clockForUser()`), entries get source `pwa`. `client_time` (company time zone) is used for actions queued offline, accepted up to 48 h back. Answers 409 `ActionNotAllowed` / `DayLocked`.
- `GET anxhr/vault`: own documents visible to the employee (unread flag). Downloads stay on `vault.php` / `vault_download.php` with the browser session (the download link needs the session CSRF token, which an API key cannot provide).

Requirements: module *API REST* enabled, rights *anxhr time own* (clock) and *anxhr vault own* (documents).

Sign in:
- With login and password: the PWA calls the core `POST /api/index.php/login`. This core API is disabled by default; enable it with the constant `API_ENABLE_LOGIN_API = 1` (Home > Setup > Other setup).
- SSO users (or when the login API stays disabled): generate an API key on the own user card and enter it under "Mit API-Schlüssel anmelden". The key is stored in the browser's localStorage; "Abmelden" removes it.

Install to home screen: open `https://<your-dolibarr>/custom/anxhr/pwa/` in the phone browser (HTTPS required for the service worker), then Android/Chrome: menu > "Zum Startbildschirm hinzufügen"; iOS/Safari: share button > "Zum Home-Bildschirm".
Offline: the app shell is cached; clock actions done offline are queued in localStorage and sent with their original time when the device is online again.
