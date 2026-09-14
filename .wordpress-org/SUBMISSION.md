# WordPress.org submission guide

Checklist for publishing **Volunteer Impact Tracker** to the [Plugin Directory](https://wordpress.org/plugins/).

## Before you submit

1. **WordPress.org account** — Register at [wordpress.org](https://wordpress.org/support/register/). The username must match `Contributors:` in `readme.txt` (`doodersrage`). Whitelist `plugins@wordpress.org`.
2. **Confirm Author** — Plugin header Author is currently `doodersrage`. Change to your real name or organization if you prefer that on the listing.
3. **Validate readme** — Paste `readme.txt` into the [Readme Validator](https://wordpress.org/plugins/developers/readme-validator/).
4. **Run Plugin Check** — Install the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin on a test site and fix any Errors.
5. **Test on WordPress 7.1** — Activate, log hours, approve, export CSV, open a certificate, uninstall cleanly.
6. **Screenshots** — Capture the five UI screens listed in `.wordpress-org/README.md` and add `screenshot-1.png` … `screenshot-5.png` there (and later to SVN `/assets/`).
7. **Build the zip** — Zip the plugin folder contents so the archive root is `volunteer-impact-tracker/` with the main PHP file inside. Do **not** include `.git`, `.wordpress-org`, or this guide if you prefer a lean zip (assets go to SVN separately after approval).

```bash
# From the parent of the plugin folder:
cd /home/robertsm/Projects
zip -r volunteer-impact-tracker-1.1.2.zip volunteer-impact-tracker \
  -x 'volunteer-impact-tracker/.git/*' \
  -x 'volunteer-impact-tracker/.wordpress-org/*'
```

## Submit for review

1. Go to [Add Your Plugin](https://wordpress.org/plugins/developers/add/).
2. Provide a short overview (hours logging, approvals, reports, certificates for nonprofits).
3. Upload the complete zip.
4. Wait for the review email (often within ~14 business days). Fix anything they request.

## After approval (SVN)

You will receive an SVN URL similar to `https://plugins.svn.wordpress.org/volunteer-impact-tracker`.

Typical layout:

```
/assets/          ← icons, banners, screenshots (from .wordpress-org/)
/trunk/           ← latest development copy of the plugin
/tags/1.1.2/      ← frozen release matching Stable tag
```

1. Check out the empty repo.
2. Copy plugin files into `trunk/` (exclude `.git` and `.wordpress-org`).
3. Copy `.wordpress-org/*` images into `assets/`.
4. Commit trunk + assets.
5. Copy `trunk` to `tags/1.1.2` and commit.
6. Ensure `readme.txt` **Stable tag** is `1.1.2`.

Useful docs:

- [Planning, Submitting, and Maintaining Plugins](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)

## Already done in 1.1.1 for directory readiness

- Real Author / Author URI / Domain Path
- Short description ≤ 150 characters; max 5 tags
- Tested up to: 7.1
- GPL `LICENSE` file
- `languages/` + Domain Path
- Privacy policy suggestion via `wp_add_privacy_policy_content()`
- Directory icon + banner PNGs in `.wordpress-org/`
- Silent `index.php` files in asset directories
- Standard Installation / Screenshots sections in `readme.txt`
