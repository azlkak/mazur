# MazurEstate website

Static multilingual website deployed with GitHub Pages. The project does not
require a build step or package manager.

## Structure

- `index.html` — home page and deployment entry point
- `doradztwo/` — real estate advisory hub
- `dla-deweloperow/` — sales support landing page for property developers
- `lokal-medyczny/` — multilingual landing page for finding and verifying medical premises
- `kim-jestesmy/` — about page
- `wyniki-wyszukiwania/` — search results page
- `oferta/` — property details page
- `domy/`, `dzialki/`, `lokale-komercyjne/` — property category pages
- `polityka-prywatnosci/`, `polityka-cookies/`, `regulamin/` — legal pages
- `assets/css/` — shared styles
- `assets/js/` — shared scripts and translations
- `assets/images/` — images and brand assets
- `assets/images/developers/` — imagery used by the developer landing page
- `assets/images/medical-premises/` — imagery used by the medical-premises landing page
- `archive/` — archived export files
- `docs/screenshots/` — development reference screenshots

Each public page folder contains its own `index.html` and, where needed, local
`styles.css` and `script.js`. Shared files remain in `assets` to avoid
duplication.

## Repository conventions

- Public pages use folder-based URLs and an `index.html` entry point.
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

## Deployment

GitHub Pages publishes the `main` branch. The QA preview is available at:

https://azlkak.github.io/mazur/

Before committing, check that the working tree contains only intentional files:

```bash
git status --short
git diff --check
```
