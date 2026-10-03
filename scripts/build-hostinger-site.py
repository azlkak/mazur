"""Build a public-only Hostinger site archive without touching the API install.

Usage: python3 scripts/build-hostinger-site.py /absolute/output-directory
The output is a ready-to-serve document root plus a zip for isolated staging.
Never publish the repository itself: it contains private-server source and docs.
"""
from pathlib import Path
import shutil
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PAGE_DIRS = [
    "assets", "en", "uk", "ru", "oferta", "wyniki-wyszukiwania",
    "doradztwo", "dla-deweloperow", "lokal-medyczny",
    "lokal-gastronomiczny", "grunt-pod-budowe", "mieszkanie-pod-wynajem",
    "kim-jestesmy", "domy", "dzialki", "lokale-komercyjne",
    "polityka-prywatnosci", "polityka-cookies", "regulamin",
]
ROOT_FILES = ["index.html", "sitemap.xml", "robots.txt", "favicon.ico"]
SERVER_FILES = {
    "public/.htaccess": ".htaccess",
    "public/sitemap-oferty.php": "sitemap-oferty.php",
    "public/oferta/.htaccess": "oferta/.htaccess",
    "public/oferta/index.php": "oferta/index.php",
    "public/api/offer-seo-lib.php": "api/offer-seo-lib.php",
    "public/api/portal-visibility.php": "api/portal-visibility.php",
    "public/api/description-formatter.php": "api/description-formatter.php",
}


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit("Usage: build-hostinger-site.py /absolute/output-directory")
    output = Path(sys.argv[1]).resolve()
    if not output.is_absolute() or output in (ROOT, ROOT.parent):
        raise SystemExit("Use a dedicated output directory, never the repository root")
    output.mkdir(parents=True, exist_ok=True)
    public = output / "public"
    if public.exists():
        shutil.rmtree(public)
    public.mkdir()
    for name in PAGE_DIRS:
        source = ROOT / name
        if source.is_dir():
            shutil.copytree(source, public / name,
                ignore=shutil.ignore_patterns(".DS_Store", "*.bak", "*.patch"))
    for name in ROOT_FILES:
        source = ROOT / name
        if source.is_file():
            shutil.copy2(source, public / name)
    for source_name, target_name in SERVER_FILES.items():
        source = ROOT / "server" / "mls" / source_name
        target = public / target_name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
    archive = output / "mazur-static-site.zip"
    if archive.exists():
        archive.unlink()
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=8) as result:
        for file in sorted(public.rglob("*")):
            if file.is_file() and file.suffix.lower() != ".php" and file.name != ".htaccess":
                result.write(file, file.relative_to(public))
    print(f"Built {sum(1 for file in public.rglob('*') if file.is_file())} files: {archive}")


if __name__ == "__main__":
    main()
