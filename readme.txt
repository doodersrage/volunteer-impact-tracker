=== Volunteer Impact Tracker ===
Contributors: doodersrage
Tags: volunteer, nonprofit, hours tracking, certificate, reporting
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log volunteer hours, approve self-reports, and generate grant-ready reports and printable certificates.

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

1. Upload the plugin files to the `/wp-content/plugins/volunteer-impact-tracker` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Volunteers → Settings** and set your organization name, hourly value, and preferred workflow options.
4. (Optional) Add opportunities under **Volunteers → Opportunities**.
5. (Optional) Add `[vit_log_hours]` and/or `[vit_my_hours]` to a page.

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

= What personal data does this plugin store? =

Volunteer name, email, hours, dates, optional notes, and related opportunity references. See Settings → Privacy for suggested privacy-policy text, or the plugin's privacy policy suggestion on the Privacy Policy guide screen.

= What happens when I delete the plugin? =

The custom hours table and plugin settings are removed. Opportunity posts are left in place.

== Screenshots ==

1. Log Hours screen — add entries and browse recent volunteer time.
2. Pending Approvals — review and bulk-approve self-reported hours.
3. Reports — date-range totals, in-kind estimate, and certificates.
4. Printable certificate of service.
5. Settings — organization, workflow, emails, and manager roles.

== Changelog ==

= 1.1.2 =
* Plugin Check fixes: translators comment, CSV streaming without fopen, certificate styles via wp_enqueue_style.
* Custom-table SQL uses prepare %i identifiers; Requires at least 6.2.
* Hardened sanitization, nonce phpcs annotations, and DirectDatabaseQuery ignores for the hours table.

= 1.1.1 =
* Prepared for WordPress.org: metadata, GPL LICENSE, Domain Path, privacy policy text, directory assets.
* Short description and tags updated to directory limits; Tested up to 7.1.
* Certificate email action now uses POST; translated status labels.

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

= 1.1.2 =
Plugin Check compliance release. Requires WordPress 6.2+.

= 1.1.1 =
WordPress.org readiness release: privacy text, LICENSE, and metadata fixes. Recommended before directory submission.

= 1.1.0 =
Adds edit/search/pagination, bulk approvals, emails, my-hours shortcode, dashboard widget, and role assignment.
