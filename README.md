# Volunteer Impact Tracker

WordPress plugin for nonprofits to **log volunteer hours**, approve self-reports, export grant-ready reports, and issue printable certificates of service.

[View on GitHub](https://github.com/doodersrage/volunteer-impact-tracker)

## Why it exists

Most volunteer plugins stop at signup. This one tracks what happened after the shift: hours served, approvals, totals by person and opportunity, in-kind dollar estimates, CSV export, and signed certificate links.

It is intentionally narrow — not a donation, events, or membership system.

## Features

| Area | What you get |
| --- | --- |
| Opportunities | CPT with date, location, optional capacity |
| Log hours | Admin logging (auto-approved) + front-end self-report |
| Approvals | Pending queue with menu badge and admin notice (optional) |
| Reports | Date-range totals by volunteer / opportunity + in-kind value |
| Export | CSV for the current filter |
| Certificates | Signed links, print / save as PDF, copy link from Reports |

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy this folder to `wp-content/plugins/volunteer-impact-tracker/`, **or** zip it and upload via **Plugins → Add New → Upload Plugin**.
2. Activate **Volunteer Impact Tracker**.
3. Open **Volunteers → Settings** and set organization name + hourly value.
4. (Optional) Add opportunities under **Volunteers → Opportunities**.
5. (Optional) Put `[vit_log_hours]` on a page for volunteer self-reporting.

### From git

```bash
cd wp-content/plugins
git clone https://github.com/doodersrage/volunteer-impact-tracker.git
```

Then activate in **Plugins**.

## Shortcode

```
[vit_log_hours]
```

Pin to one opportunity:

```
[vit_log_hours opportunity_id="123"]
```

## Admin menu

- **Log Hours** — enter approved hours; filter recent entries by status  
- **Pending Approvals** — approve / reject self-reports  
- **Opportunities** — manage events volunteers log against  
- **Reports** — filters, totals, CSV, certificates  
- **Settings** — org name, hourly rate, certificate text, require-approval toggle  

## Certificates

From **Reports**, use **View** or **Copy link** for a volunteer (email required). Links are signed so parameters can’t be silently altered; anyone with the exact URL can open it — treat them like a paper certificate and share privately.

## Hour limits

- **0.25–24** hours per entry  
- Date served cannot be in the future (site timezone)  

## In-kind dollar value

The Settings hourly rate is a placeholder. Update it annually using a current published estimate (e.g. Independent Sector) before putting the figure in a grant or board report.

## Uninstall

Deleting the plugin removes the hours table and settings. Opportunity posts are kept.

## Changelog

See [`readme.txt`](readme.txt) (WordPress.org-style changelog), or the [releases / commits](https://github.com/doodersrage/volunteer-impact-tracker) on GitHub.

### 1.0.1

Bug fixes for certificate URLs, admin user IDs, and timezone dates; stronger validation; pending badges; print/copy QoL.

### 1.0.0

Initial release.

## License

GPLv2 or later. See the plugin header and [GPL-2.0](https://www.gnu.org/licenses/gpl-2.0.html).
