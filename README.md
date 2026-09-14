# Volunteer Impact Tracker

WordPress plugin for nonprofits to **log volunteer hours**, approve self-reports, export grant-ready reports, and issue printable certificates of service.

**Current version:** 1.1.1 · [GitHub](https://github.com/doodersrage/volunteer-impact-tracker)

> Preparing for the WordPress.org directory? See [WORDPRESS-ORG.md](WORDPRESS-ORG.md).

## Why it exists

Most volunteer plugins stop at signup. This one tracks what happened after the shift: hours served, approvals, totals by person and opportunity, in-kind dollar estimates, CSV export, and signed certificate links.

It is intentionally narrow — not a donation, events, or membership system.

## Features

| Area | What you get |
| --- | --- |
| Opportunities | CPT with date, location, optional capacity |
| Log hours | Admin add/edit, search, pagination; front-end self-report |
| My hours | `[vit_my_hours]` for logged-in volunteers |
| Approvals | Pending queue, bulk actions, menu badge, admin notice |
| Email | Pending alert, optional approval notice, send certificate |
| Reports | Date-range totals, in-kind value, CSV export |
| Certificates | Signed links — view, copy, or email |
| Dashboard | YTD hours, pending count, top volunteers |
| Access | Grant capability to additional roles from Settings |

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy this folder to `wp-content/plugins/volunteer-impact-tracker/`, **or** zip it and upload via **Plugins → Add New → Upload Plugin**.
2. Activate **Volunteer Impact Tracker**.
3. Open **Volunteers → Settings** (org name, hourly rate, workflow, emails, roles).
4. (Optional) Add opportunities under **Volunteers → Opportunities**.
5. Add shortcodes to pages as needed.

### From git

```bash
cd wp-content/plugins
git clone https://github.com/doodersrage/volunteer-impact-tracker.git
```

Then activate in **Plugins**.

## Shortcodes

```
[vit_log_hours]
[vit_log_hours opportunity_id="123"]
[vit_my_hours]
[vit_my_hours year="2025"]
```

## License

GPLv2 or later — see [LICENSE](LICENSE).
