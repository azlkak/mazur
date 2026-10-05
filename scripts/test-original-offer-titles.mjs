import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const translations = readFileSync(new URL('../assets/js/page-translations.js', import.meta.url), 'utf8');

for (const lang of ['en', 'uk', 'ru']) {
  test(`${lang} offer keeps the title supplied by the offer API`, () => {
    const title = 'Penthouse z Tarasem na Dachu na Mokotowie | MazurEstate';
    const document = {
      title,
      documentElement: { lang },
      querySelector() { throw new Error('Offer content must not be replaced by a static translation'); },
      querySelectorAll() { throw new Error('Offer content must not be replaced by a static translation'); }
    };
    runInNewContext(translations, {
      window: { MAZUR_LANG: lang },
      location: { pathname: `/${lang}/oferta/`, search: '?id=34964566021' },
      document,
      URLSearchParams
    });
    assert.equal(document.title, title);
  });
}
