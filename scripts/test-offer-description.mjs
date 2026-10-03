import assert from 'node:assert/strict';
import test from 'node:test';
import { prepareDescription } from '../assets/js/offer-description.mjs';
const p = text => ({type:'paragraph',runs:[{text}]});
const document = blocks => ({schemaVersion:1,language:'pl',blocks});
const asOffer = blocks => ({description:'Legacy fallback.', descriptionDocument:document(blocks)});
const texts = blocks => blocks.flatMap(b => b.type==='list' ? b.items.flatMap(i=>texts(i.blocks)) : b.runs.map(r=>r.text)).join('\n');

test('Old API stays usable and detects unambiguous headings and consecutive bullets',()=>{
 const d=prepareDescription({description:'LOKALIZACJA:\n- Blisko parku.\n- Przystanek 300 m.\nOpłaty dodatkowo.'});
 assert.deepEqual(d.blocks.map(b=>b.type),['heading','list','paragraph']);
 assert.equal(d.blocks[1].items.length,2);
 assert.equal(texts(d.blocks),'LOKALIZACJA:\nBlisko parku.\nPrzystanek 300 m.\nOpłaty dodatkowo.');
});
test('Labels followed by text become readable sections without losing rich-text emphasis',()=>{
 const d=prepareDescription(asOffer([{type:'paragraph',runs:[{text:'Lokalizacja: '},{text:'Warszawa, blisko parku.',strong:true}]}]));
 assert.deepEqual(d.blocks,[{type:'heading',runs:[{text:'Lokalizacja'}]},{type:'paragraph',runs:[{text:'Warszawa, blisko parku.',strong:true}]}]);
});
test('Ordinary colons do not create guessed headings',()=>{
 const source='Cena: 900 000 zł. Kontakt: telefonicznie.';
 assert.deepEqual(prepareDescription({description:source}).blocks,[p(source)]);
});
test('Keeps Polish source language',()=>assert.equal(prepareDescription({description:'Tekst.'}).language,'pl'));
test('A lone bullet is not guessed into a list',()=>assert.equal(prepareDescription({description:'- Jedno zdanie.'}).blocks[0].type,'paragraph'));
test('Continuous ordered numbers preserve start',()=>{
 const l=prepareDescription({description:'3. A\n4. B'}).blocks[0]; assert.equal(l.type,'list'); assert.equal(l.start,3);
});
test('Discontinuous numbering stays literal',()=>assert.deepEqual(prepareDescription({description:'1. A\n3. B'}).blocks.map(b=>b.type),['paragraph','paragraph']));
test('Amounts, area, negation and postal code survive',()=>{
 const s='Cena 889\u00a0000 zł. Powierzchnia 110,67 m².\nNie zawiera opłat. Kod 05-270.';
 assert.equal(texts(prepareDescription({description:s}).blocks),s.replace('\u00a0',' '));
});
test('Does not invent emphasis or sections for prose',()=>{
 const s='Lokalizacja jest dobra. Czynsz wynosi 500 zł, bez mediów.';
 assert.deepEqual(prepareDescription({description:s}).blocks,[p(s)]);
});
test('Existing strong and em runs retained',()=>{
 const blocks=[{type:'heading',runs:[{text:'Standard'}]},{type:'paragraph',runs:[{text:'Salon '},{text:'25 m²',strong:true},{text:' i '},{text:'kuchnia',em:true}]}];
 assert.deepEqual(prepareDescription(asOffer(blocks)).blocks,blocks);
});
test('Lists preserve explicit values, reversed order and nesting',()=>{
 const blocks=[{type:'list',ordered:true,start:8,reversed:true,items:[{value:8,blocks:[p('A'),{type:'list',ordered:false,items:[{blocks:[p('Dokument')]}]}]},{value:6,blocks:[p('B')]}]}];
 assert.deepEqual(prepareDescription(asOffer(blocks)).blocks,blocks);
});
test('Invalid schema falls back without dropping original text',()=>assert.equal(texts(prepareDescription({description:'Cały opis.',descriptionDocument:{schemaVersion:99,language:'pl',blocks:[]}}).blocks),'Cały opis.'));
test('Invalid block type falls back',()=>assert.equal(texts(prepareDescription(asOffer([{type:'script',runs:[{text:'bad'}]}])).blocks),'Legacy fallback.'));
test('Invalid run falls back',()=>assert.equal(texts(prepareDescription(asOffer([{type:'paragraph',runs:[{text:null}]}])).blocks),'Legacy fallback.'));
test('No attributes or source styles passed to renderer',()=>{
 const o=asOffer([{...p('Bezpieczny'),style:'color:red',onclick:'alert(1)',runs:[{text:'Bezpieczny',href:'javascript:alert(1)',strong:'yes'}]}]);
 assert.deepEqual(prepareDescription(o).blocks,[p('Bezpieczny')]);
});
test('Literal markup is never parsed as HTML',()=>{
 const s='<img src=x onerror=alert(1)> & test';
 assert.equal(texts(prepareDescription(asOffer([p(s)])).blocks),s);
});
test('Depth limit falls back',()=>{
 let b=p('tekst'); for(let n=0;n<43;n++)b={type:'list',ordered:false,items:[{blocks:[b]}]};
 assert.equal(texts(prepareDescription(asOffer([b])).blocks),'Legacy fallback.');
});
test('Complexity limit falls back',()=>assert.equal(texts(prepareDescription(asOffer(Array.from({length:8000},()=>p('a')))).blocks),'Legacy fallback.'));
test('Oversize structured text falls back, never silently truncated',()=>assert.equal(texts(prepareDescription(asOffer([p('a'.repeat(800001))])).blocks),'Legacy fallback.'));
test('Empty description is valid and distinguished from malformed data',()=>assert.deepEqual(prepareDescription(asOffer([])).blocks,[]));
test('Null and absent text do not become literal null or undefined',()=>{
 assert.deepEqual(prepareDescription({description:null}).blocks,[]); assert.deepEqual(prepareDescription().blocks,[]);
});
test('Input objects are not mutated',()=>{
 const o=asOffer([p('UKŁAD:'),p('- A'),p('- B')]); const before=JSON.stringify(o); prepareDescription(o); assert.equal(JSON.stringify(o),before);
});
