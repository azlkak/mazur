# Tłumaczenie parametrów ofert

Etykiety interfejsu karty są stałe i tłumaczone w `oferta/script.js`. Zmienne
wartości pochodzące z MLS/EstiCRM — atuty z `tagList`, typ nieruchomości,
słowne oznaczenie piętra oraz województwo — są tłumaczone przez istniejący
prywatny worker OpenAI. Liczby, cena, powierzchnia, rok i numer oferty nie
wymagają tłumaczenia. Nazwy miasta i dzielnicy pozostają nazwami własnymi.

`offer_parameter_translations` przechowuje jeden przekład na język i unikalną
frazę PL, wspólny dla wszystkich ofert. `offer_parameter_sources` zapamiętuje
paczkę ostatnio zeskanowaną dla danej oferty. Import bez zmiany tekstu nie
zleca kolejnych wywołań API. Publiczny odczyt korzysta tylko z zapisanych
przekładów; gdy nowej frazy jeszcze nie ma, pokazuje polski oryginał.
Tytuł i opis mają osobny obieg i nie są przez tę zmianę unieważniane.

Kolejność wdrożenia:

1. W bazie `u101822986_mls` uruchomić migrację
   `server/mls/private/migrations/20261013_offer_parameters.sql`.
2. Wgrać prywatny `offer-parameters.php` i nowy `bin/translate-offers.php`
   do katalogu importera oraz publiczny `offer-parameters.php` i
   `mls-offer.php` do katalogu API. Oba helpery mają zgodne reguły wyboru fraz.
3. Opublikować publiczny build witryny, obejmujący kartę oferty oraz
   `api/offer-parameters.php` potrzebny do modelu SEO.
4. Jednorazowo uruchomić `translate-offers.php --sync`, aby zarejestrować
   istniejące atuty. Harmonogram musi uruchamiać `--sync` po imporcie oraz
   `--work` do obsługi kolejki. `--work` dzieli istniejący limit 30 wywołań na przebieg między
   opisami i parametrami; nie dodaje drugiej puli 30 wywołań.
5. Śledzić `offer_parameter_translations` według `state`. Dla błędów
   `failed` sprawdzić `last_error` przed `--retry-failed`. Sprawdzić na EN,
   UK i RU kartę z wolnymi tagami, np. „Dla studenta”, oraz kartę z typem
   i słownym piętrem. Przekład może pojawić się dopiero po wykonaniu kolejki.

Przy wycofaniu można przywrócić poprzedni worker, API i skrypt strony.
Nowe tabele mogą pozostać w bazie; nie ingerują w obecne tłumaczenia tytułów
i opisów ani w źródłowe dane ofert.
