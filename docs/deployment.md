# Deployment i wycofanie zmiany

Stan wdrożenia: 03.10.2026. GitHub `azlkak/mazur` jest repozytorium kodu,
natomiast produkcyjny ruch `mazurestate.pl` i `www.mazurestate.pl` obsługuje
Hostinger. Workflow `Hostinger release` buduje publiczne pliki po pushu do
`main` i zapisuje je na gałęzi `hostinger-live`. Hostinger należy połączyć
z tą gałęzią i katalogiem `site` przez Git auto-deployment. Dopiero potwierdzone
włączenie tego połączenia zapewnia automatyczną publikację. GitHub Pages może
pozostać awaryjną kopią statycznych stron, ale nie wykonuje PHP kart ofert.

## Układ na Hostingerze

- Identyfikator konta i bezwzględne ścieżki są w prywatnej dokumentacji operacyjnej.
- `public_html/site/` — odizolowany build publicznej witryny. Statyczne strony
  są plikami HTML, aktywne oferty obsługuje `oferta/index.php`, a
  `sitemap-oferty.xml` jest kierowana do PHP i czyta bieżącą bazę.
- `public_html/api/` — istniejące API pod `api.mazurestate.pl`; nie nadpisywać
  wdrożeniem strony. Formularze i obrazy korzystają z tej usługi.
- Prywatny katalog importera poza katalogiem WWW — konfiguracja, paczki FTP,
  zdjęcia i logi. Nie kopiować do `public_html` ani do repo.
- Root `.htaccess` (`server/mls/hostinger-root.htaccess`) kieruje tylko żądania
  hostów apex/www do `site/`; ścieżka `/api/` i host API są wyłączone.

Rekordy FTP, API i poczty nie są częścią przełączenia WWW. Ich bieżące wartości
oraz parametry eksportów CRM należy sprawdzić w prywatnej dokumentacji
operacyjnej i panelu. Nie zmieniać ich przy publikacji frontendu.

## Publikacja nowej wersji witryny

Docelowa ścieżka automatyczna: PR → testy → merge do `main` → workflow
`Hostinger release` → gałąź `hostinger-live` → Hostinger `site/`.
`release.json` na witrynie wskazuje SHA źródłowego commitu; porównać go z
GitHubem po wdrożeniu. Publiczna gałąź nie zawiera importera, konfiguracji ani
dokumentacji. Nie podpinać surowego `main` do katalogu serwowanego przez WWW.
Artefakty buildów są przechowywane 14 dni; historia publicznej gałęzi pozwala
odtworzyć poprzednią wersję frontendu. Nie jest to backup bazy ani zdjęć.

Poniższa ścieżka ręczna służy do stagingu lub awarii automatyzacji:

1. Sprawdzić `git status --short`, cel wdrożenia i zmiany w repo. Uruchomić
   `python3 scripts/prepare-seo.py` po zmianach metadanych lub tras oraz
   `python3 scripts/check-language-routes.py`.
2. Uruchomić `python3 scripts/build-hostinger-site.py /absolute/output-directory`.
   Skrypt kopiuje jedynie publiczne pliki do `public/` i tworzy archiwum
   `mazur-static-site.zip` z plikami statycznymi. Nie publikuje dokumentacji,
   prywatnej konfiguracji, bazy ani paczek MLS.
3. Wdrożyć archiwum **tylko** do odizolowanego `public_html/site/`. Mechanizm
   wdrożenia archiwum może nadpisać zawartość katalogu docelowego, dlatego
   nigdy nie celować w `public_html/` (tam znajduje się produkcyjne API).
4. Wgrać wybrane `.php` i `.htaccess` z `public/` osobno do `site/`; nie
   podmieniać produkcyjnych plików `public_html/api/` wersją z repo bez diffu
   i backupu. Zmianę root `.htaccess` robić tylko po inspekcji dotychczasowego.
5. Na izolowanej subdomenie `seo-stage.mazurestate.pl` sprawdzić stronę
   główną, wersje językowe, wyniki wyszukiwania, aktywną i ukrytą ofertę,
   galerię/lightbox, formularz bez wysyłania testowego leada, obrazy, API,
   `robots.txt`, statyczną i dynamiczną sitemapę. Sprawdzić desktop, telefon
   i tablet. Staging ma nagłówek `X-Robots-Tag: noindex, nofollow`.
6. Jeśli zmienia się DNS, najpierw odczytać całą strefę i snapshoty Hostinger,
   walidować tylko zmieniane rekordy, a po zmianie zweryfikować zarówno
   publiczny DNS, jak i bezpośrednio docelowy CDN. Pamiętać o TTL 300 s:
   przez kilka minut część użytkowników może widzieć poprzedni serwer.

Każda karta `/oferta/?id=...` powstaje z bieżącej bazy; 200 i canonical dostaje
tylko opublikowana, nieukryta oferta. Ukryta lub usunięta daje 404, a błąd
bazy 503. Sitemap ofert jest odczytywana na żywo, więc cena, zdjęcia i status
mogą zmienić się po godzinnym imporcie bez przebudowy strony. Nie ustawiamy
`lastmod`, dopóki nie ma potwierdzonej historii **istotnych** zmian treści.

## Wycofanie

Jeśli nowa witryna zawiedzie, a API i importer są zdrowe, przywrócić jedynie
`@ ALIAS azlkak.github.io.` i `www CNAME azlkak.github.io.`. Najpierw
sprawdzić aktualne rekordy, następnie zwalidować zmianę. FTP, API i poczta
pozostają bez zmian. Identyfikator snapshotu sprzed przełączenia jest w prywatnej
dokumentacji. Odtworzenie całego snapshotu nadpisze również późniejsze
niezwiązane zmiany — traktować go jako awaryjne odniesienie, nie pierwszą
metodę rollbacku. Wersja GitHub Pages nie oferuje dynamicznych, indeksowalnych
kart; dlatego rollback jest tymczasowym przywróceniem dostępności serwisu.

Awaria samego API/importera wymaga odrębnej diagnozy i kopii dokładnych
plików. Nie uruchamiać importu ponownie bez sprawdzenia paczek i logów.
Nie commitować sekretów, plików XML/ZIP, zdjęć zaimportowanych ani dumpów DB.

## Monitoring i otwarte kontrole operacyjne

Workflow `Production health` wykonuje odczytowe testy strony, sitemap,
losowanej przez kolejność sitemapy aktywnej oferty, API, pierwszego zdjęcia
i ukrytej oferty testowej `esti-215181`. Harmonogram co 15 minut jest
best-effort GitHub Actions, nie gwarantowanym SLA. Wyniki artefaktów: 14 dni.
Powiadomienia o błędach należy włączyć w ustawieniach GitHub Actions właściciela
repozytorium. Dla krytycznego SLA dodać niezależny monitoring zewnętrzny.

Zielony test WWW **nie potwierdza świeżości importów**. Osobno sprawdzać panel
integracji: ostatnie uruchomienie i sukces MLS/EstiCRM, błędy oraz kolejkę paczek.
Brak nowych paczek nie oznacza awarii — MLS wysyła aktualizacje po zmianach.
Nie uruchamiać ponownie importera w celu testowania dostępności.

Do potwierdzenia w hPanel: harmonogram kopii bazy i prywatnych plików,
retencja, ostatni udany backup oraz test odtworzenia w izolowanej lokalizacji.
Nie traktować snapshotu DNS ani artefaktów GitHub jako kopii danych CRM.
Do potwierdzenia w Google Search Console: zgłoszenie obu sitemap oraz
inspekcja aktywnej karty i brak indeksowalności ukrytej oferty testowej.
