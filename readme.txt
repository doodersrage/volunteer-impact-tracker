=== Volunteer Impact Tracker ===
Contributors: doodersrage
Tags: volunteer, nonprofit, hours tracking, certificate, reporting, in-kind
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log volunteer hours against opportunities, approve self-reported time, and generate reports and printable certificates for grant applications and board reporting.

== Description ==

Most WordPress volunteer plugins stop at signup: a volunteer registers for a shift, gets a reminder email, and that's it. Nothing tracks whether they actually showed up, how many hours they served over a year, or what that adds up to for a grant report — so nonprofits end up keeping that in a separate spreadsheet anyway.

Volunteer Impact Tracker picks up where those plugins leave off:

* **Opportunities** — custom post type for volunteer opportunities/events (date, location, optional capacity).
* **Log Hours** — admins can add, edit, search, and paginate entries; admin-logged hours are approved immediately.
* **Self-report** — `[vit_log_hours]` front-end form; optional require-login setting.
* **My hours** — `[vit_my_hours]` lets logged-in volunteers see their own entries and yearly totals.
* **Approval queue** — pending queue with single and bulk approve/reject, menu badge, and admin notice.
* **Email notifications** — optional alerts when hours need approval, when hours are approved, and certificate delivery from Reports.
* **Reports** — date-range totals by volunteer and opportunity, in-kind dollar estimate, CSV export.
* **Certificates** — signed printable links; view, copy, or email from Reports.
* **Dashboard widget** — YTD hours, pending count, and top volunteers.
* **Roles** — grant the Volunteers capability to Editor or other roles from Settings.

This plugin is intentionally narrow. It doesn't replace donation, event, or membership plugins — it turns logged volunteer time into something you can report and hand back to the volunteer.

= Shortcodes =

`[vit_log_hours]` — self-report form. Optional: `[vit_log_hours opportunity_id="123"]`.

`[vit_my_hours]` — logged-in volunteer's hours for the current year. Optional: `[vit_my_hours year="2025"]`.

= Admin screens =

* **Volunteers → Log Hours** — add/edit hours; search and filter entries.
* **Volunteers → Pending Approvals** — approve or reject (including bulk).
* **Volunteers → Opportunities** — manage opportunities.
* **Volunteers → Reports** — totals, CSV, certificates.
* **Volunteers → Settings** — org name, hourly value, workflow, emails, manager roles.

= A note on the dollar-value estimate =

The default per-hour value in Settings is a placeholder. Independent Sector publishes an updated national estimate of the value of volunteer time each year — look up the current figure and update the Settings field before using this number in an actual grant report or annual filing.

== Installation ==

1. Download or clone this repository into `/wp-content/plugins/volunteer-impact-tracker/`, or zip the folder and upload it via Plugins → Add New → Upload Plugin.
2. Activate **Volunteer Impact Tracker**.
3. Go to **Volunteers → Settings** and set your organization name, hourly value, and preferred workflow options.
4. Add opportunities under **Volunteers → Opportunities** (optional — hours can also be logged as "General").
5. Add `[vit_log_hours]` and/or `[vit_my_hours]` to pages as needed.

== Frequently Asked Questions ==

= Does this replace my volunteer signup plugin? =

No — it's meant to sit alongside one. Signup plugins handle getting people to a shift. This plugin handles hours served, approvals, and reporting.

= Who can see the certificate link? =

The link is signed so parameters can't be silently altered, but it does not require login. Share it privately with the volunteer it belongs to.

= Why does a certificate say "Needs email"? =

Certificates are looked up by volunteer email. Include an email when logging hours to enable certificates and approval/certificate emails.

= How many hours can someone log per entry? =

Each entry must be between **0.25 and 24** hours, and the date served cannot be in the future (site timezone).

= Can I require login to submit hours? =

Yes — enable **Require Login** under Volunteers → Settings.

= Who can manage the Volunteers screens? =

Administrators always can. Under Settings you can also grant access to other roles (for example Editor).

= What happens when I delete the plugin? =

The custom hours table and plugin settings are removed. Opportunity posts are left in place.

== Changelog ==

= 1.1.0 =
* Edit existing hour entries (including status).
* Search and pagination on the entries list.
* Bulk approve/reject on Pending Approvals.
* Require Login setting for the self-report form.
* New `[vit_my_hours]` shortcode for logged-in volunteers.
* Email notifications for pending submissions and approved hours.
* Email certificate from Reports.
* Dashboard widget with YTD hours, pending count, and top volunteers.
* Assign Volunteers capability to additional roles from Settings.

= 1.0.1 =
* Fixed certificate links, admin user ID storage, and timezone date defaults.
* Stronger validation; pending badges; print/copy certificate QoL.

= 1.0.0 =
* Initial release: opportunities, hour logging, approval queue, reports, CSV export, printable certificates.

== Upgrade Notice ==

= 1.1.0 =
Adds edit/search/pagination, bulk approvals, emails, my-hours shortcode, dashboard widget, and role assignment.
