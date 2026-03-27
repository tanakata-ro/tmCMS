<?php
/**
 * Parsedown — Markdown パーサー
 *
 * オリジナル: https://github.com/erusev/parsedown
 * License: MIT
 *
 * tmCMSにParsedownが同梱されていない環境向けの内蔵実装。
 * 本家Parsedownが vendor/ にあればそちらが優先されます（bootstrap.php参照）。
 */
class Parsedown
{
    const VERSION = '1.7.4-bundled';

    private array $BlockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'], '1' => ['List'], '2' => ['List'], '3' => ['List'],
        '4' => ['List'], '5' => ['List'], '6' => ['List'], '7' => ['List'],
        '8' => ['List'], '9' => ['List'],
        ':' => ['Table'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode'],
    ];

    private array $unmarkedBlockTypes = ['Code'];
    private array $InlineTypes = [
        '"' => ['SpecialCharacter'],
        '!' => ['Image'],
        '&' => ['SpecialCharacter'],
        '*' => ['Emphasis'],
        ':' => ['Url'],
        '<' => ['UrlTag', 'EmailTag', 'Markup', 'SpecialCharacter'],
        '>' => ['SpecialCharacter'],
        '[' => ['Link'],
        '_' => ['Emphasis'],
        '`' => ['Code'],
        '~' => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    private string $inlineMarkerList = '!"*_&[:<>`~\\';
    private bool $breaksEnabled = false;
    private bool $markupEscaped = false;
    private bool $urlsLinked = true;
    private bool $safeMode = false;
    private array $safeLinksWhitelist = ['http://', 'https://', 'ftp://', 'ftps://', 'mailto:', 'tel:'];
    private array $DefinitionData = [];

    public function text(string $text): string
    {
        $Elements = $this->textElements($text);
        $markup   = $this->elements($Elements);
        $markup   = trim($markup, "\n");
        return $markup;
    }

    private function textElements(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = $text . "\n";
        $this->DefinitionData = [];
        $blocks = $this->lines(explode("\n", $text));
        return $blocks;
    }

    private function lines(array $lines): array
    {
        return $this->linesElements($lines);
    }

    private function linesElements(array $lines): array
    {
        $Elements    = [];
        $CurrentBlock = null;

        foreach ($lines as $line) {
            if (chop($line) === '') {
                if (isset($CurrentBlock)) {
                    $CurrentBlock['interrupted'] = (isset($CurrentBlock['interrupted']) ? $CurrentBlock['interrupted'] + 1 : 1);
                }
                continue;
            }

            while (substr($line, 0, 4) === '    ') {
                $line = substr($line, 4);
                break; // only strip once for code blocks handled below
            }
            $line = ['body' => $line];

            $line['indent'] = strlen($line['body']) - strlen(ltrim($line['body']));
            $line['text']   = ltrim($line['body']);

            if (isset($CurrentBlock['continuable'])) {
                $methodName = 'block' . $CurrentBlock['type'] . 'Continue';
                if (method_exists($this, $methodName)) {
                    $Block = $this->$methodName($line, $CurrentBlock);
                    if (isset($Block)) {
                        $CurrentBlock = $Block;
                        continue;
                    }
                }

                if ($this->isBlockCompletable($CurrentBlock['type'])) {
                    $methodName   = 'block' . $CurrentBlock['type'] . 'Complete';
                    $CurrentBlock = $this->$methodName($CurrentBlock);
                }
            }

            $marker = $line['text'][0];
            $blockTypes = $this->unmarkedBlockTypes;
            if (isset($this->BlockTypes[$marker])) {
                foreach ($this->BlockTypes[$marker] as $blockType) {
                    $blockTypes[] = $blockType;
                }
            }

            foreach ($blockTypes as $blockType) {
                $Block = $this->{'block' . $blockType}($line, $CurrentBlock);
                if (isset($Block)) {
                    $Block['type'] = $blockType;
                    if (!isset($Block['identified'])) {
                        if (isset($CurrentBlock)) {
                            $Elements[] = $this->extractElement($CurrentBlock);
                        }
                        $Block['identified'] = true;
                    }
                    if ($this->isBlockContinuable($blockType)) {
                        $Block['continuable'] = true;
                    }
                    $CurrentBlock = $Block;
                    continue 2;
                }
            }

            if (isset($CurrentBlock) && $CurrentBlock['type'] === 'Paragraph') {
                $Block = $this->blockParagraphContinue($line, $CurrentBlock);
                if (isset($Block)) {
                    $CurrentBlock = $Block;
                    continue;
                }
            }

            if (isset($CurrentBlock)) {
                $Elements[] = $this->extractElement($CurrentBlock);
            }

            $CurrentBlock = $this->blockParagraph($line);
        }

        if (isset($CurrentBlock)) {
            if (isset($CurrentBlock['continuable']) && $this->isBlockCompletable($CurrentBlock['type'])) {
                $methodName   = 'block' . $CurrentBlock['type'] . 'Complete';
                $CurrentBlock = $this->$methodName($CurrentBlock);
            }
            $Elements[] = $this->extractElement($CurrentBlock);
        }

        return $Elements;
    }

    private function extractElement(array $Component): array
    {
        if (!isset($Component['element'])) {
            if (isset($Component['markup'])) {
                $Component['element'] = ['rawHtml' => $Component['markup']];
            } elseif (isset($Component['hidden'])) {
                $Component['element'] = [];
            }
        }
        return $Component['element'];
    }

    private function isBlockContinuable(string $Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Continue');
    }

    private function isBlockCompletable(string $Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Complete');
    }

    // ── Block parsers ─────────────────────────────────────────────

    private function blockCode(array $Line, ?array $Block = null): ?array
    {
        if (isset($Block) && $Block['type'] === 'Paragraph' && !isset($Block['interrupted'])) {
            return null;
        }
        if ($Line['indent'] >= 4) {
            $text = substr($Line['body'], 4);
            return ['element' => ['name' => 'pre', 'element' => ['name' => 'code', 'text' => $text]], 'type' => 'Code'];
        }
        return null;
    }

    private function blockCodeContinue(array $Line, array $Block): ?array
    {
        if ($Line['indent'] >= 4) {
            if (isset($Block['interrupted'])) {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);
                unset($Block['interrupted']);
            }
            $Block['element']['element']['text'] .= "\n" . substr($Line['body'], 4);
            return $Block;
        }
        return null;
    }

    private function blockCodeComplete(array $Block): array
    {
        return $Block;
    }

    private function blockComment(array $Line): ?array
    {
        if ($this->markupEscaped || $this->safeMode) return null;
        if (str_starts_with($Line['text'], '<!--')) {
            $Block = ['element' => ['rawHtml' => $Line['body'], 'autobreak' => true]];
            if (str_contains($Line['text'], '-->')) return $Block;
            $Block['closed'] = false;
            return $Block;
        }
        return null;
    }

    private function blockCommentContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['closed'])) return null;
        $Block['element']['rawHtml'] .= "\n" . $Line['body'];
        if (str_contains($Line['text'], '-->')) $Block['closed'] = true;
        return $Block;
    }

    private function blockFencedCode(array $Line): ?array
    {
        $marker = $Line['text'][0];
        $openerLength = strspn($Line['text'], $marker);
        if ($openerLength < 3) return null;
        $infostring = trim(substr($Line['text'], $openerLength), "\t ");
        if (str_contains($infostring, '`')) return null;
        $Element = ['name' => 'code', 'text' => ''];
        if ($infostring !== '') {
            // 言語:ファイル名 形式に対応
            $lang = explode(':', $infostring)[0];
            $Element['attributes'] = ['class' => 'language-' . $lang];
        }
        return [
            'char'        => $marker,
            'openerLength'=> $openerLength,
            'element'     => ['name' => 'pre', 'element' => $Element],
        ];
    }

    private function blockFencedCodeContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['complete'])) return null;
        if (isset($Block['interrupted'])) {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);
            unset($Block['interrupted']);
        }
        $marker       = $Block['char'];
        $closerLength = strspn($Line['text'], $marker);
        if ($closerLength >= $Block['openerLength'] && chop(substr($Line['text'], $closerLength)) === '') {
            $Block['element']['element']['text'] = substr($Block['element']['element']['text'], 1);
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['element']['text'] .= "\n" . $Line['body'];
        return $Block;
    }

    private function blockFencedCodeComplete(array $Block): array { return $Block; }

    private function blockHeader(array $Line): ?array
    {
        $level = strspn($Line['text'], '#');
        if ($level > 6) return null;
        $text = trim(substr($Line['text'], $level), ' ');
        return ['element' => ['name' => 'h' . $level, 'handler' => ['function' => 'lineElements', 'argument' => $text, 'destination' => 'elements']]];
    }

    private function blockList(array $Line): ?array
    {
        [$name, $pattern] = $Line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]{1,9}[.\)]'];
        if (!preg_match('/^(' . $pattern . '([ ]+|$))/', $Line['text'], $matches)) return null;
        $markerWithoutWhitespace = strstr($matches[0], ' ', true) ?: $matches[0];
        return [
            'indent'    => $Line['indent'],
            'pattern'   => $pattern,
            'data'      => ['type' => $name, 'marker' => $matches[0], 'markerType' => ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1)), 'items' => []],
            'element'   => ['name' => $name, 'elements' => []],
        ];
    }

    private function blockListContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted']) && $Line['indent'] === 0) return null;
        $pattern  = $Block['pattern'];
        if ($Line['indent'] < $Block['indent'] + 4 && preg_match('/^(' . $pattern . '([ ]+|$))/', $Line['text'], $matches)) {
            $text = substr($Line['text'], strlen($matches[0]));
            $Block['element']['elements'][] = ['name' => 'li', 'handler' => ['function' => 'li', 'argument' => [$text], 'destination' => 'elements']];
            $Block['data']['items'][] = $text;
            if (isset($Block['interrupted'])) unset($Block['interrupted']);
        } elseif (!isset($Block['interrupted'])) {
            $lastItem = &$Block['data']['items'][count($Block['data']['items']) - 1];
            $lastItem .= "\n" . $Line['body'];
            $lastEl   = &$Block['element']['elements'][count($Block['element']['elements']) - 1];
            $lastEl   = ['name' => 'li', 'handler' => ['function' => 'li', 'argument' => explode("\n", $lastItem), 'destination' => 'elements']];
        } else {
            return null;
        }
        return $Block;
    }

    private function blockListComplete(array $Block): array { return $Block; }

    private function blockQuote(array $Line): ?array
    {
        if (!preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches)) return null;
        return ['element' => ['name' => 'blockquote', 'handler' => ['function' => 'linesElements', 'argument' => [$matches[1]], 'destination' => 'elements']]];
    }

    private function blockQuoteContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) return null;
        if ($Line['text'][0] === '>' && preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches)) {
            $Block['element']['handler']['argument'][] = $matches[1];
            return $Block;
        }
        return null;
    }

    private function blockRule(array $Line): ?array
    {
        if (preg_match('/^(?:(?:\*[ ]*){3,}|(?:_[ ]*){3,}|(?:-[ ]*){3,})$/', chop($Line['text']))) {
            return ['element' => ['name' => 'hr']];
        }
        return null;
    }

    private function blockSetextHeader(array $Line, ?array $Block = null): ?array
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph' || isset($Block['interrupted'])) return null;
        if ($Line['indent'] < 4 && chop(chop($Line['text'], ' '), $Line['text'][0]) === '') {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';
            return $Block;
        }
        return null;
    }

    private function blockMarkup(array $Line): ?array
    {
        if ($this->markupEscaped || $this->safeMode) return null;
        if (!preg_match('/^<[\/]?+(\w*)(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+(\/)?>/', $Line['text'], $matches)) return null;
        $element = strtolower($matches[1]);
        if (!in_array($element, ['address','article','aside','base','basefont','blockquote','body','caption','center','col','colgroup','dd','details','dialog','dir','div','dl','dt','fieldset','figcaption','figure','footer','form','frame','frameset','h1','h2','h3','h4','h5','h6','head','header','hr','html','iframe','legend','li','link','main','menu','menuitem','meta','nav','noframes','ol','optgroup','option','p','param','section','source','summary','table','tbody','td','tfoot','th','thead','title','tr','track','ul'], true)) return null;
        return ['element' => ['rawHtml' => $Line['text'], 'autobreak' => true], 'closed' => str_contains($Line['text'], '>')];
    }

    private function blockMarkupContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['closed']) || isset($Block['interrupted'])) return null;
        $Block['element']['rawHtml'] .= "\n" . $Line['body'];
        return $Block;
    }

    private function blockReference(array $Line): ?array
    {
        if ($Line['indent'] > 3 || !preg_match('/^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$/', $Line['text'], $matches)) return null;
        $this->DefinitionData['Reference'][strtolower($matches[1])] = ['url' => $matches[2], 'title' => $matches[3] ?? null];
        return ['hidden' => true];
    }

    private function blockTable(array $Line, ?array $Block = null): ?array
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph' || isset($Block['interrupted'])) return null;
        if (!str_contains($Block['element']['handler']['argument'], '|') && !str_contains($Line['text'], '|') && !str_contains($Line['text'], ':')) return null;
        if (!preg_match('/^[ ]*+(\|[ ]*+)?+(?:[-:]+[ ]*+\|[ ]*+)+([-:]+[ ]*+)?+$/', $Line['text'])) return null;
        $HeaderElements = [];
        $header = $Block['element']['handler']['argument'];
        $header = trim($header);
        $header = trim($header, '|');
        $headerCells = explode('|', $header);
        $alignments = [];
        $divider = $Line['text'];
        $divider = trim($divider);
        $divider = trim($divider, '|');
        $dividerCells = explode('|', $divider);
        foreach ($dividerCells as $dividerCell) {
            $dividerCell = trim($dividerCell);
            if ($dividerCell === '') { $alignments[] = null; continue; }
            $alignment = null;
            if ($dividerCell[0] === ':') $alignment = 'left';
            if (substr($dividerCell, -1) === ':') $alignment = $alignment === 'left' ? 'center' : 'right';
            $alignments[] = $alignment;
        }
        foreach ($headerCells as $index => $headerCell) {
            $headerCell = trim($headerCell);
            $HeaderElement = ['name' => 'th', 'handler' => ['function' => 'lineElements', 'argument' => $headerCell, 'destination' => 'elements']];
            if (isset($alignments[$index])) $HeaderElement['attributes'] = ['style' => 'text-align: ' . $alignments[$index] . ';'];
            $HeaderElements[] = $HeaderElement;
        }
        return [
            'alignments' => $alignments,
            'identified' => true,
            'element'    => ['name' => 'table', 'elements' => [
                ['name' => 'thead', 'elements' => [['name' => 'tr', 'elements' => $HeaderElements]]],
                ['name' => 'tbody', 'elements' => []],
            ]],
        ];
    }

    private function blockTableContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) return null;
        if (count($Block['alignments']) === 1 || $Line['text'][0] === '|' || str_contains($Line['text'], '|')) {
            $Elements  = [];
            $row       = $Line['text'];
            $row       = trim($row);
            $row       = trim($row, '|');
            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);
            foreach ($matches[0] as $index => $cell) {
                $cell    = trim($cell);
                $Element = ['name' => 'td', 'handler' => ['function' => 'lineElements', 'argument' => $cell, 'destination' => 'elements']];
                if (isset($Block['alignments'][$index])) $Element['attributes'] = ['style' => 'text-align: ' . $Block['alignments'][$index] . ';'];
                $Elements[] = $Element;
            }
            $Block['element']['elements'][1]['elements'][] = ['name' => 'tr', 'elements' => $Elements];
            return $Block;
        }
        return null;
    }

    private function blockParagraph(array $Line): array
    {
        return ['element' => ['name' => 'p', 'handler' => ['function' => 'lineElements', 'argument' => $Line['text'], 'destination' => 'elements']]];
    }

    private function blockParagraphContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) return null;
        $Block['element']['handler']['argument'] .= "\n" . $Line['text'];
        return $Block;
    }

    // ── Inline parsers ─────────────────────────────────────────────

    private function lineElements(string $text): array
    {
        $Elements = [];
        $excerpt  = '';
        while ($remainder = $text) {
            $minimumPosition = null;
            $marker          = null;
            $excerpt         = null;
            foreach ($this->InlineTypes as $m => $inlineTypes) {
                $markerPosition = strpos($text, $m);
                if ($markerPosition === false) continue;
                if ($minimumPosition !== null && $markerPosition >= $minimumPosition) continue;
                $minimumPosition = $markerPosition;
                $marker          = $m;
            }
            if ($marker === null) { $Elements[] = ['text' => $remainder]; break; }
            $pos = $minimumPosition;
            if ($pos > 0) { $Elements[] = ['text' => substr($text, 0, $pos)]; }
            $text    = substr($text, $pos);
            $excerpt = ['text' => $text, 'context' => $text];
            foreach ($this->InlineTypes[$marker] as $inlineType) {
                $Inline = $this->{'inline' . $inlineType}($excerpt);
                if (!isset($Inline)) continue;
                if (isset($Inline['position']) && $Inline['position'] > 0) break;
                if (!isset($Inline['position'])) $Inline['position'] = 0;
                $Elements[] = $Inline['element'];
                $text = substr($text, $Inline['position'] + $Inline['extent']);
                continue 2;
            }
            $Elements[] = ['text' => $marker];
            $text = substr($text, strlen($marker));
        }
        return $Elements;
    }

    private function inlineCode(array $Excerpt): ?array
    {
        $marker = $Excerpt['text'][0];
        if (preg_match('/^(' . preg_quote($marker) . '+)[ ]*+(.+?)[ ]*+(?<!' . preg_quote($marker) . ')\1(?!' . preg_quote($marker) . ')/s', $Excerpt['text'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['name' => 'code', 'text' => preg_replace('/[ ]*+\n/', ' ', $matches[2])]];
        }
        return null;
    }

    private function inlineEmailTag(array $Excerpt): ?array
    {
        $text = $Excerpt['context'];
        if (!str_contains($text, '>') || !preg_match('/^<(\w++\+?+(?:[._\-]?\w++)*+@\w+(?:[.-]\w+)*+\.\w{2,}+)>/i', $text, $matches)) return null;
        return ['extent' => strlen($matches[0]), 'element' => ['name' => 'a', 'text' => $matches[1], 'attributes' => ['href' => 'mailto:' . $matches[1]]]];
    }

    private function inlineEmphasis(array $Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1])) return null;
        $marker = $Excerpt['text'][0];
        if ($Excerpt['text'][1] === $marker && preg_match('/^' . preg_quote($marker) . '{2}(?!\s)(.+?)(?<!\s)' . preg_quote($marker) . '{2}/s', $Excerpt['text'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['name' => 'strong', 'handler' => ['function' => 'lineElements', 'argument' => $matches[1], 'destination' => 'elements']]];
        }
        if (preg_match('/^' . preg_quote($marker) . '(?!\s)(.+?)(?<!\s)' . preg_quote($marker) . '/s', $Excerpt['text'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['name' => 'em', 'handler' => ['function' => 'lineElements', 'argument' => $matches[1], 'destination' => 'elements']]];
        }
        return null;
    }

    private function inlineEscapeSequence(array $Excerpt): ?array
    {
        if (isset($Excerpt['text'][1]) && in_array($Excerpt['text'][1], ['\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '#', '+', '-', '.', '!', '|'])) {
            return ['extent' => 2, 'element' => ['text' => $Excerpt['text'][1]]];
        }
        return null;
    }

    private function inlineImage(array $Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '[') return null;
        $Excerpt['text'] = substr($Excerpt['text'], 1);
        $Link = $this->inlineLink($Excerpt);
        if ($Link === null) return null;
        $imageElement = ['name' => 'img', 'attributes' => ['src' => $Link['element']['attributes']['href'], 'alt' => $Link['element']['handler']['argument'] ?? '']];
        if (isset($Link['element']['attributes']['title'])) $imageElement['attributes']['title'] = $Link['element']['attributes']['title'];
        return ['extent' => $Link['extent'] + 1, 'element' => $imageElement];
    }

    private function inlineLink(array $Excerpt): ?array
    {
        $Element = ['name' => 'a', 'handler' => ['function' => 'lineElements', 'argument' => null, 'destination' => 'elements'], 'attributes' => ['href' => null, 'title' => null]];
        $extent  = 0;
        $remainder = $Excerpt['text'];
        if (!preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches)) return null;
        $Element['handler']['argument'] = $matches[1];
        $extent += strlen($matches[0]);
        $remainder = substr($remainder, $extent);
        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]*+[)])*+)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/', $remainder, $matches)) {
            $Element['attributes']['href'] = $matches[1];
            if (isset($matches[2])) $Element['attributes']['title'] = substr($matches[2], 1, -1);
            $extent += strlen($matches[0]);
        } else {
            $definition = strtolower($Element['handler']['argument']);
            if (!isset($this->DefinitionData['Reference'][$definition])) return null;
            $Definition = $this->DefinitionData['Reference'][$definition];
            $Element['attributes']['href']  = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
            $extent += 0;
        }
        $Element['attributes']['href'] = str_replace(['&', '"'], ['&amp;', '&quot;'], $Element['attributes']['href']);
        return ['extent' => $extent, 'element' => $Element];
    }

    private function inlineMarkup(array $Excerpt): ?array
    {
        if ($this->markupEscaped || $this->safeMode || !str_contains($Excerpt['context'], '>')) return null;
        if ($Excerpt['text'][1] === '/' && preg_match('/^<\/\w[\w-]*+[ ]*+>/s', $Excerpt['context'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['rawHtml' => $matches[0]]];
        }
        if ($Excerpt['text'][1] === '!' && preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $Excerpt['context'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['rawHtml' => $matches[0]]];
        }
        if ($Excerpt['text'][1] !== ' ' && preg_match('/^<\w+(?:[ ]*+' . $this->regexHtmlAttribute . ')*+[ ]*+\/?>/s', $Excerpt['context'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['rawHtml' => $matches[0]]];
        }
        return null;
    }

    private function inlineSpecialCharacter(array $Excerpt): ?array
    {
        if ($Excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', $Excerpt['text'])) {
            return ['extent' => 1, 'element' => ['rawHtml' => '&amp;']];
        }
        $map = ['>' => '&gt;', '<' => '&lt;', '"' => '&quot;'];
        if (isset($map[$Excerpt['text'][0]])) {
            return ['extent' => 1, 'element' => ['rawHtml' => $map[$Excerpt['text'][0]]]];
        }
        return null;
    }

    private function inlineStrikethrough(array $Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1])) return null;
        if ($Excerpt['text'][1] === '~' && preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches)) {
            return ['extent' => strlen($matches[0]), 'element' => ['name' => 'del', 'handler' => ['function' => 'lineElements', 'argument' => $matches[1], 'destination' => 'elements']]];
        }
        return null;
    }

    private function inlineUrl(array $Excerpt): ?array
    {
        if (!$this->urlsLinked || !isset($Excerpt['text'][2]) || $Excerpt['text'][2] !== '/') return null;
        if (!preg_match('/\bhttps?+:[\/]{2}[^\s<]*+\b\/?+/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)) return null;
        $url = $matches[0][0];
        return ['extent' => strlen($matches[0][0]), 'position' => $matches[0][1], 'element' => ['name' => 'a', 'text' => $url, 'attributes' => ['href' => $url]]];
    }

    private function inlineUrlTag(array $Excerpt): ?array
    {
        if (!str_contains($Excerpt['context'], '>') || !preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $Excerpt['context'], $matches)) return null;
        $url = $matches[1];
        return ['extent' => strlen($matches[0]), 'element' => ['name' => 'a', 'text' => $url, 'attributes' => ['href' => $url]]];
    }

    // ── Element rendering ──────────────────────────────────────────

    private function elements(array $Elements): string
    {
        $markup = '';
        foreach ($Elements as $Element) {
            $markup .= "\n" . $this->element($Element);
        }
        return $markup;
    }

    private function element(array $Element): string
    {
        if ($this->safeMode) {
            $Element = $this->sanitiseElement($Element);
        }
        $markup = '';
        if (isset($Element['rawHtml'])) {
            $markup = $Element['rawHtml'];
            $autobreak = isset($Element['autobreak']) ? $Element['autobreak'] : isset($Element['name']);
            if ($autobreak) $markup = "\n" . $markup . "\n";
            return $markup;
        }
        if (!isset($Element['name'])) {
            if (isset($Element['handler'])) return $this->handle($Element);
            if (isset($Element['text'])) return htmlspecialchars($Element['text'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '';
        }
        $markup = '<' . $Element['name'];
        if (isset($Element['attributes'])) {
            foreach ($Element['attributes'] as $name => $value) {
                if ($value === null) continue;
                $markup .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }
        $permitRawHtml = false;
        if (isset($Element['text'])) {
            $Element['handler']['function']    = 'lines';
            $Element['handler']['argument']    = [$Element['text']];
            $Element['handler']['destination'] = 'elements';
        }
        if (isset($Element['handler'])) {
            $handlerFunction = $Element['handler']['function'];
            $handlerArgument = $Element['handler']['argument'];
            if (isset($Element['handler']['destination'])) {
                $handlerDestination = $Element['handler']['destination'];
                if ($handlerDestination === 'elements') {
                    $Element['elements'] = $this->$handlerFunction($handlerArgument);
                    unset($Element['handler']);
                } elseif ($handlerDestination === 'element') {
                    $Element['element'] = $this->$handlerFunction($handlerArgument);
                    unset($Element['handler']);
                }
            }
        }
        $isVoid = in_array($Element['name'], ['area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source']);
        if ($isVoid) {
            $markup .= ' />';
        } else {
            $markup .= '>';
            if (isset($Element['text'])) {
                $markup .= htmlspecialchars($Element['text'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif (isset($Element['elements'])) {
                $markup .= $this->elements($Element['elements']);
            } elseif (isset($Element['element'])) {
                $markup .= $this->element($Element['element']);
            }
            $markup .= '</' . $Element['name'] . '>';
        }
        return $markup;
    }

    private function handle(array $Element): string
    {
        if (isset($Element['function'])) {
            return $this->{$Element['function']}($Element['argument'] ?? '');
        }
        return '';
    }

    private function li(array $lines): array
    {
        $Elements = $this->linesElements($lines);
        if (!in_array('', $lines) && isset($Elements[0]) && isset($Elements[0]['name']) && $Elements[0]['name'] === 'p') {
            $Elements[0] = $Elements[0]['handler']['argument'] ?? $Elements[0];
        }
        return $Elements;
    }

    private function sanitiseElement(array $Element): array
    {
        static $goodAttribute  = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt = ['a' => 'href', 'img' => 'src'];
        if (isset($safeUrlNameToAtt[$Element['name'] ?? ''])) {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }
        if (!empty($Element['attributes'])) {
            foreach ($Element['attributes'] as $att => $val) {
                if (!preg_match($goodAttribute, $att)) unset($Element['attributes'][$att]);
                elseif ($val !== null) $Element['attributes'][$att] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
        }
        return $Element;
    }

    private function filterUnsafeUrlInAttribute(array $Element, string $attribute): array
    {
        foreach ($this->safeLinksWhitelist as $scheme) {
            if (str_starts_with($Element['attributes'][$attribute] ?? '', $scheme)) return $Element;
        }
        $Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute] ?? '');
        return $Element;
    }

    private string $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:[ ]*+=[ ]*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';
}
