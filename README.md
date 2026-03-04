# Zabbix RCA Module — Root Cause Analysis View

Advanced Root Cause Analysis module for Zabbix 7.0+. Provides timeline visualization, cascade chain detection, gap analysis, CI relationship mapping, and a trainable alert pattern registry.

---

## Directory Structure

```
rca/
├── actions/
│   ├── HostnameParser.php      # Parses hostnames → structured metadata
│   ├── RcaData.php             # AJAX data endpoint (problems, correlation, gaps)
│   ├── RcaRegistry.php         # Registry CRUD (Super Admin only)
│   └── RcaView.php             # Main page controller
├── assets/
│   ├── css/
│   │   └── rca.css             # Full stylesheet
│   └── js/
│       └── rca.js              # Frontend: timeline, detail panel, matrix, registry UI
├── config/
│   ├── hostname_map.json       # Hostname parser config (env/customer/product/type codes)
│   ├── hostname_exceptions.json # Manual overrides for non-standard hostnames
│   └── rca_registry.json       # CI relationships, alert patterns, gap rules, training log
├── views/
│   └── rca.view.php            # Main page template
├── LICENSE
├── manifest.json
├── Module.php
└── README.md
```

---

## Installation

1. Copy the `rca/` directory to your Zabbix modules directory:
   ```
   /usr/share/zabbix/modules/rca/
   ```

2. Set correct ownership:
   ```bash
   chown -R www-data:www-data /usr/share/zabbix/modules/rca/
   chmod -R 755 /usr/share/zabbix/modules/rca/
   chmod 664 /usr/share/zabbix/modules/rca/config/*.json
   ```

3. In Zabbix UI: **Administration → General → Modules → Scan for modules → Enable RCA**

4. The **RCA Page** menu item will appear under **Monitoring → RCA Page**.

---

## Hostname Format

### Standard Format (13 chars, ~95% of hosts)

```
prbd190107001
││└─┘└┘└┘└─┘
││  │  │  └── instance  (3 chars) — e.g. 001
││  │  └───── server type (2 chars) — e.g. 07 = Storage
││  └──────── product   (2 chars) — e.g. 01 = Android
│└─────────── customer  (4 chars) — e.g. bd19 = Google Inc
└──────────── environment (2 chars) — pr = Production
```

**Segment offsets (PHP):**
```
env:      substr($host, 0, 2)
customer: substr($host, 2, 4)
product:  substr($host, 6, 2)
type:     substr($host, 8, 2)
instance: substr($host, 10, 3)
```

### Parsing Strategy (in order)
1. **Exception override** — check `hostname_exceptions.json` first
2. **Positional parse** — fixed offsets on 13-char hostname
3. **Hostgroup fallback** — parse CUSTOMER/, PRODUCT/, TYPE/ groups
4. **Unresolved** — show raw hostname with ⚠ badge (still shown in timeline)

### Adding New Codes
Edit `config/hostname_map.json` — no PHP changes needed:
```json
"customers": {
  "xy99": { "name": "New Customer Ltd", "short": "NewCo" }
},
"products": {
  "08": { "name": "Mobile App", "short": "Mobile" }
}
```

### Non-standard Hostnames
Add to `config/hostname_exceptions.json`:
```json
{
  "hostname": "network-core-sw01",
  "env": "pr",
  "customer": "bd19",
  "type": "05",
  "instance": "001",
  "note": "Core switch — non-standard naming"
}
```

---

## Configuration Files

### `config/hostname_map.json`
Defines all hostname segment codes. Edit to add environments, customers, products, server types. **No PHP restart needed.**

### `config/hostname_exceptions.json`
Manual hostname overrides for the ~5% of hosts that don't follow the 13-char format. Add one entry per non-standard host.

### `config/rca_registry.json`
The RCA "brain":
- **`ci_relationships`** — known dependency paths (Storage→DB→App→Web)
- **`alert_patterns`** — trigger name glob patterns with cascade windows and confidence scores
- **`gap_rules`** — expected alert pairs; fires a gap warning if effect doesn't appear
- **`training_log`** — appended when "Train Registry" is triggered from an incident

**This file can also be edited from the RCA UI (Super Admin → Registry tab).**

---

## Correlation Strategy

Events are correlated in this priority order:
1. **Alert name** — glob pattern match against `rca_registry.json` patterns (weight: 40%)
2. **Time proximity** — closer events score higher (weight: 25%)
3. **Host group / customer** — same customer = likely related (weight: 20%)
4. **Trigger dependencies** — Zabbix native deps (weight: 10%)
5. **Tags** — overlapping tag values (weight: 5%)

All strategies can be toggled from the filter bar.

---

## Registry Management (Super Admin Only)

Access: **RCA Page → Registry tab** (only visible to Super Admin users)

**Add alert pattern:**
- Cause pattern: glob string matching the trigger that fires first (e.g. `Disk I/O latency*`)
- Effect pattern: glob string matching the expected downstream trigger
- Window: seconds within which the effect must appear to be considered a cascade

**Train from incident:**
- After running an analysis and confirming the root cause, click **Train Registry** in the Gap Detection tab
- This increments `seen_count` and nudges `confidence` upward for matched patterns
- Unconfirmed new patterns detected in the incident are offered for addition

---

## Time Presets

| Button | Window |
|--------|--------|
| 10m    | Last 10 minutes |
| 30m    | Last 30 minutes |
| 1h     | Last 1 hour (default) |
| 3h     | Last 3 hours |
| 6h     | Last 6 hours |
| 12h    | Last 12 hours |
| Custom | User-defined from/to datetime |

---

## API Authentication

The module uses the logged-in user's Zabbix session (CWebUser). No separate API token needed. All API calls are scoped to the user's Zabbix permissions — users can only see hosts and problems they have access to.

---

## Requirements

- Zabbix 7.0 or later
- PHP 8.0+
- `config/*.json` files must be writable by the web server user (for registry saves)
- No external JS libraries required

---

## Version History

| Version | Notes |
|---------|-------|
| 1.0.0   | Initial release — timeline, cascade chains, gap detection, registry, matrix view |

---

## Roadmap / Future Enhancements

- [ ] Predictive gap detection using ML confidence scoring
- [ ] Email/Slack alert when root cause is auto-identified
- [ ] Multi-incident comparison view
- [ ] Export incident report as PDF
- [ ] Tag-based environment auto-detection
- [ ] Webhooks for registry pattern confirmation
