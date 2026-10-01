# MLS — środowisko Hostinger

## Jedno źródło kodu

Ten katalog w repozytorium GitHub jest źródłem kodu importera i API. Hostinger
uruchamia wdrożoną kopię. Zmiany wprowadzamy najpierw tutaj, a po sprawdzeniu
wdrażamy tylko potrzebne pliki na serwer. Samo wypchnięcie repozytorium na GitHub
publikuje frontend przez GitHub Pages, ale **nie** aktualizuje PHP ani bazy na
Hostingerze.

- `private/bin/import.php` — importer uruchamiany przez cron.
- `private/schema.sql` — schemat dla nowej instalacji; nie aktualizuje istniejącej bazy.
- `private/migrations/` — jednorazowe zmiany istniejącej bazy.
- `public/api/` — jawne punkty API; tylko ten podkatalog trafia pod `public_html/api`.
- `private/config.example.php` — wzór konfiguracji bez sekretów.

### Wybrane oferty na stronie głównej — wdrożone 29.09.2026

Panel `https://api.mazurestate.pl/api/featured-admin.php` pozwala osobie
nietechnicznej wyszukać ofertę MLS po numerze, tytule lub miejscowości,
dodać do dziesięciu ofert, zmienić ich kolejność i usunąć je z sekcji.
Na stronie głównej karty przewija się poziomo strzałkami lub gestem. Dostęp
jest przez kod jednorazowy wysyłany e-mailem. Kod i lista uprawnionych
adresów nie trafiają do publicznego HTML ani do GitHub. W prywatnym
`mls/config.php` ustawiono oba uprawnione adresy. Przy kolejnej instalacji dodać:

```php
'featured_admin_emails' => ['adres-pierwszy@example.com', 'adres-drugi@example.com'],
```

Utworzono tabelę `featured_offers` w `u101822986_mls` i wgrano
`public/api/featured-offers.php`, `featured-admin.php`,
`featured-admin.html`, `featured-admin.css` i `featured-admin.js` do
`public_html/api/`. Odpowiedzi publicznego API oraz stanu niezalogowanego
panelu zwracają HTTP 200. Logowanie kodem, zapis pierwszej oferty i widok
kart należy sprawdzić po otrzymaniu kodu przez właściciela.
Nie wdrażać pozostałych plików PHP z repozytorium razem z tą funkcją:
produkcyjna baza nadal ma schemat jednego źródła.

Publiczny punkt `featured-offers.php` zwraca tylko identyfikatory aktywnych
ofert, więc zdjęcia i ceny zawsze pochodzą z bieżącego API MLS. Gdy lista
jest pusta lub API nie odpowiada, sekcja pozostaje ukryta. Wycofana oferta
jest automatycznie pomijana. Panel pokazuje ją jako nieaktywną, by można
ją było usunąć z ustawienia. Ta wersja obsługuje wyłącznie numery MLS;
po wdrożeniu drugiego źródła trzeba rozszerzyć walidację i łączenie danych.

### Ukrywanie ofert z portalu

Panel pozwala ukryć wyszukaną ofertę MLS lub EstiCRM na całym portalu i później
ją przywrócić. Ukrycie zapisuje się w `portal_hidden_offers`, poza tabelą
importowanych ofert. Importer MLS nie kasuje tych ustawień. Publiczne API
pomija ukryte oferty w wynikach, podpowiedziach lokalizacji i sekcji wybranych,
a strona szczegółów zwraca dla nich 404. Jeżeli oba źródła zawierają tę samą
nieruchomość (`companyId:number`), ukrycie obejmuje oba rekordy. Ukrycie
usuwa ofertę z zapisanej listy wybranych, żeby nie wróciła tam automatycznie
po przywróceniu. Przed wdrożeniem nowych plików PHP uruchomić migrację
`private/migrations/20260929_portal_hidden_offers.sql` na bazie `u101822986_mls`.
Publiczne API i panel obsługują zarówno obecny schemat `mls_offers` bez kolumny
`source`, jak i schemat po planowanej migracji `20260929_offer_sources.sql`.

### Status integracji w panelu ofert

Po zalogowaniu ten sam panel pokazuje osobno MLS i EstiCRM: ostatnie
uruchomienie importera, ostatni import, liczby ofert, paczki oczekujące,
przetwarzane i błędne oraz historię ostatnich paczek. Odczyt jest dostępny
wyłącznie dla uprawnionych adresów po logowaniu kodem. Panel odświeża dane
co minutę i pozwala odświeżyć je ręcznie.

Importer zapisuje prywatny plik `logs/status-<źródło>.json` przy rozpoczęciu,
poprawnym zakończeniu i błędzie każdego przebiegu, także gdy nie ma nowych
paczek. Dzięki temu panel odróżnia działający harmonogram bez nowych danych
od braku uruchomienia. Po ponad 2 godzinach i 15 minutach bez potwierdzenia
pokazuje ostrzeżenie; paczki w `errors/` również wymagają uwagi. Jeżeli
importer danego źródła nie zapisuje jeszcze statusu, panel uczciwie pokaże
„Brak telemetrii”, zachowując historię z bazy. Migrację dwóch źródeł wykonano
01.10.2026; status MLS potwierdził poprawny przebieg o 13:00 UTC. Import
EstiCRM pozostaje wyłączony do sprawdzenia pierwszej rzeczywistej paczki.

Prawdziwy `config.php`, paczki MLS, zdjęcia, archiwum, logi i zrzuty bazy
nie trafiają do GitHub. Dane produkcyjne pozostają na Hostingerze; kopie
bezpieczeństwa mogą być przechowywane lokalnie przez administratora.

### Indeks lokalizacji — wdrożony i uzupełniony 29.09.2026

Zrobiono kopię bazy `u101822986_mls` przed migracją, wykonano
`private/migrations/20260928_offer_locations.sql` i wdrożono
`private/bin/import.php` do prywatnego `mls/bin/` oraz
`public/api/mls-locations.php` do `public_html/api/`. Po imporcie rzeczywistego
eksportu uzupełniono miasto i dzielnicę w istniejących rekordach bez zmiany
danych oferty. Miasto jest dostępne w 5 054 z 5 071 rekordów (1 597 różnych
miast); 17 rekordów nie ma nazwy miasta w danych źródłowych. Dzielnicy nie
podano w 3 115 rekordach. Podpowiedzi w wyszukiwarce zostały sprawdzone dla
Warszawy i jej dzielnic; filtr wskazał 523 mieszkania na sprzedaż w Warszawie.
Importer na serwerze zapisuje te pola także przy następnych paczkach, co
potwierdził automatyczny import 29.09.2026 o 11:00 UTC. Jego produkcyjna
wersja nadal obsługuje wyłącznie MLS;
kod drugiego źródła w repozytorium wymaga osobnej migracji przed wdrożeniem.

### Pierwszy rzeczywisty eksport MLS — 29.09.2026

Zaimportowano paczkę pełną około 14,6 GiB i trzy następujące po niej paczki
przyrostowe (4,6, 43,43 i 60,79 MiB). Po ich zatwierdzeniu baza zawierała
5 071 ofert, w tym 5 066 kandydatów do publikacji, oraz cztery zarejestrowane
paczki. Jedno powtórzenie identycznej oferty `377499` pominięto; raport
`logs/skipped-<sha>.json` został zapisany na serwerze. Importer przekazał
raport do dwóch adresów powiadomień wskazanych w prywatnym `config.php`.
Właściciel potwierdził otrzymanie wiadomości e-mail z raportem.

Następna paczka przyrostowa (93,71 MiB) została pobrana przez cron o 11:00
UTC i przeniesiona do archiwum. Po niej baza zawierała 5 078 ofert, z czego
5 073 do publikacji, oraz pięć zarejestrowanych paczek. Liczba rekordów bez
miasta pozostała równa 17.

Rzeczywiste nazwy paczek mają sufiks `_YYYYMMDDHHMMSS.zip`, a akcje w
przyrostach obejmują `create`, `update` i `delete`. Importer akceptuje te
formaty oraz starszy wzór nazwy z podkreśleniem przed godziną. Duża paczka
wymaga limitów co najmniej 20 GiB dla ZIP i 30 GiB po rozpakowaniu.
Połączenie z bazą jest odnawiane po długich etapach odczytu ZIP i zdjęć, żeby
nie wygasło przed transakcją. Konto ma 50 GiB; po imporcie zajęte było około
29,36 GiB.

### Drugi eksport: wybrane oferty EstiCRM (konfiguracja w toku)

Eksport własnych ofert powinien używać opcji **„Dowolny – format EstiCRMXml”**
w EstiCRM i trybu **ręcznego**. Po ustawieniu oferty jako „Aktywna publikacja”
pracownik zaznacza portal MazurEstate w polu „EX” tej oferty. Dzięki temu
wybiera w CRM, które oferty pojawią się na stronie; tryb automatyczny wysłałby
wszystkie aktywne oferty spełniające ustawienia portalu. Ten eksport potrzebuje
osobnego konta FTP ograniczonego do `mls/esticrm/incoming` i osobnego zadania
cron: `import.php --source=esticrm`. Nie kierować paczek EstiCRM do
`mls/incoming` używanego przez MLS.

Ograniczenie do województwa mazowieckiego dotyczy wyłącznie źródła MLS.
W portalu eksportowym EstiCRM pole „Wysyłaj TYLKO z lokalizacji” pozostaje
puste, a importer nie nakłada filtra regionalnego na ręcznie wybrane oferty.
Nie wolno przenosić filtra MLS na oferty własne.

Przed włączeniem drugiego eksportu: wykonać kopię bazy, uruchomić migrację
`private/migrations/20260929_offer_sources.sql`, wdrożyć importer i punkty API,
utworzyć katalogi `esticrm/incoming`, `esticrm/processing`, `esticrm/archive`
i `esticrm/errors` poza `public_html`. Po otrzymaniu pierwszej paczki uruchomić
`import.php --source=esticrm --check /bezwzgledna/sciezka/paczka.zip`; kontrola
działa przy wyłączonym imporcie. Dopiero po sprawdzeniu składni PHP i wyniku
kontroli ustawić w prywatnym `config.php` `esticrm_import_enabled => true`,
zaimportować tę paczkę ręcznie, sprawdzić publiczne API i włączyć osobny cron.
Paczka
całościowa usuwa oferty tylko własnego źródła. Publiczne identyfikatory MLS
pozostają liczbami; oferty EstiCRM dostają prefiks `esti-`.

Lista wyników daje pierwszeństwo ofertom własnym. Identyczne oferty z MLS
i EstiCRM są ukrywane na liście, jeśli oba eksporty podają to samo `companyId`
i numer oferty. Inne duplikaty wymagają osobnego sprawdzenia.

W formularzu EstiCRM: nazwa portalu `MazurEstate — wybrane oferty`, adres hosta
`147.93.73.136` (bez `ftp://`), port `21`, login nowego konta ograniczonego do
`mls/esticrm/incoming`, a katalogi zdjęć oraz XML/ZIP pozostawić puste. Hasło
tego konta wprowadza właściciel bezpośrednio w Hostingerze i EstiCRM. Wybrać
**eksport ręczny** i początkowo zaznaczyć w „EX” tylko jedną ofertę testową.
Pierwsza realna paczka musi potwierdzić, że nazwy plików,
struktura ZIP i znaczniki aktywnej oferty pasują do walidacji importera.
Po imporcie sprawdzić szczegóły i zdjęcia wybranej oferty na stronie, a
następnie wycofać jej zaznaczenie w „EX” i sprawdzić usunięcie z portalu.
MLS powinien w obu krokach pozostać bez zmian.

Lista wyników pobiera wszystkie oferty do publikacji strumieniowo z API, z
pięcioma zdjęciami podglądowymi na ofertę, i stronicuje je w przeglądarce.
Przy dalszym wzroście katalogu warto przenieść filtrowanie i stronicowanie
do API, aby nie przesyłać całej listy przy każdym otwarciu wyszukiwarki.

Status 01.10.2026: publiczne API i frontend wyświetlają rzeczywiste oferty MLS.
API zwraca wyłącznie jawnie wybrane pola i nie publikuje danych kontaktowych
ani dokładnego adresu. Godzinny cron MLS wskazywał usuniętą ścieżkę
`darkgreen-rabbit-981798.hostingersite.com` i zwracał `Could not open input file`.
Zastąpiono go pojedynczym zadaniem `0 * * * *` uruchamiającym importer pod
`domains/mazurestate.pl/mls/bin/import.php` (UID `7SEAMmQMRq`). Przebieg po
wdrożeniu importera dwuzródłowego 01.10.2026 o 13:00 UTC zakończył się
poprawnie według prywatnego `logs/status-mls.json`. Odbiór następnej paczki
MLS nadal wymaga weryfikacji. Ostatnia paczka
zapisana w `mls_batches` została zaimportowana 30.09.2026 o 13:00 UTC;
powiadomienie eksportera z 30.09.2026 19:29 czasu polskiego zgłasza blokadę
zapisu na FTP portalu. Trzeba osobno sprawdzić adres FTP i dostęp konta
eksportu MLS, zanim uzna się synchronizację za przywróconą.

## Serwer

```text
/home/u101822986/domains/mazurestate.pl/mls/
  config.php          # prywatne dane bazy, tryb 0600
  bin/import.php      # importer uruchamiany wyłącznie z CLI
  schema.sql
  incoming/           # katalog docelowy osobnego konta FTP dla MLS
  processing/
  archive/
  errors/
  images/
  logs/
```

- Baza: `u101822986_mls`; osobny użytkownik o tej samej nazwie.
- W wiadomości przekazanej MLS podano host `ftp.mazurestate.pl`, port `21`,
  login `u101822986.mls`, katalog `/` i tryb pasywny; użytkownik potwierdził,
  że tych danych używa nadawca. Rekord DNS `ftp` nadal wskazuje
  `147.93.73.136`. Konto `u101822986.mls` jest obecnie ograniczone do
  `domains/mazurestate.pl/mls/incoming`. Osobne konto
  `u101822986.esticrm` ograniczono do `mls/esticrm/incoming`.
- Zadanie Cron w hPanelu: raz na godzinę (`0 * * * *`), polecenie:
  `/usr/bin/php /home/u101822986/domains/mazurestate.pl/mls/bin/import.php`.
- Importer uruchomił się 01.10.2026 o 09:00 czasu polskiego i przetworzył
  zaległą paczkę `EstiCRM_2553_20260930152903.zip` (28 rekordów). Potwierdzają
  to wyjście zadania Cron i nowy wpis w `mls_batches` z czasem 07:00:04 UTC.
  Dostarczanie kolejnych paczek przez FTP nadal wymaga osobnej weryfikacji.
- Tymczasowy klucz SSH użyty przy wdrożeniu został usunięty.

## Formularz kontaktowy i EstiCRM

Frontend wysyła formularze do `public/api/enquiry.php`. Ten plik sprawdza dane,
rozpoznaje ofertę po jej lokalnym identyfikatorze i przekazuje zapytanie bezpośrednio
do `POST https://app.esticrm.pl/apiClient/question/store`. Dane formularza nie są
zapisywane w lokalnej bazie Hostinger.

W prywatnym `mls/config.php` trzeba uzupełnić:

```php
'esticrm_company' => 'identyfikator firmy',
'esticrm_token' => 'sekretny token API',
'esticrm_agent_email' => 'email opiekuna zapytań',
```

Identyfikator i token są dostępne dla administratora w EstiCRM w
`Ustawienia -> Dane firmowe`. Nie wolno dodawać ich do repozytorium ani kodu
frontendu. Formularz wymaga imienia, wiadomości, zgody oraz co najmniej telefonu
lub e-maila. Na stronie oferty backend uzupełnia numer oferty, rynek, rodzaj
nieruchomości i typ transakcji na podstawie już zaimportowanych danych MLS.

## Zachowanie importera

- Odbiera płaskie paczki ZIP z `definitions.xml`, XML ofert i zdjęciami.
- Obsługuje `export="incremental"` z akcjami `create`, `update` i `delete`.
- Obsługuje `export="full"`; zawartość paczki jest stanem autorytatywnym, więc
  oferty nieobecne w niej są usuwane z bazy.
- Przetwarza paczki chronologicznie według sufiksu `_YYYYMMDDHHMMSS.zip`
  (rzeczywisty MLS) lub `_YYYYMMDD_HHMMSS.zip` (starszy przykład).
- Czeka 10 minut od ostatniej zmiany pliku, blokuje równoległe uruchomienia i
  zatwierdza każdą paczkę w transakcji bazy.
- Sprawdza strukturę ZIP, CRC, rozmiary, XML, identyfikatory, daty i obrazy;
  odrzuca ścieżki, dowiązania, DTD i encje.
- Deduplikuje paczki i zdjęcia przez SHA-256. Przetworzone ZIP-y trafiają do
  `archive`, błędne do `errors`. Archiwum ZIP ma retencję 7 dni.
- Pomija błędne identyfikatory i powtarzające się rekordy, zapisuje raport
  oraz wysyła powiadomienie o pominięciach bez zatrzymywania poprawnych ofert.
- Po pełnym eksporcie usuwa nieużywane zdjęcia.
- Oferta jest kandydatem do publikacji tylko przy `offerExport=1` i `status=3`.
  To nie wystawia jej jeszcze publicznie.

## Weryfikacja wykonana na serwerze

- PHP 8.3.33; dostępne ZipArchive, SimpleXML i PDO MySQL.
- Walidacja przykładu MLS: 19 ofert, 64 zdjęcia.
- Import przykładu do bazy: 19 ofert i 1 paczka.
- Test paczki pełnej, a następnie przyrostowego usunięcia: stan końcowy 1 oferta
  i 2 zarejestrowane paczki.
- Przykładowa paczka została ponownie zaimportowana na potrzeby integracji frontendu.
- Składnia PHP, połączenie z bazą i uruchomienie importera bez paczek przeszły
  poprawnie.

## Następne kroki

1. Przekazać MLS dane osobnego konta FTP dopiero po zatwierdzeniu przez właściciela.
2. Odebrać pierwszą rzeczywistą paczkę pełną i sprawdzić liczbę ofert, rozmiar,
   nazewnictwo pliku oraz rzeczywiste akcje usuwania.
3. Zweryfikować jawną listę pól publicznych na rzeczywistych danych. Nie zwracać
   surowego `fields_json`, ponieważ źródło może zawierać dane kontaktowe i dokładne
   dane lokalizacji.
4. Dodać stronicowanie API przed publikacją pełnego zbioru około 9000 ofert.
5. Po rzeczywistym pełnym imporcie ocenić wykorzystanie 50 GB i ewentualnie
   zmienić retencję archiwum.

Źródła sprawdzone 27.09.2026:

- https://mls.org.pl/faq/ — ZIP przez FTP, paczki godzinowe i pełna synchronizacja.
- https://przetestuj.esticrm.pl/help/esticrmxml — pola formatu EstiCRM XML.
- https://info.esticrm.pl/article/eksport-przyrostowy-i-calosciowy/ — działanie
  eksportu przyrostowego i pełnego oraz zalecenie pełnego eksportu co 2 tygodnie.
