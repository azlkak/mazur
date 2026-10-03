# MazurEstate website

Multilingual website with static service pages and server-rendered property
details on Hostinger. GitHub remains the source repository. The frontend needs
no package manager; a small Python script prepares a public-only deployment.

## Structure

- `index.html` — home page and deployment entry point
- `doradztwo/` — real estate advisory hub
- `dla-deweloperow/` — sales support landing page for property developers
- `lokal-medyczny/` — multilingual landing page for finding and verifying medical premises
- `lokal-gastronomiczny/` — multilingual landing page for finding and verifying hospitality premises
- `grunt-pod-budowe/` — multilingual landing page for development-land acquisition and due diligence
- `mieszkanie-pod-wynajem/` — multilingual landing page for end-to-end rental apartment investment, fit-out and management
- `kim-jestesmy/` — about page
- `wyniki-wyszukiwania/` — search results page
- `oferta/` — property details template and client-side interactions
- `domy/`, `dzialki/`, `lokale-komercyjne/` — property category pages
- `polityka-prywatnosci/`, `polityka-cookies/`, `regulamin/` — legal pages
- `assets/css/` — shared styles
- `assets/js/` — shared scripts and translations
- `assets/images/` — images and brand assets
- `server/mls/` — versioned Hostinger importer, API, offer renderer and deployment routing; see its README
- `assets/images/developers/` — imagery used by the developer landing page
- `assets/images/medical-premises/` — imagery used by the medical-premises landing page
- `assets/images/hospitality/` — imagery used by the hospitality-premises landing page
- `assets/images/development-land/` — imagery used by the development-land landing page
- `archive/` — archived export files
- `docs/screenshots/` — development reference screenshots

Each public page folder contains its own `index.html` and, where needed, local
`styles.css` and `script.js`. Shared files remain in `assets` to avoid
duplication.

Public service content is available in Polish (`pl`), English (`en`), Ukrainian (`uk`)
and Russian (`ru`) at separate paths: `/` for Polish and `/en/`, `/uk/`, `/ru/` for translations. The same prefixes apply to service pages. Old `?lang=` links are redirected in the browser to the corresponding path, preserving filters and fragments. MLS/EstiCRM descriptions remain Polish; offer URLs have one Polish canonical regardless of the UI language.

## Repository conventions

- Service pages use folder-based URLs and an `index.html` entry point. On Hostinger, `/oferta/?id=...` is rendered by `oferta/index.php` for active, non-hidden offers.
- Page-specific CSS and JavaScript stay next to the page they support.
- Page-specific translations stay in the page folder as `i18n.js`; shared navigation and footer translations remain in `assets/js/`.
- Reusable styles, scripts, translations and images belong in `assets/`.
- Archived exports and QA references stay outside public page folders.
- Local editor state, temporary uploads and preview screenshots are ignored.
- New file names use lowercase kebab-case.

## Local preview

Run a static server from the repository root and open the displayed local address. Folder-based URLs require HTTP preview rather than opening the HTML files directly.

```bash
python3 -m http.server 4173
```

Then open `http://127.0.0.1:4173/`.

## SEO maintenance

Run `python3 scripts/prepare-seo.py` after changing a public page, SEO copy or the production domain. The generator owns the marked head blocks, translated HTML copies in `en/`, `uk/`, `ru/`, `assets/js/seo-config.js`, `sitemap.xml` and `robots.txt`. Edit the original page and generator, not the translated HTML copies. Each language has a static canonical and hreflang links; shared `seo.js` maintains matching structured data at runtime. Search/listing pages remain noindex. Run `python3 scripts/check-language-routes.py` to validate generated paths and the sitemap.

The public `robots.txt` lists both `sitemap.xml` (service pages) and the live `sitemap-oferty.xml` (visible offers). The offer sitemap reads the database on request; it does not need a scheduled build. See [SEO plan and 36 blog topics](docs/seo-plan.md) for content planning.

## Deployment

The production website is available at:

https://mazurestate.pl/

GitHub is the source of truth. Pushing `main` runs the `Hostinger release`
workflow: validate, build public files, update `hostinger-live`. Hostinger's
Git auto-deployment publishes that branch into `public_html/site-live/`.
Compare `/release.json` with the source commit after deployment. The
builder excludes documentation, draft pages and private importer sources. The
main document root retains the existing `api/` directory and uses
`server/mls/hostinger-root.htaccess` to route only apex/www traffic into
`site-live/`. The `api.mazurestate.pl` host and private `mls/` importer remain
separate. Never deploy the archive over `public_html/` or commit private
`config.php`, MLS packages, imported images, database exports or credentials.

Test an active and a hidden offer, the dynamic sitemap, images, search,
contact form display, language routes and API before changing DNS. A DNS
rollback changes only `@` and `www`; the `ftp`, `api`, email and other records
must be left untouched.

`Production health` checks the site, API, sitemap, images and importer liveness
on a best-effort 15-minute schedule, retaining diagnostic artifacts for 14 days.
Enable GitHub Actions failure notifications on the repository owner's account.
The retained old `site/` build is a frontend rollback point, not a database backup.
See [deployment and recovery instructions](docs/deployment.md) for pending
backup/Search Console verification and the separate 30-day import log rotation.

Before committing, check that the working tree contains only intentional files:

```bash
git status --short
git diff --check
```
