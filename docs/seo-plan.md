# SEO MazurEstate — plan bez nowych podstron

Stan: 6 września 2026. Zakres: istniejący serwis, bez publikacji bloga i nowych landingów.

## Co zostało wdrożone

- Mapa XML: 14 indeksowalnych stron × 4 języki = 56 adresów. Warianty PL, EN, UK i RU są wzajemnie powiązane przez hreflang, z polskim x-default.
- Osobne tytuły i opisy dla każdej strony i języka; canonical wskazuje właściwy język, bez parametrów śledzących i aliasu index.html.
- Przykładowa oferta i demonstracyjne wyniki wyszukiwania: noindex,follow; poza mapą. Po uruchomieniu rzeczywistych ofert trzeba opracować osobne reguły dla ofert aktywnych, wycofanych i filtrów.
- Dane uporządkowane firmy, witryny, strony i usług. Bez wymyślonych ocen, realizacji, biura dostępnego dla klientów ani gwarancji wyników inwestycji.
- Linki do specjalistycznych usług w stopkach, także na stronie głównej. Semantyczny main na stronie głównej. Leniwe ładowanie dalszych zdjęć, bez zmiany wyglądu.
- Generator zapewniający spójność mapy i metadanych przy kolejnych aktualizacjach.

## Istotne ograniczenia

Mapa ułatwia odkrywanie adresów — nie gwarantuje indeksacji ani wyższej pozycji. Meta description może wpłynąć na sposób prezentacji wyniku, ale Google może wybrać inny fragment.

Strona działa na GitHub Pages pod /mazur/. Plik robots.txt w tym katalogu NIE zastępuje pliku w katalogu głównym hosta. Dlatego mapę należy zgłosić bezpośrednio w Google Search Console. Po przejściu na własną domenę robots.txt powinien znajdować się pod /robots.txt, a adres bazowy w generatorze należy zmienić i ponownie wygenerować pliki. Nie blokujemy robotom dostępu do stron noindex: muszą móc odczytać dyrektywę.

Tłumaczenia nadal korzystają z JavaScript i ?lang=. Canonical jest ustawiany przez JS, aby statyczny polski canonical nie kolidował z innymi językami. Google musi wyrenderować stronę; inne roboty i podglądy społecznościowe mogą widzieć polski HTML. Docelowo warto rozważyć generowanie pełnego HTML dla każdego języka, ale obecny zakres nie zmienia struktury adresów. Nie jest to audyt jakości wszystkich przekładów.

Nie deklarujemy osiągniętych wyników Core Web Vitals: do tego potrzebne są pomiary laboratoryjne oraz dane rzeczywistych użytkowników. Nie dodajemy FAQ schema tylko dla efektu: zwykły serwis agencji nie powinien zakładać otrzymania rozszerzonych wyników FAQ.

## Co zrobić następnie

1. Ustalić docelową własną domenę przed większym inwestowaniem w SEO. Przy migracji zachować ścieżki, zmienić canonical/hreflang/mapę i zaplanować przekierowania ze starego hosta w zakresie jego możliwości.
2. Zweryfikować Search Console; zgłosić sitemap.xml. Sprawdzić reprezentatywny adres każdej usługi i języka narzędziem inspekcji URL: wyrenderowany HTML, wybrany canonical i dostępność indeksowania.
3. Zebrać punkt odniesienia: wyświetlenia, kliknięcia, zapytania, CTR i konwersje według landingu oraz języka. Kliknięcie telefonu nie jest jeszcze pozyskanym klientem — odróżniać kontakt od kwalifikowanego leada i podpisanej umowy.
4. Dopiero na tej podstawie korygować tytuły oraz treść. Priorytet: specjalistyczne usługi i pytania z intencją zakupu, nie walka wyłącznie o bardzo szerokie „nieruchomości Warszawa”.
5. Uzupełnić wiarygodność autentycznymi informacjami o zespole, procesie, doświadczeniu i opiniami z możliwym do wskazania źródłem. Nie tworzyć fikcyjnych historii klientów.
6. Profil firmy w Google prowadzić zgodnie z rzeczywistą obsługą klientów; brak dostępnego biura nie powinien być maskowany zdjęciami fikcyjnego biura. Spójne dane kontaktowe na stronie i w profilach.

## Blog: 36 tematów do rozważenia

To propozycje redakcyjne, nie potwierdzone wolumeny wyszukiwania. Priorytet A oznacza bliskość decyzji o skorzystaniu z usługi, nie gwarancję ruchu. Przed publikacją sprawdzić aktualne przepisy, konkurencję w wynikach i pytania z rozmów handlowych. Treści prawne, podatkowe i techniczne wymagają odpowiedniej weryfikacji specjalisty.

| # | Temat artykułu | Główna potrzeba czytelnika | Landing do powiązania | Priorytet |
|---|---|---|---|---|
| 1 | Mieszkanie pod wynajem w Warszawie: jak policzyć wynik po wszystkich kosztach? | Ocena opłacalności | mieszkanie-pod-wynajem/ | A |
| 2 | Rynek pierwotny czy wtórny pod wynajem — porównanie całego budżetu | Wybór rodzaju inwestycji | mieszkanie-pod-wynajem/ | A |
| 3 | Kawalerka czy dwa pokoje: jak dopasować mieszkanie do najemcy? | Dobór lokalu | mieszkanie-pod-wynajem/ | A |
| 4 | Zakup, remont i pierwszy najem: realistyczny harmonogram inwestycji | Zaplanowanie otwarcia najmu | mieszkanie-pod-wynajem/ | A |
| 5 | Wykończenie mieszkania na wynajem: gdzie warto dopłacić, a gdzie nie? | Racjonalny standard | mieszkanie-pod-wynajem/ | A |
| 6 | Zarządzanie najmem: co powinno obejmować i jak porównywać oferty? | Wybór operatora | mieszkanie-pod-wynajem/ | A |
| 7 | Jak weryfikować najemcę z poszanowaniem jego prywatności? | Ograniczenie ryzyka | mieszkanie-pod-wynajem/ | A |
| 8 | Pustostan, naprawy i rotacja: jaki bufor uwzględnić w planie najmu? | Plan finansowy | mieszkanie-pod-wynajem/ | B |
| 9 | Sprzedać czy nadal wynajmować? Jak przygotować porównanie scenariuszy | Decyzja właściciela | mieszkanie-pod-wynajem/ | B |
| 10 | Lokal pod gabinet: co sprawdzić przed podpisaniem umowy najmu? | Weryfikacja lokalu | lokal-medyczny/ | A |
| 11 | Lokal po gabinecie medycznym: dlaczego to nie zawsze gotowe rozwiązanie? | Ocena adaptacji | lokal-medyczny/ | A |
| 12 | Gabinet stomatologiczny: jak zweryfikować instalacje pod konkretny sprzęt? | Wykonalność techniczna | lokal-medyczny/ | A |
| 13 | Wentylacja gabinetu w nowym budynku: dokumenty, trasy i zgody | Uniknięcie opóźnień | lokal-medyczny/ | A |
| 14 | Dostępność gabinetu: jak sprawdzić układ, zanim zamówisz projekt? | Weryfikacja metrażu | lokal-medyczny/ | B |
| 15 | Lokal pod restaurację: lista dokumentów i pytań przed najmem | Przygotowanie decyzji | lokal-gastronomiczny/ | A |
| 16 | Wentylacja gastronomii: dlaczego sam szyb wentylacyjny nie wystarczy? | Ocena instalacji | lokal-gastronomiczny/ | A |
| 17 | Montaż centrali na dachu: jak sprawdzić transport, dostęp i zgody? | Ocena logistyki adaptacji | lokal-gastronomiczny/ | A |
| 18 | Kawiarnia bez pełnej kuchni: co zależy od menu, sprzętu i procesu? | Dobór lokalu do konceptu | lokal-gastronomiczny/ | A |
| 19 | Lokal po restauracji: co sprawdzić zamiast ufać poprzedniemu przeznaczeniu? | Ocena przejmowanego lokalu | lokal-gastronomiczny/ | A |
| 20 | Dostawy, odpady i hałas: ograniczenia restauracji niewidoczne na zdjęciach | Ryzyko operacyjne | lokal-gastronomiczny/ | B |
| 21 | Działka budowlana: jakie dokumenty zebrać przed ofertą zakupu? | Przygotowanie transakcji | grunt-pod-budowe/ | A |
| 22 | Droga na mapie a dostęp do drogi publicznej: co trzeba zweryfikować? | Weryfikacja dojazdu | grunt-pod-budowe/ | A |
| 23 | Media przy granicy działki: jak sprawdzić możliwość i termin przyłączenia? | Koszty i wykonalność | grunt-pod-budowe/ | A |
| 24 | Badania gruntu przed zakupem: kiedy i o jaki zakres zapytać geotechnika? | Ograniczenie ryzyka budowy | grunt-pod-budowe/ | A |
| 25 | MPZP, warunki zabudowy i plan ogólny: co sprawdzić dla konkretnej działki? | Możliwości zabudowy | grunt-pod-budowe/ | A |
| 26 | Tania działka, droga inwestycja: jak porównać całkowite nakłady? | Porównanie gruntów | grunt-pod-budowe/ | B |
| 27 | Dom pod Warszawą: koszty i ograniczenia, których nie widać w cenie | Wybór domu | domy/ | B |
| 28 | Oględziny domu z rynku wtórnego: kiedy zaangażować inspektora? | Ocena techniczna | domy/ | A |
| 29 | Najem lokalu komercyjnego: jak porównać koszty poza czynszem? | Porównanie ofert | lokale-komercyjne/ | A |
| 30 | Wakacje czynszowe i adaptacja: co ustalić, zanim podpiszesz najem? | Negocjacje warunków | doradztwo/ | A |
| 31 | Zakup nieruchomości przez cudzoziemca w Polsce: jak przygotować dokumenty? | Przygotowanie formalne | doradztwo/ | A |
| 32 | Zakup mieszkania w Polsce, gdy mieszkasz za granicą: etapy i pełnomocnictwa | Organizacja transakcji | doradztwo/ | A |
| 33 | Doradca nieruchomości: o co zapytać przed rozpoczęciem współpracy? | Wybór partnera | doradztwo/ | B |
| 34 | Obsługa sprzedaży inwestycji deweloperskiej: co zlecić na zewnątrz? | Zakres outsourcingu | dla-deweloperow/ | A |
| 35 | Dlaczego zapytania o mieszkania nie zamieniają się w rezerwacje? | Diagnoza procesu sprzedaży | dla-deweloperow/ | A |
| 36 | Jak przygotować ofertę inwestycji dla klientów obcojęzycznych? | Rozszerzenie sprzedaży | dla-deweloperow/ | B |

### Jak publikować, żeby to miało sens biznesowy

Zacząć od 1, 6, 10, 15, 21 i 31. To propozycja oparta na intencji i ofercie firmy, nie badaniu wolumenów. Każdy tekst powinien rozwiązywać jeden problem, zawierać konkretną checklistę lub przykład obliczenia, mieć autora/weryfikatora i link do jednej właściwej usługi z prostym CTA. Przykłady hipotetyczne wyraźnie oznaczać; realizacje i liczby tylko po potwierdzeniu.

Nie tłumaczyć automatycznie całego planu na cztery języki. Najpierw sprawdzić popyt na usługę w danym języku; teksty dla cudzoziemców potrzebują innego kontekstu, nie tylko przekładu. Artykuły powinny uzupełniać landingi, a nie kopiować ich treści i konkurować o dokładnie tę samą intencję.

## Źródła zasad technicznych

- [Google: JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)
- [Google: canonical i duplikaty](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Google: mapa witryny](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Google: witryny wielojęzyczne](https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites)
