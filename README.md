# MazurEstate website

Static multilingual website prepared for deployment on Netlify.

## Structure

- `index.html` — home page and deployment entry point
- `kim-jestesmy/` — about page
- `wyniki-wyszukiwania/` — search results page
- `oferta/` — property details page
- `domy/`, `dzialki/`, `lokale-komercyjne/` — property category pages
- `polityka-prywatnosci/`, `polityka-cookies/`, `regulamin/` — legal pages
- `assets/css/` — shared styles
- `assets/js/` — shared scripts and translations
- `assets/images/` — images and brand assets
- `archive/` — archived export files
- `docs/screenshots/` — development reference screenshots

Each public page folder contains its own `index.html` and, where needed, local `styles.css` and `script.js`. Shared files remain in `assets` to avoid duplication.

## Local preview

Run a static server from the repository root and open the displayed local address. Folder-based URLs require HTTP preview rather than opening the HTML files directly.

```bash
python3 -m http.server 4173
```
