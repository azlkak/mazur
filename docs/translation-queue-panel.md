# Podgląd kolejki tłumaczeń w panelu ofert

Panel `featured-admin.php` udostępnia po zalogowaniu odczytowy endpoint
`?action=translation-status`. Podgląd pobiera dane przy wejściu w zakładkę
oraz przyciskiem „Odśwież”; nie odpytuje serwera cyklicznie. Po próbie ponowienia
błędnych tłumaczeń pobiera świeży wynik. Sam odczyt nie uruchamia workera ani płatnych wywołań API.
Panel jest podzielony na zakładki: status integracji, status tłumaczeń i
wyświetlanie ofert. Odświeżana jest tylko aktualnie oglądana zakładka monitoringu.
Adresy `#integration`, `#translations` i `#offers` pozwalają otworzyć ją
bezpośrednio; wybór zostaje przy odświeżeniu strony. Zakładki obsługują klawisze
strzałek w lewo/prawo, Home i End, a zmiana zakładki nie kasuje edycji ofert.

- Tytuły/opisy i wspólny słownik parametrów mają niezależne liczniki stanów
  oraz podział EN/UK/RU. Są to zadania fragment × język, nie liczby ofert.
- `done` obejmuje też stare fragmenty zamknięte jako `stale`; panel osobno
  pokazuje liczbę takich pominiętych zadań. Czas ostatniego ukończenia ich nie
  uwzględnia. Ostatnia aktywność oznacza zmianę rekordu, nie dowód działania crona.
- Licznik gotowych tekstów obejmuje oferty `publishable=1` w imporcie, również
  ręcznie ukryte na portalu. Wymaga zgodnego źródła, ID, podpisu treści,
  zsynchronizowanego hasha paczki, niepustego tytułu i dokumentu opisu w danym
  języku — zgodnie z publicznym czytnikiem tłumaczeń.
- Lista ostatnich problemów jest ograniczona do ośmiu rekordów. Surowe błędy,
  konfiguracja i klucze API nie są zwracane.
- Brak tabel lub błąd odczytu jednej kolejki daje stan niedostępny, nie zero.
  Awaria podglądu tłumaczeń nie blokuje istniejącego monitoringu importów.

## Ręczne ponawianie

Każda kolejka ma przycisk „Ponów błędne”, widoczny przy `failed > 0`.
Pierwsze kliknięcie pokazuje liczbę zadań, informację o płatnym API i wymaga
drugiego potwierdzenia. Akcja POST `retry-translations` wymaga dozwolonej
sesji, poprawnego Origin, niepustego CSRF, `confirmed: true` i aktualnej liczby
błędnych zadań. Licznik zmieniony od podglądu wymaga odświeżenia i ponownego
potwierdzenia. Podczas pracy translatora akcja zwraca stan zajęty.

Ponawiana jest wyłącznie wybrana kolejka oraz stan `failed`: zadania wracają
do `pending`, próby zaczynają się od zera, a ostatni błąd jest czyszczony.
Gotowe i przetwarzane zadania nie są zmieniane. Endpoint korzysta z tej samej
blokady co worker i nie wywołuje OpenAI. Zwykły worker nadal wykonuje najwyżej
30 zadań na przebieg. Błędy `retry` mają już własne automatyczne ponawianie.

## Wdrożenie

Do istniejącego `public_html/api/` wgrać po diffie i backupie:
`featured-admin.php`, `.html`, `.js`, `.css` oraz nowy helper
`translation-queue-status.php` i `translation-queue-retry.php`. Aktualizować helpery przed endpointem.
Nie są potrzebne nowe tabele ani zmiany crona, workera i konfiguracji OpenAI.
Panel korzysta z dotychczasowych sesji i listy dozwolonych adresów.

Testy `Translation panel checks` używają izolowanej bazy MySQL z danymi
przykładowymi. Sprawdzają poprawność agregacji i gotowości ofert, autoryzację,
brak ujawniania sekretów, brak modyfikacji kolejki oraz częściowe migracje.
