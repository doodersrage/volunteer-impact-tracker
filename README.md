# Volunteer Impact Tracker

WordPress plugin for nonprofits to **log volunteer hours**, approve self-reports, export grant-ready reports, and issue printable certificates of service.

**Current version:** 1.1.0 · [GitHub](https://github.com/doodersrage/volunteer-impact-tracker)

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

## Admin menu

- **Log Hours** — add/edit; search and filter entries  
- **Pending Approvals** — approve / reject (including bulk)  
- **Opportunities** — events volunteers log against  
- **Reports** — filters, totals, CSV, certificates  
- **Settings** — org, rate, approval/login, emails, manager roles  

## Certificates

From **Reports**, use **View**, **Copy link**, or **Email**. Links are signed; anyone with the exact URL can open them — share privately.

## Hour limits

- **0.25–24** hours per entry  
- Date served cannot be in the future (site timezone)  

## In-kind dollar value

Update the Settings hourly rate annually using a current published estimate before putting the figure in a grant or board report.

## Uninstall

Deleting the plugin removes the hours table and settings. Opportunity posts are kept.

## Changelog

See [`readme.txt`](readme.txt) for the full WordPress.org-style changelog.

### 1.1.0

Edit/search/pagination, bulk approvals, emails, `[vit_my_hours]`, dashboard widget, role assignment, require-login setting.

### 1.0.1

Certificate URL / timezone / validation fixes and admin QoL.

### 1.0.0

Initial release.

## License

GPLv2 or later. See the plugin header and [GPL-2.0](https://www.gnu.org/licenses/gpl-2.0.html).
