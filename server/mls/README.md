# MLS — środowisko Hostinger

Status: importer wdrożony i uruchomiony 27.09.2026 na hostingu Hostinger dla
`darkgreen-rabbit-981798.hostingersite.com`. Ograniczone publiczne API oraz frontend
zostały uruchomione na danych z przykładowej paczki EstiCRM. API zwraca wyłącznie
jawnie wybrane pola i nie publikuje danych kontaktowych ani dokładnego adresu.

## Serwer

```text
/home/u101822986/domains/darkgreen-rabbit-981798.hostingersite.com/mls/
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
- Konto FTP MLS jest ograniczone do katalogu `mls/incoming`.
- Zadanie Cron w hPanelu: raz na godzinę (`0 * * * *`).
- Polecenie: `/usr/bin/php /home/u101822986/domains/darkgreen-rabbit-981798.hostingersite.com/mls/bin/import.php`.
- Importer jest włączony. Dane dostępowe nie zostały przekazane MLS.
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
- Obsługuje `export="incremental"` z akcjami `update` i `delete`.
- Obsługuje `export="full"`; zawartość paczki jest stanem autorytatywnym, więc
  oferty nieobecne w niej są usuwane z bazy.
- Przetwarza paczki chronologicznie według sufiksu `_YYYYMMDD_HHMMSS.zip`.
- Czeka 10 minut od ostatniej zmiany pliku, blokuje równoległe uruchomienia i
  zatwierdza każdą paczkę w transakcji bazy.
- Sprawdza strukturę ZIP, CRC, rozmiary, XML, identyfikatory, daty i obrazy;
  odrzuca ścieżki, dowiązania, DTD i encje.
- Deduplikuje paczki i zdjęcia przez SHA-256. Przetworzone ZIP-y trafiają do
  `archive`, błędne do `errors`. Archiwum ZIP ma retencję 7 dni.
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
