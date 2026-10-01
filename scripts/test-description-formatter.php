<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/description-formatter.php';
$passed = 0;
function check(bool $condition, string $name): void { global $passed; if (!$condition) throw new RuntimeException('FAIL: ' . $name); echo 'PASS: ' . $name . PHP_EOL; $passed++; }
function flatten(array $blocks): string { $texts=[];foreach($blocks as $b){if($b['type']==='list'){foreach($b['items'] as $i)$texts[]=flatten($i['blocks']);}else $texts[]=implode('',array_column($b['runs'],'text'));}return implode("\n",$texts); }
check(mazurDescriptionDocument('')['blocks'] === [], 'empty description');
$d=mazurDescriptionDocument("Koszty:\nCzynsz 1\u{00A0}200 zł.\nNie zawiera opłat.");
check($d['schemaVersion']===1 && $d['language']==='pl', 'version and source language');
check(flatten($d['blocks'])==="Koszty:\nCzynsz 1 200 zł.\nNie zawiera opłat.", 'text, amounts, NBSP and negation preserved');
check(flatten(mazurDescriptionDocument('Koszt 1&nbsp;200 zł &amp; media')['blocks'])==='Koszt 1 200 zł & media','entities in plain source');
check(mazurDescriptionDocument(str_repeat('a',800001))===null,'oversized source falls back without truncation');
check(mazurDescriptionDocument("bad\xFF")===null,'invalid UTF8 falls back');
if (!class_exists(DOMDocument::class)) {
 check(mazurDescriptionDocument('<p>test</p>')===null,'missing DOM extension returns null');
 echo "DOM tests not run: ext-dom unavailable.\n";
 exit(in_array('--require-dom',$argv,true)?2:0);
}
$d=mazurDescriptionDocument('<p>Pierwszy.</p><p>Drugi.</p>');
check(flatten($d['blocks'])==="Pierwszy.\nDrugi.",'HTML paragraph boundaries');
$d=mazurDescriptionDocument('<h2>UKŁAD</h2><p>Salon <strong>25 m²</strong> i <em>kuchnia</em>.</p>');
check($d['blocks'][0]['type']==='heading','headings mapped to data');
check(flatten($d['blocks'])==="UKŁAD\nSalon 25 m² i kuchnia.",'inline spacing preserved');
check(($d['blocks'][1]['runs'][1]['strong']??false) && ($d['blocks'][1]['runs'][3]['em']??false),'strong and em retained');
$d=mazurDescriptionDocument('<p>Cena 889&nbsp;000 zł<br>Powierzchnia 110,67 m²</p>');
check(flatten($d['blocks'])==="Cena 889 000 zł\nPowierzchnia 110,67 m²",'NBSP not newlines; BR is newline');
$d=mazurDescriptionDocument('<ol start="3"><li>Etap A<ul><li>Dokument</li></ul></li><li value="8">Etap B</li></ol>');
check($d['blocks'][0]['start']===3 && $d['blocks'][0]['items'][1]['value']===8,'explicit list numbering');
check($d['blocks'][0]['items'][0]['blocks'][1]['type']==='list','nested lists');
$d=mazurDescriptionDocument('<ol reversed><li>A</li><li>B</li></ol>');
check($d['blocks'][0]['reversed']===true && $d['blocks'][0]['start']===2,'reversed list default start');
$d=mazurDescriptionDocument('<p onclick="alert(1)" style="color:red">Bezpieczny <a href="javascript:alert(1)">tekst</a>.</p><script>alert(1)</script><img src="https://example.invalid/tracker"><iframe src="https://example.invalid">hidden</iframe>');
check(flatten($d['blocks'])==='Bezpieczny tekst.','active and non-text elements omitted');
$json=json_encode($d,JSON_THROW_ON_ERROR);
check(!str_contains($json,'onclick')&&!str_contains($json,'href')&&!str_contains($json,'tracker')&&!str_contains($json,'style'),'attributes and remote resources never returned');
$d=mazurDescriptionDocument('<p>&lt;img src=x onerror=alert(1)&gt;</p>');
check(flatten($d['blocks'])==='<img src=x onerror=alert(1)>','entity-decoded markup is literal text');
$d=mazurDescriptionDocument('<table><tr><td>Opłata</td><td>500 zł</td></tr></table>');
check(flatten($d['blocks'])==="Opłata\n500 zł",'table cell text in source order');
check(mazurDescriptionDocument('<!DOCTYPE html><p>text</p>')===null,'custom document declaration falls back');
check(mazurDescriptionDocument(str_repeat('<div>',50).'x'.str_repeat('</div>',50))===null,'depth bounded');
check(mazurDescriptionDocument(str_repeat('<br>',15001))===null,'node count bounded');
$d=mazurDescriptionDocument('<pre>Linia 1\nLinia 2</pre>');
check($d!==null,'PRE parsed');
echo "Passed $passed PHP checks with ext-dom.\n";
