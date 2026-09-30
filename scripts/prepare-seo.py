"""Generate SEO metadata and sitemap for existing routes; no new content pages.

Run from any directory: python3 scripts/prepare-seo.py
BASE is the currently deployed origin. Change it when the production domain moves.
"""
import json
import re
from pathlib import Path
from html import escape
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
BASE = 'https://mazurestate.pl/'
LANGS = ['pl', 'en', 'uk', 'ru']
NAMES = {
    '': ['Agencja nieruchomości Warszawa', 'Real estate agency in Warsaw', 'Агенція нерухомості у Варшаві', 'Агентство недвижимости в Варшаве'],
    'doradztwo/': ['Doradztwo nieruchomości Warszawa', 'Property advisory in Warsaw', 'Консультації з нерухомості у Варшаві', 'Консультации по недвижимости в Варшаве'],
    'dla-deweloperow/': ['Obsługa sprzedaży dla deweloperów', 'Property sales support for developers', 'Супровід продажів для забудовників', 'Сопровождение продаж для застройщиков'],
    'lokal-medyczny/': ['Lokal pod gabinet medyczny Warszawa', 'Medical premises in Warsaw', 'Приміщення для медичного кабінету у Варшаві', 'Помещение для медицинского кабинета в Варшаве'],
    'lokal-gastronomiczny/': ['Lokal pod restaurację, kawiarnię i bar Warszawa', 'Restaurant, café and bar premises in Warsaw', 'Приміщення для ресторану, кафе та бару у Варшаві', 'Помещение для ресторана, кафе и бара в Варшаве'],
    'grunt-pod-budowe/': ['Zakup i analiza gruntu pod budowę', 'Development land purchase and assessment', 'Купівля та перевірка землі під забудову', 'Покупка и проверка земли под застройку'],
    'mieszkanie-pod-wynajem/': ['Zakup mieszkania pod wynajem Warszawa', 'Buy-to-let investment in Warsaw', 'Купівля квартири для оренди у Варшаві', 'Покупка квартиры для аренды в Варшаве'],
    'kim-jestesmy/': ['Poznaj zespół MazurEstate', 'Meet the MazurEstate team', 'Познайомтеся з командою MazurEstate', 'Познакомьтесь с командой MazurEstate'],
    'domy/': ['Zakup i wynajem domu Warszawa i okolice', 'Buy or rent a house in the Warsaw area', 'Купівля та оренда будинку у Варшаві й околицях', 'Покупка и аренда дома в Варшаве и окрестностях'],
    'dzialki/': ['Działki Warszawa i okolice — pomoc w wyborze', 'Land in the Warsaw area — selection support', 'Земельні ділянки у Варшаві й околицях', 'Земельные участки в Варшаве и окрестностях'],
    'lokale-komercyjne/': ['Lokale komercyjne Warszawa — najem i zakup', 'Commercial property in Warsaw — rent and purchase', 'Комерційні приміщення у Варшаві — оренда й купівля', 'Коммерческие помещения в Варшаве — аренда и покупка'],
    'polityka-prywatnosci/': ['Polityka prywatności', 'Privacy policy', 'Політика конфіденційності', 'Политика конфиденциальности'],
    'polityka-cookies/': ['Polityka ciasteczek', 'Cookie policy', 'Політика файлів cookie', 'Политика файлов cookie'],
    'regulamin/': ['Regulamin strony', 'Website terms', 'Правила користування сайтом', 'Правила использования сайта'],
    'wyniki-wyszukiwania/': ['Wyszukiwanie nieruchomości', 'Property search', 'Пошук нерухомості', 'Поиск недвижимости'],
    'oferta/': ['Przykładowa oferta nieruchomości', 'Sample property listing', 'Приклад пропозиції нерухомості', 'Пример предложения недвижимости'],
}
DETAILS = {
    '': ['Sprzedaż, zakup, wynajem i zarządzanie nieruchomościami w Warszawie. Obsługa w języku polskim, angielskim, ukraińskim i rosyjskim.', 'Property sales, purchases, rentals and management in Warsaw. Service in Polish, English, Ukrainian and Russian.', 'Продаж, купівля, оренда та управління нерухомістю у Варшаві. Обслуговування польською, англійською, українською й російською.', 'Продажа, покупка, аренда и управление недвижимостью в Варшаве. Обслуживание на польском, английском, украинском и русском.'],
    'lokal-medyczny/': ['Pomoc w wyborze lokalu pod gabinet lub klinikę. Weryfikacja możliwości adaptacji i koordynacja specjalistów przed podpisaniem umowy.', 'Find premises for a practice or clinic. Assess adaptation options and coordinate specialists before signing a lease.', 'Допомога з вибором приміщення для кабінету або клініки. Перевірка можливостей адаптації та координація фахівців до договору.', 'Помощь с выбором помещения для кабинета или клиники. Проверка возможностей адаптации и координация специалистов до договора.'],
    'lokal-gastronomiczny/': ['Znajdź lokal pod restaurację, kawiarnię lub bar. Sprawdź instalacje, logistykę i możliwości adaptacji z pomocą MazurEstate.', 'Find premises for a restaurant, café or bar. Review utilities, logistics and adaptation options with MazurEstate.', 'Знайдіть приміщення для ресторану, кафе або бару. Перевірте мережі, логістику й можливості адаптації з MazurEstate.', 'Найдите помещение для ресторана, кафе или бара. Проверьте сети, логистику и возможности адаптации с MazurEstate.'],
    'grunt-pod-budowe/': ['Pomagamy wybrać i sprawdzić grunt w Warszawie i okolicach: stan prawny, plan, dojazd, media oraz możliwości realizacji projektu.', 'We help select and assess land in the Warsaw area: title, planning, road access, utilities and project feasibility.', 'Допомагаємо обрати й перевірити землю у Варшаві та околицях: правовий стан, планування, під’їзд, мережі й можливості забудови.', 'Помогаем выбрать и проверить землю в Варшаве и окрестностях: правовой статус, планирование, подъезд, сети и возможности застройки.'],
    'mieszkanie-pod-wynajem/': ['Od wyszukania i zakupu mieszkania po projekt, wykończenie, wynajem i zarządzanie. Zaplanuj inwestycję w Warszawie z MazurEstate.', 'From apartment search and purchase to design, fit-out, rental and management. Plan your Warsaw investment with MazurEstate.', 'Від пошуку й купівлі квартири до проєкту, ремонту, оренди та управління. Сплануйте інвестицію у Варшаві з MazurEstate.', 'От поиска и покупки квартиры до проекта, ремонта, аренды и управления. Спланируйте инвестицию в Варшаве с MazurEstate.'],
}
NOINDEX = {'wyniki-wyszukiwania/', 'oferta/'}
SERVICE = {'doradztwo/', 'dla-deweloperow/', 'lokal-medyczny/', 'lokal-gastronomiczny/', 'grunt-pod-budowe/', 'mieszkanie-pod-wynajem/', 'domy/', 'dzialki/', 'lokale-komercyjne/'}
pages = {}
for route, names in NAMES.items():
    descriptions = DETAILS.get(route, [
        f'{names[0]}. Poznaj zakres usług i zasady współpracy z MazurEstate.' if route in SERVICE else f'{names[0]} — informacje MazurEstate.',
        f'{names[1]}. Explore services and how to work with MazurEstate.' if route in SERVICE else f'{names[1]} — information from MazurEstate.',
        f'{names[2]}. Дізнайтеся про послуги й умови співпраці з MazurEstate.' if route in SERVICE else f'{names[2]} — інформація MazurEstate.',
        f'{names[3]}. Узнайте об услугах и условиях сотрудничества с MazurEstate.' if route in SERVICE else f'{names[3]} — информация MazurEstate.',
    ])
    pages[route] = {'names': dict(zip(LANGS, names)), 'descriptions': dict(zip(LANGS, descriptions)), 'index': route not in NOINDEX, 'service': route in SERVICE}

config = {'base': BASE, 'languages': LANGS, 'pages': pages}
(ROOT / 'assets/js/seo-config.js').write_text('/* Generated by scripts/prepare-seo.py. */\nwindow.MAZUR_SEO = ' + json.dumps(config, ensure_ascii=False, indent=2) + ';\n')

def url_for(route, lang):
    return BASE + ('' if lang == 'pl' else lang + '/') + route

for route, page in pages.items():
    path = ROOT / route / 'index.html'
    source = path.read_text()
    source = re.sub(r'\s*<!-- SEO START -->.*?<!-- SEO END -->\s*', '\n', source, flags=re.S)
    source = re.sub(r'<title>.*?</title>', '', source, flags=re.S | re.I)
    source = re.sub(r'<meta\s+name=["\'](?:description|robots)["\'][^>]*>', '', source, flags=re.I)
    source = re.sub(r'^[ \t]+$', '', source, flags=re.M)
    source = re.sub(r'\n{3,}', '\n\n', source)
    prefix = '../' if route else ''
    def seo_tags(lang):
        tags = ['<!-- SEO START -->', f'<title>{escape(page["names"][lang])} | MazurEstate</title>',
                f'<meta name="description" content="{escape(page["descriptions"][lang], quote=True)}">',
                f'<meta name="robots" content="{"index,follow,max-image-preview:large" if page["index"] else "noindex,follow"}">',
                f'<link rel="canonical" href="{url_for(route, lang)}">']
        if page['index']:
            for alternate in LANGS:
                tags.append(f'<link rel="alternate" hreflang="{alternate}" href="{url_for(route, alternate)}">')
            tags.append(f'<link rel="alternate" hreflang="x-default" href="{url_for(route, "pl")}">')
        tags += [f'<script src="{prefix}assets/js/language-routing.js"></script>',
                 f'<script src="{prefix}assets/js/seo-config.js"></script>',
                 f'<script src="{prefix}assets/js/seo.js"></script>', '<!-- SEO END -->']
        return '\n'.join(tags)
    source = source.replace('</head>', '\n' + seo_tags('pl') + '\n</head>', 1)
    # Preserve existing geometry and copy; defer only below-the-fold static images.
    count = [0]
    def image_attrs(match):
        tag = match.group(0)
        tag = re.sub(r'\s*/>$', '>', tag)
        count[0] += 1
        if count[0] > 3 and 'loading=' not in tag:
            tag = tag[:-1] + ' loading="lazy">'
        if 'decoding=' not in tag:
            tag = tag[:-1] + ' decoding="async">'
        return tag
    source = re.sub(r'<img\b[^>]*>', image_attrs, source, flags=re.I)
    path.write_text(source)
    for lang in LANGS[1:]:
        translated = source.replace('<html lang="pl">', f'<html lang="{lang}">', 1)
        translated = translated.replace('<head>', f'<head><base href="/{route}">', 1)
        translated = re.sub(r'<!-- SEO START -->.*?<!-- SEO END -->', seo_tags(lang), translated, count=1, flags=re.S)
        target = ROOT / lang / route / 'index.html'
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(translated)

NS = 'http://www.sitemaps.org/schemas/sitemap/0.9'
XHTML = 'http://www.w3.org/1999/xhtml'
ET.register_namespace('', NS)
ET.register_namespace('xhtml', XHTML)
tree = ET.Element(f'{{{NS}}}urlset')
for route, page in pages.items():
    if not page['index']:
        continue
    for lang in LANGS:
        item = ET.SubElement(tree, f'{{{NS}}}url')
        ET.SubElement(item, f'{{{NS}}}loc').text = url_for(route, lang)
        for alternate in LANGS + ['x-default']:
            ET.SubElement(item, f'{{{XHTML}}}link', rel='alternate', hreflang=alternate, href=url_for(route, alternate if alternate != "x-default" else "pl"))
ET.indent(tree)
ET.ElementTree(tree).write(ROOT / 'sitemap.xml', encoding='utf-8', xml_declaration=True)
(ROOT / 'robots.txt').write_text('# GitHub project Pages serves this file below the origin root.\n# Submit sitemap.xml directly in Search Console; install this policy at /robots.txt on the final domain.\nUser-agent: *\nAllow: /\nSitemap: ' + BASE + 'sitemap.xml\n')
print(f'Prepared {len(pages)} existing pages; {sum(p["index"] for p in pages.values()) * len(LANGS)} sitemap URLs.')
