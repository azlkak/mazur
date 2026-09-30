"""Validate generated language pages, canonical URLs and sitemap entries."""
from pathlib import Path
from urllib.parse import urlparse
import re
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
LANGS = ('pl', 'en', 'uk', 'ru')
NS = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9', 'x': 'http://www.w3.org/1999/xhtml'}
root = ET.parse(ROOT / 'sitemap.xml').getroot()
urls = root.findall('s:url', NS)
assert len(urls) == 56, f'Expected 56 indexable URLs, got {len(urls)}'
for entry in urls:
    url = entry.find('s:loc', NS).text
    parsed = urlparse(url)
    assert not parsed.query and parsed.netloc == 'mazurestate.pl', url
    file = ROOT / parsed.path.lstrip('/') / 'index.html'
    assert file.is_file(), f'Missing {file}'
    html = file.read_text()
    lang = parsed.path.strip('/').split('/')[0]
    lang = lang if lang in LANGS[1:] else 'pl'
    assert f'<html lang="{lang}">' in html, file
    assert f'<link rel="canonical" href="{url}">' in html, file
    assert 'assets/js/language-routing.js' in html, file
    alternates = entry.findall('x:link', NS)
    assert {link.attrib['hreflang'] for link in alternates} == {*LANGS, 'x-default'}, url
    for link in alternates:
        alternate = link.attrib['href']
        assert not urlparse(alternate).query, alternate
        assert (ROOT / urlparse(alternate).path.lstrip('/') / 'index.html').is_file(), alternate
for route in ('wyniki-wyszukiwania', 'oferta'):
    for lang in LANGS:
        folder = ROOT / ('' if lang == 'pl' else lang) / route
        html = (folder / 'index.html').read_text()
        assert '<meta name="robots" content="noindex,follow">' in html, folder
print(f'Validated {len(urls)} sitemap URLs and 8 noindex pages.')
