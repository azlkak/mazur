# Podgląd kolejki tłumaczeń w panelu ofert

Panel `featured-admin.php` udostępnia po zalogowaniu odczytowy endpoint
`?action=translation-status`. Podgląd odświeża się co minutę podczas oglądania
panelu oraz przyciskiem. Nie uruchamia workera ani płatnych wywołań API.

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
  konfiguracja i klucze API nie są zwracane. Nie ma przycisku ponawiającego
  płatne zadania.
- Brak tabel lub błąd odczytu jednej kolejki daje stan niedostępny, nie zero.
  Awaria podglądu tłumaczeń nie blokuje istniejącego monitoringu importów.

## Wdrożenie

Do istniejącego `public_html/api/` wgrać po diffie i backupie:
`featured-admin.php`, `.html`, `.js`, `.css` oraz nowy helper
`translation-queue-status.php`. Aktualizować helper przed endpointem.
Nie są potrzebne nowe tabele ani zmiany crona, workera i konfiguracji OpenAI.
Panel korzysta z dotychczasowych sesji i listy dozwolonych adresów.

Testy `Translation panel checks` używają izolowanej bazy MySQL z danymi
przykładowymi. Sprawdzają poprawność agregacji i gotowości ofert, autoryzację,
brak ujawniania sekretów, brak modyfikacji kolejki oraz częściowe migracje.
