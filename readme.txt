=== Volunteer Impact Tracker ===
Contributors: yourwordpressorg
Tags: volunteer, nonprofit, hours tracking, certificate, reporting
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log volunteer hours against opportunities, approve self-reported time, and generate reports and printable certificates for grant applications and board reporting.

== Description ==

Most WordPress volunteer plugins stop at signup: a volunteer registers for a shift, gets a reminder email, and that's it. Nothing tracks whether they actually showed up, how many hours they served over a year, or what that adds up to for a grant report — so nonprofits end up keeping that in a separate spreadsheet anyway.

Volunteer Impact Tracker picks up where those plugins leave off:

* **Opportunities** — a simple custom post type for volunteer opportunities/events (date, location, optional capacity).
* **Log Hours** — admins can log hours directly (recorded as approved immediately), or volunteers can self-report through a front-end form (`[vit_log_hours]` shortcode).
* **Approval queue** — self-reported hours land as pending until an admin approves or rejects them (this can be turned off in Settings if you trust your volunteers to self-certify).
* **Reports** — total hours by volunteer and by opportunity, filterable by date range, with an estimated in-kind dollar value using an hourly rate you set.
* **CSV export** — pull the filtered data straight into a grant application or board report.
* **Printable certificates** — a signed, tamper-resistant link per volunteer showing their total hours in a date range, styled for printing or saving as a PDF from the browser.

This plugin is intentionally narrow. It doesn't try to replace your donation plugin, your event calendar, or your membership system — it does one thing that's currently missing: turning logged volunteer time into something you can report and hand back to the volunteer.

= Shortcode =

`[vit_log_hours]` — renders the self-report form anywhere. Optionally pin it to one opportunity: `[vit_log_hours opportunity_id="123"]`.

= A note on the dollar-value estimate =

The default per-hour value in Settings is a placeholder. Independent Sector publishes an updated national estimate of the value of volunteer time each year — look up the current figure and update the Settings field before using this number in an actual grant report or annual filing.

== Installation ==

1. Upload the `volunteer-impact-tracker` folder to `/wp-content/plugins/`, or install the zip through Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Volunteers → Settings and set your organization name and hourly value.
4. Add opportunities under Volunteers → Opportunities (optional — hours can also be logged as "General").
5. Add the `[vit_log_hours]` shortcode to a page if you want volunteers to self-report hours.

== Frequently Asked Questions ==

= Does this replace my volunteer signup plugin? =

No — it's meant to sit alongside one. Signup plugins (like Wired Impact Volunteer Management or Volunteer Sign-Up Sheets) handle getting people to a shift. This plugin handles what happened after: hours served, approvals, and reporting.

= Who can see the certificate link? =

The certificate link is signed (it can't be edited to show a different volunteer or date range without invalidating it), but it doesn't require login — anyone with the exact link can view it, the same way a printed certificate works. Don't post certificate links publicly; hand them directly to the volunteer they belong to.

= Can I require login to submit hours? =

The front-end form works for both logged-in and logged-out visitors. If you want to restrict it to logged-in users only, wrap the shortcode in your theme/page with your own login check, or ask your site's developer to add one.

== Changelog ==

= 1.0.0 =
* Initial release: opportunities, hour logging (admin + self-report), approval queue, reports, CSV export, printable certificates.
