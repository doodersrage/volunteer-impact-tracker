# WordPress.org directory assets

These files belong in the **SVN `/assets/`** folder on WordPress.org (not inside the plugin zip / `trunk`).

| File | Purpose |
| --- | --- |
| `icon-128x128.png` | Plugin icon (required) |
| `icon-256x256.png` | Retina plugin icon (required) |
| `banner-772x250.png` | Directory header banner |
| `banner-1544x500.png` | Retina banner |

## Screenshots (add before or soon after approval)

Capture from a local install and save here as:

- `screenshot-1.png` — Log Hours
- `screenshot-2.png` — Pending Approvals
- `screenshot-3.png` — Reports
- `screenshot-4.png` — Certificate print view
- `screenshot-5.png` — Settings

Captions in `readme.txt` under `== Screenshots ==` must match this numbering.

After your plugin is approved, upload with SVN:

```bash
svn co https://plugins.svn.wordpress.org/volunteer-impact-tracker
# copy these files into volunteer-impact-tracker/assets/
svn add assets/*
svn ci -m "Add directory icons and banners"
```

See [WORDPRESS-ORG.md](../WORDPRESS-ORG.md) for the full submission checklist.
