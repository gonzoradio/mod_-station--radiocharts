# CIWV Radio Charts — Joomla 4/5 Module & Plugins

A Joomla 4/5 module and companion task plugins that pull weekly radio playlist
data from four sources, store it in the Joomla database, and display it on the
front end with week-over-week position comparison.

---

## Data Sources (in order of priority)

| # | Source | Plugin |
|---|--------|--------|
| 1 | **National Playlist** — Mediabase | `plg_task_ciwv_radiocharts_mediabase_national` |
| 2 | **Local Station Playlist** — Mediabase (CIWV spins) | `plg_task_ciwv_radiocharts_mediabase_local` |
| 3 | **Streaming Data** — Luminate | `plg_task_ciwv_radiocharts_luminate` |
| 4 | **PD Picks** — Music Master CSV export | `plg_task_ciwv_radiocharts_musicmaster` |

All data is stored in the shared `#__ciwv_radiocharts` database table and
identified by `source` so each chart can be displayed and compared
independently.

---

## Package Contents

```
mod_ciwv_radiocharts/                   ← Joomla site module (display)
│
├── mod_ciwv_radiocharts.xml            Module manifest (install/uninstall SQL, params)
├── mod_ciwv_radiocharts.php            Module entry point
├── src/
│   ├── Dispatcher/Dispatcher.php       Gathers data; passes it to the template
│   └── Helper/RadiochartsHelper.php    DB queries, upsert, week-date helpers
├── tmpl/default.php                    Front-end chart table template
├── language/en-GB/                     English language strings
└── sql/
    ├── install.mysql.utf8.sql          Creates #__ciwv_radiocharts table
    └── uninstall.mysql.utf8.sql        Drops the table on uninstall

plugins/task/
├── ciwv_radiocharts_mediabase_national/  ← Mediabase national chart importer
├── ciwv_radiocharts_mediabase_local/     ← Mediabase CIWV local-spins importer
├── ciwv_radiocharts_luminate/            ← Luminate streaming data importer
└── ciwv_radiocharts_musicmaster/         ← Music Master CSV importer
```

Each plugin folder follows the Joomla 4/5 task-plugin structure:
```
<plugin>/
├── <plugin>.xml          Manifest (namespace, files, languages)
├── services/provider.php DI service provider
├── src/Extension/        Plugin class (implements SubscriberInterface)
├── forms/                Task configuration form XML
└── language/en-GB/       English language strings
```

---

## Database Schema

The module's install SQL creates one shared table:

```sql
#__ciwv_radiocharts
  id              INT         Primary key
  week_date       DATE        Week start (Monday) — e.g. 2026-04-14
  source          VARCHAR(50) mediabase_national | mediabase_local | luminate | musicmaster
  position        INT         Chart position this week
  artist          VARCHAR(255)
  title           VARCHAR(255)
  label           VARCHAR(255) NULL
  plays           INT         Spin/play count (Mediabase & Music Master)
  streams         BIGINT      Stream count (Luminate)
  peak_position   INT NULL    All-time peak
  weeks_on_chart  INT NULL    Consecutive weeks on chart
  created_at      DATETIME
  updated_at      DATETIME
```

A `UNIQUE KEY` on `(week_date, source, position)` ensures that re-running an
import for the same week updates existing records rather than duplicating them.

---

## Installation

### 1. Install the module

Install `mod_ciwv_radiocharts` through **Extensions → Manage → Install**.
This will create the `#__ciwv_radiocharts` table automatically.

Assign the module to the desired menu items and configure:
- **Week Offset** — `0` = current week, `-1` = previous week, etc.
- **Data Sources to Display** — check/uncheck individual sources
- **Entries per Source** — maximum rows shown per chart table
- **Show Week-over-Week Comparison** — toggle position-change arrows

### 2. Install the task plugins

Install each plugin ZIP through **Extensions → Manage → Install**:
- `plg_task_ciwv_radiocharts_mediabase_national`
- `plg_task_ciwv_radiocharts_mediabase_local`
- `plg_task_ciwv_radiocharts_luminate`
- `plg_task_ciwv_radiocharts_musicmaster`

Enable each plugin in **Extensions → Plugins**.

### 3. Configure and schedule tasks

Go to **System → Manage → Scheduled Tasks** (requires Joomla 4.1+).

Create a new task for each plugin and fill in the required parameters:

| Plugin | Required params |
|--------|----------------|
| Mediabase National | `api_endpoint`, `api_key`, `chart_format` |
| Mediabase Local | `api_endpoint`, `api_key`, `station_id` (CIWV), `chart_format` |
| Luminate | `api_endpoint`, `api_key`, `market_id`, `format_id` |
| Music Master | `csv_path` (absolute server path to weekly CSV export) |

Set each task to run **Weekly** on the appropriate day after the PD delivers
the data.

---

## Week-over-Week Comparison

When **Show Week-over-Week Comparison** is enabled in the module params the
template compares each track's current position against last week's position
and displays:

- **▲ n** (green) — moved up _n_ places
- **▼ n** (red) — dropped _n_ places
- **►** (grey) — no change
- **★** (blue) — new entry this week

---

## Namespace Reference

| Component | PHP Namespace |
|-----------|--------------|
| Module | `Gonzoradio\Module\CiwvRadiocharts\Site` |
| Mediabase National plugin | `Gonzoradio\Plugin\Task\CiwvRadiochartsMediabaseNational` |
| Mediabase Local plugin | `Gonzoradio\Plugin\Task\CiwvRadiochartsMediabaseLocal` |
| Luminate plugin | `Gonzoradio\Plugin\Task\CiwvRadiochartsLuminate` |
| Music Master plugin | `Gonzoradio\Plugin\Task\CiwvRadiochartsMusicmaster` |

---

## License

GNU General Public License version 2 or later.
