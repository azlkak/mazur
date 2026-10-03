<?php
declare(strict_types=1);
/**
 * Build allowlisted text/block data, NEVER HTML for insertion into the page.
 * Source fields remain unchanged. No database, network or translation calls.
 * Compatible with PHP 8.2+; HTML needs ext-dom. Failure returns null, letting
 * the caller keep the existing plain-text description and the offer available.
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

function mazurDescriptionDocument(string $source): ?array
{
    try {
        if (strlen($source) > 800000 || !preg_match('//u', $source)) return null;
        $clean = static fn(string $text): string => str_replace(["\r\n", "\r", "\u{00A0}", "\u{202F}"], ["\n", "\n", ' ', ' '], $text);
        $compact = static function (array $runs) use ($clean): array {
            $out = [];
            foreach ($runs as $run) {
                $text = preg_replace('/\s+/u', ' ', $clean($run['text'])) ?? $run['text'];
                $last = count($out) - 1;
                if ($last < 0 || str_ends_with($out[$last]['text'], ' ')) $text = ltrim($text, ' ');
                if ($text === '') continue;
                $next = ['text' => $text];
                if (!empty($run['strong'])) $next['strong'] = true;
                if (!empty($run['em'])) $next['em'] = true;
                if ($last >= 0 && !empty($out[$last]['strong']) === !empty($next['strong']) && !empty($out[$last]['em']) === !empty($next['em'])) $out[$last]['text'] .= $text;
                else $out[] = $next;
            }
            $last = count($out) - 1;
            if ($last >= 0) {
                $out[$last]['text'] = rtrim($out[$last]['text'], ' ');
                if ($out[$last]['text'] === '') array_pop($out);
            }
            return $out;
        };
        $isHtml = (bool)preg_match('~</?(?:p|div|br|hr|h[1-6]|strong|b|em|i|ul|ol|li|span|font|a|table|section|article|pre|blockquote|script|style|img|iframe|svg)\b[^>]*>~iu', $source);
        $blocks = [];
        if (!$isHtml) {
            foreach (preg_split('/\n+/u', $clean(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: [] as $line) {
                $runs = $compact([['text' => $line]]);
                if ($runs !== []) $blocks[] = ['type' => 'paragraph', 'runs' => $runs];
            }
        } else {
            if (!class_exists(DOMDocument::class) || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) return null;
            $dom = new DOMDocument('1.0', 'UTF-8');
            $previous = libxml_use_internal_errors(true);
            try {
                // Parsing only: there is no saveHTML(), no copied attributes,
                // no external entities and no network. All output is JSON text.
                $loaded = $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $source . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$loaded) return null;
            $root = $dom->getElementsByTagName('body')->item(0);
            if (!$root) return null;
            $drop = array_fill_keys(explode(' ', 'script style iframe object embed svg math noscript meta link head title base template canvas video audio source track img input button form select option textarea'), true);
            $blockTags = array_fill_keys(explode(' ', 'p div section article header footer blockquote address center figure figcaption dl dt dd table thead tbody tfoot tr td th li'), true);
            $integer = static fn(?string $value): ?int => $value !== null && preg_match('/\A-?\d{1,6}\z/', $value) ? (int)$value : null;
            $count = 0;
            $parse = function (iterable $nodes, int $depth = 0, array $inherited = []) use (&$parse, &$count, $compact, $clean, $drop, $blockTags, $integer): array {
                if ($depth > 40) throw new LengthException('Description depth limit');
                $out = []; $buffer = [];
                $flush = static function () use (&$buffer, &$out, $compact): void {
                    $runs = $compact($buffer);
                    if ($runs !== []) $out[] = ['type' => 'paragraph', 'runs' => $runs];
                    $buffer = [];
                };
                $visit = function (DOMNode $node, array $marks, int $level) use (&$visit, &$parse, &$count, &$out, &$buffer, $flush, $compact, $clean, $drop, $blockTags, $integer): void {
                    if (++$count > 15000 || $level > 40) throw new LengthException('Description complexity limit');
                    if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) { $buffer[] = ['text' => (string)$node->nodeValue] + $marks; return; }
                    if (!$node instanceof DOMElement) return;
                    $tag = strtolower($node->tagName);
                    if (isset($drop[$tag])) return;
                    if ($tag === 'br' || $tag === 'hr') { $flush(); return; }
                    if ($tag === 'ul' || $tag === 'ol') {
                        $flush(); $items = [];
                        $start = $node->hasAttribute('start') ? $integer($node->getAttribute('start')) : null;
                        $reversed = $tag === 'ol' && $node->hasAttribute('reversed');
                        $push = static function () use (&$items, &$out, $tag, $start, $reversed): void {
                            if ($items === []) return;
                            $list = ['type' => 'list', 'ordered' => $tag === 'ol', 'start' => $start ?? ($reversed ? count($items) : 1), 'items' => $items];
                            if ($reversed) $list['reversed'] = true;
                            $out[] = $list; $items = [];
                        };
                        foreach ($node->childNodes as $child) {
                            if ($child instanceof DOMElement && strtolower($child->tagName) === 'li') {
                                if (++$count > 15000) throw new LengthException('Description size limit');
                                $item = ['blocks' => $parse($child->childNodes, $level + 1, $marks)];
                                $value = $integer($child->getAttribute('value'));
                                if ($value !== null) $item['value'] = $value;
                                $items[] = $item;
                            } elseif ($child->nodeType === XML_TEXT_NODE && trim($clean((string)$child->nodeValue)) === '') { /* list spacing */ }
                            else { $push(); array_push($out, ...$parse([$child], $level + 1, $marks)); }
                        }
                        $push(); return;
                    }
                    if (preg_match('/\Ah[1-6]\z/', $tag)) {
                        $flush();
                        foreach ($parse($node->childNodes, $level + 1, $marks) as $part) {
                            if ($part['type'] === 'paragraph') $part['type'] = 'heading';
                            $out[] = $part;
                        }
                        return;
                    }
                    if ($tag === 'pre') {
                        $flush();
                        foreach (explode("\n", $clean($node->textContent)) as $line) {
                            $runs = $compact([['text' => $line] + $marks]);
                            if ($runs !== []) $out[] = ['type' => 'paragraph', 'runs' => $runs];
                        }
                        return;
                    }
                    if (isset($blockTags[$tag])) { $flush(); array_push($out, ...$parse($node->childNodes, $level + 1, $marks)); return; }
                    if ($tag === 'strong' || $tag === 'b') $marks['strong'] = true;
                    if ($tag === 'em' || $tag === 'i') $marks['em'] = true;
                    foreach ($node->childNodes as $child) $visit($child, $marks, $level + 1);
                };
                foreach ($nodes as $node) $visit($node, $inherited, $depth);
                $flush(); return $out;
            };
            $blocks = $parse($root->childNodes);
        }
        // Plain-text heading/list heuristics are shared in the browser renderer.
        return ['schemaVersion' => 1, 'language' => 'pl', 'format' => $isHtml ? 'html' : 'text', 'blocks' => $blocks];
    } catch (Throwable $error) {
        error_log('MazurEstate description formatter: plain-text fallback.');
        return null;
    }
}
