# Formatowanie opisów ofert — wdrożenie v1

## Zakres

Zaakceptowany wygląd: 16 px, interlinia 1.75, nagłówki, akapity, listy, pogrubienie i kursywa. Reguły nie dopisują opisów, zalet ani danych. Oryginał w `fields_json` pozostaje niezmieniony. Eksport zawiera tylko PL. Interfejs opisu EN/UK/RU wskazuje jawnie polski język źródła; NIE wdrożono automatycznego tłumaczenia ani żadnej płatnej usługi.

Frontend korzysta z `descriptionDocument` (schemaVersion 1, language pl, blocks), a przy starym API albo nieprawidłowych blokach używa istniejącego `description`. Wybrane samodzielne etykiety PL i co najmniej dwa kolejne zgodne punktory tworzą nagłówki/listy. Kwoty, jednostki, negacje, dodatkowe koszty i zastrzeżenia nie są przepisywane.

## GitHub Pages

Publikacja `main` dostarcza `oferta/script.js`, `assets/js/offer-description.mjs` i `assets/css/offer-description.css`. Nie aktualizuje PHP na Hostingerze. Nie zmieniono galerii, parametrów, formularza ani tłumaczeń pozostałych części strony. Moduł nie blokuje wczytania oferty: do czasu jego załadowania dostępny jest zwykły opis. Po publikacji może być potrzebne odświeżenie z pominięciem pamięci podręcznej.

## Hostinger — minimalna zmiana istniejącego API

Nie nadpisywać całego produkcyjnego `mls-offer.php` kopią z repozytorium: inne funkcje mogą być wdrożone niezależnie. Nie ruszać `mls-test.php`, `config.php`, importera, bazy ani zdjęć.

Najpierw zapisać kopię obecnego `public_html/api/mls-offer.php` poza publicznym katalogiem. Sprawdzić, że PHP ma ext-dom (bez niego opis HTML bezpiecznie wróci do zwykłego tekstu).

1. Wgrać nowy `server/mls/public/api/description-formatter.php` jako `public_html/api/description-formatter.php`. Nie nadpisuje istniejących plików. Wejście na sam adres tego helpera ma celowo zwrócić 404.

2. W `mls-offer.php`, po `declare(strict_types=1);` i obok istniejącego `require_once`, dodać:

```php
if (is_file(__DIR__ . '/description-formatter.php')) require_once __DIR__ . '/description-formatter.php';
```

W tablicy `$offer` pozostawić istniejące pole `description`, a bezpośrednio po nim dodać:

```php
'descriptionDocument' => function_exists('mazurDescriptionDocument')
    ? mazurDescriptionDocument(firstText($fields, 'descriptionWebsite', 'description')) : null,
'descriptionLanguage' => 'pl',
```

3. Zastąpić wyłącznie dotychczasową funkcję `publicDescription()` poniższą wersją. Zachowuje granice bloków także dla starszego klienta i naprawia zamianę twardych spacji na nowe linie:

```php
function publicDescription(string $value): string
{
    $value = preg_replace('~<(?:br|hr)\b[^>]*>|</(?:p|div|h[1-6]|li|ul|ol|section|article|blockquote|tr|td|th)\s*>~i', "\n", $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\r\n", "\r", "\u{00A0}", "\u{202F}"], ["\n", "\n", ' ', ' '], $value);
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
    return trim($value);
}
```

Kontrola składni przed publikacją: `php -l public_html/api/mls-offer.php` oraz `php -l public_html/api/description-formatter.php` (ścieżki zależą od bieżącego katalogu SSH). Nie udostępniać endpointu do uruchamiania poleceń PHP.

## Sprawdzenie po aktywacji

Na istniejącej aktywnej ofercie sprawdzić HTTP 200, tekst, pełną galerię i formularz. API szczegółów powinno zawierać `offer.descriptionDocument.schemaVersion: 1`, `language: pl` i bloki. `null` oznacza użycie bezpiecznego fallbacku, np. brak ext-dom, przekroczone limity lub nieprawidłowy format.

Sprawdzić co najmniej kilka rzeczywistych opisów HTML/tekstowych, oferty z opłatami i negacjami, PL/EN/UK/RU oraz ekran mobilny. Usuniętych wcześniej granic HTML nie da się pewnie odtworzyć ze starego `description`; pełny efekt wymaga odczytu surowego opisu przez nowy helper. Obecna zmiana formatuje opis przy odczycie API, bez zmiany importera i bez ponownego importu zdjęć. Cache/tłumaczenia po imporcie to osobny etap.

## Bezpieczeństwo i ograniczenia

Helper parsuje opis jako dane, nigdy nie zwraca HTML do wstawienia przez innerHTML. DOMDocument służy jedynie do ekstrakcji tekstu i dozwolonych typów bloków, nie do sanitizacji/serializacji HTML. Atrybuty źródłowe, skrypty i nietekstowe zasoby są odrzucane; odnośniki pozostają tekstem. Tabele są spłaszczane kolejno. Renderer tworzy nowe elementy i węzły tekstowe. Limity rozmiaru/głębokości i błędy mają prowadzić do fallbacku, nie do niedostępności całej oferty.

Nie jest to audyt wszystkich danych produkcyjnych. Testy wykorzystują dane syntetyczne. Nie przenoszą kolorów/fontów z CRM ani formatowania zapisanego wyłącznie w CSS. Nie uruchomiono usług tłumaczenia.

## Testy i wycofanie

`php scripts/test-description-formatter.php --require-dom` sprawdza parser, zachowanie treści i przypadki bezpieczeństwa. `node --test scripts/test-offer-description.mjs` sprawdza strukturę, stary API i fallback. Workflow `Description formatter checks` sprawdza także składnię API i skryptu strony.

W przypadku problemu przywrócić lokalną kopię `mls-offer.php`. Nowy frontend działa także ze starym API. Nie kasować oryginalnych opisów, obrazów ani bazy. W razie potrzeby wycofać commit frontendu przez zwykły revert, nie force push.
