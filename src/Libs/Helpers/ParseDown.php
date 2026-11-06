<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use LogicException;

/**
 * # Parsedown
 * # http://parsedown.org
 * #
 * # (c) Emanuil Rusev
 * # http://erusev.com
 * #
 * # For the full license information, view the LICENSE file that was distributed
 * # with this source code.
 *
 * Markdown parser, modernized for PHP 8.4 (typed constants, typed properties,
 * strict types, `#[\Deprecated]`, `array_any()`, `match`, `str_*` helpers).
 *
 * Shapes used throughout the documentation:
 *
 * @phpstan-type Line array{body: string, indent: int, text: string}
 * @phpstan-type Excerpt array{text: string, context: string}
 * @phpstan-type Element array<string, mixed>
 * @phpstan-type Block array<string, mixed>
 * @phpstan-type Inline array<string, mixed>
 */
final class ParseDown
{
    /**
     * Version of the upstream Parsedown this implementation is based on.
     */
    public const string VERSION = '1.8.0';

    /**
     * Block types that may start with a given first character of a line.
     *
     * @var array<array-key, list<string>>
     */
    private const array BLOCK_TYPES = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
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

    /**
     * Block types tried for every line, regardless of its first character.
     *
     * @var list<string>
     */
    private const array UNMARKED_BLOCK_TYPES = ['Code'];

    /**
     * Inline types that may start with a given marker character.
     *
     * @var array<string, list<string>>
     */
    private const array INLINE_TYPES = [
        '!'  => ['Image'],
        '&'  => ['SpecialCharacter'],
        '*'  => ['Emphasis'],
        ':'  => ['Url'],
        '<'  => ['UrlTag', 'EmailTag', 'Markup'],
        '['  => ['Link'],
        '_'  => ['Emphasis'],
        '`'  => ['Code'],
        '~'  => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    /**
     * All characters that can start an inline element (used with strpbrk()).
     */
    private const string INLINE_MARKERS = '!*_&[:<`~\\';

    /**
     * Link schemes that are allowed when safe mode is enabled.
     *
     * @var list<string>
     */
    private const array SAFE_LINKS_WHITELIST = [
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'tel:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    ];

    /**
     * Element name => attribute that carries a URL (checked in safe mode).
     *
     * @var array<string, string>
     */
    private const array SAFE_URL_ATTRIBUTES = [
        'a'   => 'href',
        'img' => 'src',
    ];

    /**
     * Characters that can be backslash-escaped.
     *
     * @var list<string>
     */
    private const array SPECIAL_CHARACTERS = [
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')',
        '>', '#', '+', '-', '.', '!', '|', '~',
    ];

    /**
     * Regex for `**strong**` / `__strong__`, keyed by marker.
     *
     * @var array<string, string>
     */
    private const array STRONG_REGEX = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
    ];

    /**
     * Regex for `*em*` / `_em_`, keyed by marker.
     *
     * @var array<string, string>
     */
    private const array EM_REGEX = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    /**
     * Regex fragment matching a single HTML attribute.
     */
    private const string REGEX_HTML_ATTRIBUTE
        = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

    /**
     * HTML elements that are treated as inline (not as a raw HTML block).
     *
     * @var list<string>
     */
    private const array TEXT_LEVEL_ELEMENTS = [
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing', 'i', 'rp',
        'del', 'code', 'strike', 'marquee', 'q', 'rt', 'ins', 'font',
        'strong', 's', 'tt', 'kbd', 'mark', 'u', 'xm', 'sub', 'nobr', 'sup',
        'ruby', 'var', 'span', 'wbr', 'time',
    ];

    /**
     * Whether single line breaks are converted to `<br />`.
     */
    private bool $breaksEnabled = false;

    /**
     * Whether raw HTML in the markdown is escaped instead of passed through.
     */
    private bool $markupEscaped = false;

    /**
     * Whether bare URLs are turned into links automatically.
     */
    private bool $urlsLinked = true;

    /**
     * Whether safe mode (XSS filtering, no raw HTML) is enabled.
     */
    private bool $safeMode = false;

    /**
     * Whether strict mode is enabled (e.g. `#Header` without space is not a header).
     */
    private bool $strictMode = false;

    /**
     * Reference-style link definitions collected while parsing: `[id]: url "title"`.
     *
     * @var array<string, array{url: string, title: string|null}>
     */
    private array $referenceDefinitions = [];

    /**
     * Named singleton instances, see {@see self::instance()}.
     *
     * @var array<string, self>
     */
    private static array $instances = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Convert Markdown text to HTML.
     *
     * @param string $text The Markdown text to convert.
     * @return string The converted HTML markup.
     */
    public function text(string $text): string
    {
        $elements = $this->textElements($text);

        return trim($this->elements($elements), "\n");
    }

    /**
     * Convert a single line of Markdown to HTML (inline elements only).
     *
     * @param string $text The inline Markdown text.
     * @param list<string> $nonNestables Inline types that must not be parsed in this context.
     * @return string The converted HTML markup.
     */
    public function line(string $text, array $nonNestables = []): string
    {
        return $this->elements($this->lineElements($text, $nonNestables));
    }

    /**
     * @deprecated Use {@see self::text()} instead.
     *
     * @param string $text The Markdown text to convert.
     * @return string The converted HTML markup.
     */
    #[\Deprecated(message: 'Use text() instead', since: '1.8.0')]
    public function parse(string $text): string
    {
        return $this->text($text);
    }

    /**
     * Set whether line breaks should be converted to `<br />` tags.
     *
     * @param bool $breaksEnabled True to convert line breaks.
     * @return $this
     */
    public function setBreaksEnabled(bool $breaksEnabled): static
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    /**
     * Set whether raw HTML should be escaped instead of rendered.
     *
     * @param bool $markupEscaped True to escape raw HTML.
     * @return $this
     */
    public function setMarkupEscaped(bool $markupEscaped): static
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    /**
     * Set whether bare URLs should be linked automatically.
     *
     * @param bool $urlsLinked True to auto-link URLs.
     * @return $this
     */
    public function setUrlsLinked(bool $urlsLinked): static
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    /**
     * Enable or disable safe mode. Use it for untrusted input.
     *
     * @param bool $safeMode True to enable safe mode.
     * @return $this
     */
    public function setSafeMode(bool $safeMode): static
    {
        $this->safeMode = $safeMode;

        return $this;
    }

    /**
     * Enable or disable strict mode.
     *
     * @param bool $strictMode True to enable strict mode.
     * @return $this
     */
    public function setStrictMode(bool $strictMode): static
    {
        $this->strictMode = $strictMode;

        return $this;
    }

    /**
     * Get (or lazily create) a named shared instance.
     *
     * @param string $name Instance name.
     * @return self The shared instance for that name.
     */
    public static function instance(string $name = 'default'): self
    {
        return self::$instances[$name] ??= new self();
    }

    // -------------------------------------------------------------------------
    // Block parsing
    // -------------------------------------------------------------------------

    /**
     * Convert Markdown text to an array of elements.
     *
     * @param string $text The Markdown text to convert.
     * @return list<Element> Elements representing the parsed Markdown.
     */
    private function textElements(string $text): array
    {
        // make sure no definitions are set
        $this->referenceDefinitions = [];

        // standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // remove surrounding line breaks
        $text = trim($text, "\n");

        return $this->linesElements(explode("\n", $text));
    }

    /**
     * Iterate through lines and identify blocks.
     *
     * @param list<string> $lines Raw lines (without line terminators).
     * @return list<Element> Elements representing the identified blocks.
     */
    private function linesElements(array $lines): array
    {
        $elements = [];
        /** @var Block|null $currentBlock */
        $currentBlock = null;

        foreach ($lines as $rawLine) {
            if (rtrim($rawLine) === '') {
                if ($currentBlock !== null) {
                    $currentBlock['interrupted'] = ($currentBlock['interrupted'] ?? 0) + 1;
                }

                continue;
            }

            // expand tabs to spaces (tab stops of 4)
            while (($beforeTab = strstr($rawLine, "\t", true)) !== false) {
                $shortage = 4 - mb_strlen($beforeTab, 'utf-8') % 4;

                $rawLine = $beforeTab
                    . str_repeat(' ', $shortage)
                    . substr($rawLine, strlen($beforeTab) + 1);
            }

            $indent = strspn($rawLine, ' ');
            $text   = $indent > 0 ? substr($rawLine, $indent) : $rawLine;

            /** @var Line $line */
            $line = ['body' => $rawLine, 'indent' => $indent, 'text' => $text];

            // let the current block try to absorb this line
            if (isset($currentBlock['continuable'])) {
                $method = 'block' . $currentBlock['type'] . 'Continue';
                $block  = $this->$method($line, $currentBlock);

                if ($block !== null) {
                    $currentBlock = $block;

                    continue;
                }

                if ($this->isBlockCompletable($currentBlock['type'])) {
                    $method       = 'block' . $currentBlock['type'] . 'Complete';
                    $currentBlock = $this->$method($currentBlock);
                }
            }

            // look for a new block
            $marker     = $text[0];
            $blockTypes = self::UNMARKED_BLOCK_TYPES;

            if (isset(self::BLOCK_TYPES[$marker])) {
                foreach (self::BLOCK_TYPES[$marker] as $blockType) {
                    $blockTypes[] = $blockType;
                }
            }

            foreach ($blockTypes as $blockType) {
                $block = $this->{"block$blockType"}($line, $currentBlock);

                if ($block === null) {
                    continue;
                }

                $block['type'] = $blockType;

                if (!isset($block['identified'])) {
                    if ($currentBlock !== null) {
                        $elements[] = $this->extractElement($currentBlock);
                    }

                    $block['identified'] = true;
                }

                if ($this->isBlockContinuable($blockType)) {
                    $block['continuable'] = true;
                }

                $currentBlock = $block;

                continue 2;
            }

            // nothing matched: it is a paragraph
            $block = null;

            if ($currentBlock !== null && $currentBlock['type'] === 'Paragraph') {
                $block = $this->paragraphContinue($line, $currentBlock);
            }

            if ($block !== null) {
                $currentBlock = $block;
            } else {
                if ($currentBlock !== null) {
                    $elements[] = $this->extractElement($currentBlock);
                }

                $currentBlock               = $this->paragraph($line);
                $currentBlock['identified'] = true;
            }
        }

        if (isset($currentBlock['continuable']) && $this->isBlockCompletable($currentBlock['type'])) {
            $method       = 'block' . $currentBlock['type'] . 'Complete';
            $currentBlock = $this->$method($currentBlock);
        }

        if ($currentBlock !== null) {
            $elements[] = $this->extractElement($currentBlock);
        }

        return $elements;
    }

    /**
     * Get the element that represents a finished block.
     *
     * @param Block $component The block.
     * @return Element The block's element (empty when the block renders nothing).
     */
    private function extractElement(array $component): array
    {
        return $component['element'] ?? [];
    }

    /**
     * Whether blocks of the given type can span multiple lines.
     *
     * @param string $type Block type name, e.g. `List`.
     */
    private function isBlockContinuable(string $type): bool
    {
        return method_exists($this, 'block' . $type . 'Continue');
    }

    /**
     * Whether blocks of the given type need a final "complete" pass.
     *
     * @param string $type Block type name, e.g. `List`.
     */
    private function isBlockCompletable(string $type): bool
    {
        return method_exists($this, 'block' . $type . 'Complete');
    }

    /**
     * Indented code block.
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not indented code.
     */
    private function blockCode(array $line, ?array $currentBlock = null): ?array
    {
        if ($currentBlock !== null
            && ($currentBlock['type'] ?? null) === 'Paragraph'
            && !isset($currentBlock['interrupted'])
        ) {
            return null;
        }

        if ($line['indent'] < 4) {
            return null;
        }

        return [
            'element' => [
                'name'    => 'pre',
                'element' => [
                    'name' => 'code',
                    'text' => substr($line['body'], 4),
                ],
            ],
        ];
    }

    /**
     * Continue an indented code block.
     *
     * @param Line $line The current line.
     * @param Block $block The code block.
     * @return Block|null The updated block, or null when the code block ended.
     */
    private function blockCodeContinue(array $line, array $block): ?array
    {
        if ($line['indent'] < 4) {
            return null;
        }

        if (isset($block['interrupted'])) {
            $block['element']['element']['text'] .= str_repeat("\n", $block['interrupted']);
            unset($block['interrupted']);
        }

        $block['element']['element']['text'] .= "\n" . substr($line['body'], 4);

        return $block;
    }

    /**
     * HTML comment block (`<!-- ... -->`).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line does not start a comment.
     */
    private function blockComment(array $line, ?array $currentBlock = null): ?array
    {
        if ($this->markupEscaped || $this->safeMode) {
            return null;
        }

        if (!str_starts_with($line['text'], '<!--')) {
            return null;
        }

        $block = [
            'element' => [
                'rawHtml'   => $line['body'],
                'autobreak' => true,
            ],
        ];

        if (str_contains($line['text'], '-->')) {
            $block['closed'] = true;
        }

        return $block;
    }

    /**
     * Continue an HTML comment block.
     *
     * @param Line $line The current line.
     * @param Block $block The comment block.
     * @return Block|null The updated block, or null when the comment is closed.
     */
    private function blockCommentContinue(array $line, array $block): ?array
    {
        if (isset($block['closed'])) {
            return null;
        }

        $block['element']['rawHtml'] .= "\n" . $line['body'];

        if (str_contains($line['text'], '-->')) {
            $block['closed'] = true;
        }

        return $block;
    }

    /**
     * Fenced code block (``` or ~~~).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not a fence opener.
     */
    private function blockFencedCode(array $line, ?array $currentBlock = null): ?array
    {
        $marker       = $line['text'][0];
        $openerLength = strspn($line['text'], $marker);

        if ($openerLength < 3) {
            return null;
        }

        $infoString = trim(substr($line['text'], $openerLength), "\t ");

        if (str_contains($infoString, '`')) {
            return null;
        }

        $code = [
            'name' => 'code',
            'text' => '',
        ];

        if ($infoString !== '') {
            // The language is the first space-separated token of the info string.
            $language           = substr($infoString, 0, strcspn($infoString, " \t\n\f\r"));
            $code['attributes'] = ['class' => "language-$language"];
        }

        return [
            'char'         => $marker,
            'openerLength' => $openerLength,
            'element'      => [
                'name'    => 'pre',
                'element' => $code,
            ],
        ];
    }

    /**
     * Continue a fenced code block.
     *
     * @param Line $line The current line.
     * @param Block $block The fenced code block.
     * @return Block|null The updated block, or null after the closing fence.
     */
    private function blockFencedCodeContinue(array $line, array $block): ?array
    {
        if (isset($block['complete'])) {
            return null;
        }

        if (isset($block['interrupted'])) {
            $block['element']['element']['text'] .= str_repeat("\n", $block['interrupted']);
            unset($block['interrupted']);
        }

        $length = strspn($line['text'], $block['char']);

        if ($length >= $block['openerLength'] && rtrim(substr($line['text'], $length), ' ') === '') {
            // drop the leading "\n" added by the first appended line
            $block['element']['element']['text'] = substr($block['element']['element']['text'], 1);
            $block['complete']                   = true;

            return $block;
        }

        $block['element']['element']['text'] .= "\n" . $line['body'];

        return $block;
    }

    /**
     * ATX header (`# Title`).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not a header.
     */
    private function blockHeader(array $line, ?array $currentBlock = null): ?array
    {
        $level = strspn($line['text'], '#');

        if ($level > 6) {
            return null;
        }

        $text = trim($line['text'], '#');

        if ($this->strictMode && isset($text[0]) && $text[0] !== ' ') {
            return null;
        }

        return [
            'element' => [
                'name'    => 'h' . $level,
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => trim($text, ' '),
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    /**
     * Ordered / unordered list item.
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not a list item.
     */
    private function blockList(array $line, ?array $currentBlock = null): ?array
    {
        $isUnordered = in_array($line['text'][0], ['*', '+', '-'], true);

        [$name, $pattern] = $isUnordered
            ? ['ul', '[*+-]']
            : ['ol', '[0-9]{1,9}+[.\)]'];

        if (preg_match('/^(' . $pattern . '([ ]++|$))(.*+)/', $line['text'], $matches) !== 1) {
            return null;
        }

        $contentIndent = strlen($matches[2]);

        if ($contentIndent >= 5) {
            $contentIndent -= 1;
            $matches[1]     = substr($matches[1], 0, -$contentIndent);
            $matches[3]     = str_repeat(' ', $contentIndent) . $matches[3];
        } elseif ($contentIndent === 0) {
            $matches[1] .= ' ';
        }

        $markerWithoutWhitespace = (string) strstr($matches[1], ' ', true);

        $block = [
            'indent'  => $line['indent'],
            'pattern' => $pattern,
            'data'    => [
                'type'       => $name,
                'marker'     => $matches[1],
                'markerType' => $name === 'ul'
                    ? $markerWithoutWhitespace
                    : substr($markerWithoutWhitespace, -1),
            ],
            'element' => [
                'name'     => $name,
                'elements' => [],
            ],
        ];

        $block['data']['markerTypeRegex'] = preg_quote($block['data']['markerType'], '/');

        if ($name === 'ol') {
            $listStart = ltrim((string) strstr($matches[1], $block['data']['markerType'], true), '0') ?: '0';

            if ($listStart !== '1') {
                // an ordered list not starting at 1 cannot interrupt a paragraph
                if ($currentBlock !== null
                    && $currentBlock['type'] === 'Paragraph'
                    && !isset($currentBlock['interrupted'])
                ) {
                    return null;
                }

                $block['element']['attributes'] = ['start' => $listStart];
            }
        }

        $block['li'] = [
            'name'    => 'li',
            'handler' => [
                'function'    => 'li',
                'argument'    => !empty($matches[3]) ? [$matches[3]] : [],
                'destination' => 'elements',
            ],
        ];

        // Reference on purpose: later lines append to $block['li'] and must
        // show up inside the element tree as well.
        $block['element']['elements'][] = &$block['li'];

        return $block;
    }

    /**
     * Continue a list.
     *
     * @param Line $line The current line.
     * @param Block $block The list block.
     * @return Block|null The updated block, or null when the list ended.
     */
    private function blockListContinue(array $line, array $block): ?array
    {
        if (isset($block['interrupted']) && empty($block['li']['handler']['argument'])) {
            return null;
        }

        $requiredIndent = $block['indent'] + strlen($block['data']['marker']);
        $isSibling      = false;
        $matches        = [];

        if ($line['indent'] < $requiredIndent) {
            $siblingPattern = $block['data']['type'] === 'ol'
                ? '/^[0-9]++' . $block['data']['markerTypeRegex'] . '(?:[ ]++(.*)|$)/'
                : '/^' . $block['data']['markerTypeRegex'] . '(?:[ ]++(.*)|$)/';

            $isSibling = preg_match($siblingPattern, $line['text'], $matches) === 1;
        }

        if ($isSibling) {
            // a new item of the same list
            if (isset($block['interrupted'])) {
                $block['li']['handler']['argument'][] = '';
                $block['loose']                       = true;
                unset($block['interrupted']);
            }

            unset($block['li']);

            $block['indent'] = $line['indent'];
            $block['li']     = [
                'name'    => 'li',
                'handler' => [
                    'function'    => 'li',
                    'argument'    => [$matches[1] ?? ''],
                    'destination' => 'elements',
                ],
            ];

            $block['element']['elements'][] = &$block['li'];

            return $block;
        }

        if ($line['indent'] < $requiredIndent && $this->blockList($line) !== null) {
            return null;
        }

        if ($line['text'][0] === '[' && $this->blockReference($line) !== null) {
            return $block;
        }

        if ($line['indent'] >= $requiredIndent) {
            if (isset($block['interrupted'])) {
                $block['li']['handler']['argument'][] = '';
                $block['loose']                       = true;
                unset($block['interrupted']);
            }

            $block['li']['handler']['argument'][] = substr($line['body'], $requiredIndent);

            return $block;
        }

        if (!isset($block['interrupted'])) {
            $block['li']['handler']['argument'][]
                = preg_replace('/^[ ]{0,' . $requiredIndent . '}+/', '', $line['body']);

            return $block;
        }

        return null;
    }

    /**
     * Finish a list: make every item "loose" (wrapped in paragraphs) if needed.
     *
     * @param Block $block The list block.
     * @return Block The completed block.
     */
    private function blockListComplete(array $block): array
    {
        if (isset($block['loose'])) {
            foreach ($block['element']['elements'] as &$li) {
                if (end($li['handler']['argument']) !== '') {
                    $li['handler']['argument'][] = '';
                }
            }

            unset($li);
        }

        return $block;
    }

    /**
     * Block quote (`> text`).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not a quote.
     */
    private function blockQuote(array $line, ?array $currentBlock = null): ?array
    {
        if (preg_match('/^>[ ]?+(.*+)/', $line['text'], $matches) !== 1) {
            return null;
        }

        return [
            'element' => [
                'name'    => 'blockquote',
                'handler' => [
                    'function'    => 'linesElements',
                    'argument'    => [$matches[1]],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    /**
     * Continue a block quote.
     *
     * @param Line $line The current line.
     * @param Block $block The quote block.
     * @return Block|null The updated block, or null when the quote ended.
     */
    private function blockQuoteContinue(array $line, array $block): ?array
    {
        if (isset($block['interrupted'])) {
            return null;
        }

        if ($line['text'][0] === '>' && preg_match('/^>[ ]?+(.*+)/', $line['text'], $matches) === 1) {
            $block['element']['handler']['argument'][] = $matches[1];

            return $block;
        }

        // lazy continuation
        $block['element']['handler']['argument'][] = $line['text'];

        return $block;
    }

    /**
     * Horizontal rule (`---`, `***`, `___`).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not a rule.
     */
    private function blockRule(array $line, ?array $currentBlock = null): ?array
    {
        $marker = $line['text'][0];

        if (substr_count($line['text'], $marker) >= 3 && rtrim($line['text'], " $marker") === '') {
            return ['element' => ['name' => 'hr']];
        }

        return null;
    }

    /**
     * Setext header (`Title` followed by `===` or `---`).
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The paragraph that becomes the header.
     * @return Block|null The converted paragraph block, or null when it does not apply.
     */
    private function blockSetextHeader(array $line, ?array $currentBlock = null): ?array
    {
        if ($currentBlock === null || $currentBlock['type'] !== 'Paragraph' || isset($currentBlock['interrupted'])) {
            return null;
        }

        if ($line['indent'] < 4 && rtrim(rtrim($line['text'], ' '), $line['text'][0]) === '') {
            $currentBlock['element']['name'] = $line['text'][0] === '=' ? 'h1' : 'h2';

            return $currentBlock;
        }

        return null;
    }

    /**
     * Raw HTML block.
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null A new block, or null when the line is not block-level HTML.
     */
    private function blockMarkup(array $line, ?array $currentBlock = null): ?array
    {
        if ($this->markupEscaped || $this->safeMode) {
            return null;
        }

        $pattern = '/^<[\/]?+(\w*)(?:[ ]*+' . self::REGEX_HTML_ATTRIBUTE . ')*+[ ]*+(\/)?>/';

        if (preg_match($pattern, $line['text'], $matches) !== 1) {
            return null;
        }

        if (in_array(strtolower($matches[1]), self::TEXT_LEVEL_ELEMENTS, true)) {
            return null;
        }

        return [
            'name'    => $matches[1],
            'element' => [
                'rawHtml'   => $line['text'],
                'autobreak' => true,
            ],
        ];
    }

    /**
     * Continue a raw HTML block.
     *
     * @param Line $line The current line.
     * @param Block $block The markup block.
     * @return Block|null The updated block, or null when it ended.
     */
    private function blockMarkupContinue(array $line, array $block): ?array
    {
        if (isset($block['closed']) || isset($block['interrupted'])) {
            return null;
        }

        $block['element']['rawHtml'] .= "\n" . $line['body'];

        return $block;
    }

    /**
     * Reference-style link definition (`[id]: https://example.com "Title"`).
     *
     * The definition is stored and the block renders nothing.
     *
     * @param Line $line The current line.
     * @param Block|null $currentBlock The block currently being built.
     * @return Block|null An empty block, or null when the line is not a definition.
     */
    private function blockReference(array $line, ?array $currentBlock = null): ?array
    {
        if (str_contains($line['text'], ']')
            && preg_match(
                '/^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$/',
                $line['text'],
                $matches
            ) === 1
        ) {
            $this->referenceDefinitions[strtolower($matches[1])] = [
                'url'   => $matches[2],
                'title' => $matches[3] ?? null,
            ];

            return ['element' => []];
        }

        return null;
    }

    /**
     * Table (header row followed by an alignment row).
     *
     * @param Line $line The current line (candidate divider row).
     * @param Block|null $currentBlock The paragraph that may become the table header.
     * @return Block|null A table block, or null when the lines are not a table.
     */
    private function blockTable(array $line, ?array $currentBlock = null): ?array
    {
        if ($currentBlock === null || $currentBlock['type'] !== 'Paragraph' || isset($currentBlock['interrupted'])) {
            return null;
        }

        $header = $currentBlock['element']['handler']['argument'];

        if ((
                !str_contains($header, '|')
                && !str_contains($line['text'], '|')
                && !str_contains($line['text'], ':')
            )
            || str_contains($header, "\n")
        ) {
            return null;
        }

        if (rtrim($line['text'], ' -:|') !== '') {
            return null;
        }

        $alignments = [];
        $divider    = trim(trim($line['text']), '|');

        foreach (explode('|', $divider) as $dividerCell) {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '') {
                return null;
            }

            $alignment = null;

            if ($dividerCell[0] === ':') {
                $alignment = 'left';
            }

            if (str_ends_with($dividerCell, ':')) {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments[] = $alignment;
        }

        $headerCells = explode('|', trim(trim($header), '|'));

        if (count($headerCells) !== count($alignments)) {
            return null;
        }

        $headerElements = [];

        foreach ($headerCells as $index => $headerCell) {
            $headerElement = [
                'name'    => 'th',
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => trim($headerCell),
                    'destination' => 'elements',
                ],
            ];

            if (isset($alignments[$index])) {
                $headerElement['attributes'] = ['style' => "text-align: {$alignments[$index]};"];
            }

            $headerElements[] = $headerElement;
        }

        return [
            'alignments' => $alignments,
            'identified' => true,
            'element'    => [
                'name'     => 'table',
                'elements' => [
                    [
                        'name'     => 'thead',
                        'elements' => [
                            ['name' => 'tr', 'elements' => $headerElements],
                        ],
                    ],
                    [
                        'name'     => 'tbody',
                        'elements' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Continue a table with another body row.
     *
     * @param Line $line The current line.
     * @param Block $block The table block.
     * @return Block|null The updated block, or null when the table ended.
     */
    private function blockTableContinue(array $line, array $block): ?array
    {
        if (isset($block['interrupted'])) {
            return null;
        }

        if (count($block['alignments']) !== 1
            && $line['text'][0] !== '|'
            && !str_contains($line['text'], '|')
        ) {
            return null;
        }

        $row = trim(trim($line['text']), '|');

        preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);

        $cells    = array_slice($matches[0], 0, count($block['alignments']));
        $elements = [];

        foreach ($cells as $index => $cell) {
            $element = [
                'name'    => 'td',
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => trim($cell),
                    'destination' => 'elements',
                ],
            ];

            if (isset($block['alignments'][$index])) {
                $element['attributes'] = ['style' => 'text-align: ' . $block['alignments'][$index] . ';'];
            }

            $elements[] = $element;
        }

        $block['element']['elements'][1]['elements'][] = [
            'name'     => 'tr',
            'elements' => $elements,
        ];

        return $block;
    }

    /**
     * Start a new paragraph block.
     *
     * @param Line $line The current line.
     * @return Block The paragraph block.
     */
    private function paragraph(array $line): array
    {
        return [
            'type'    => 'Paragraph',
            'element' => [
                'name'    => 'p',
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $line['text'],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    /**
     * Append a line to an existing paragraph.
     *
     * @param Line $line The current line.
     * @param Block $block The paragraph block.
     * @return Block|null The updated block, or null when the paragraph was interrupted.
     */
    private function paragraphContinue(array $line, array $block): ?array
    {
        if (isset($block['interrupted'])) {
            return null;
        }

        $block['element']['handler']['argument'] .= "\n" . $line['text'];

        return $block;
    }

    // -------------------------------------------------------------------------
    // Inline parsing
    // -------------------------------------------------------------------------

    /**
     * Parse inline Markdown into elements.
     *
     * @param string $text The inline text.
     * @param list<string> $nonNestables Inline types that must not be parsed here.
     * @return list<Element> The parsed elements.
     */
    private function lineElements(string $text, array $nonNestables = []): array
    {
        $text     = str_replace(["\r\n", "\r"], "\n", $text);
        $elements = [];

        $nonNestables = $nonNestables === []
            ? []
            : array_combine($nonNestables, $nonNestables);

        // the excerpt starts at the first occurrence of an inline marker
        while (($excerptText = strpbrk($text, self::INLINE_MARKERS)) !== false) {
            $marker         = $excerptText[0];
            $markerPosition = strlen($text) - strlen($excerptText);

            /** @var Excerpt $excerpt */
            $excerpt = ['text' => $excerptText, 'context' => $text];

            foreach (self::INLINE_TYPES[$marker] as $inlineType) {
                // is this inline type allowed in the current context?
                if (isset($nonNestables[$inlineType])) {
                    continue;
                }

                $inline = $this->{"inline$inlineType"}($excerpt);

                if ($inline === null) {
                    continue;
                }

                // make sure the inline belongs to "our" marker
                if (isset($inline['position']) && $inline['position'] > $markerPosition) {
                    continue;
                }

                $inline['position'] ??= $markerPosition;

                // the new element inherits our non-nestables
                $inline['element']['nonNestables'] = isset($inline['element']['nonNestables'])
                    ? array_merge($inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables;

                // text before the inline, then the inline itself
                $elements[] = $this->inlineText(substr($text, 0, $inline['position']));
                $elements[] = $this->extractElement($inline);

                // remove the examined text
                $text = substr($text, $inline['position'] + $inline['extent']);

                continue 2;
            }

            // the marker does not belong to an inline
            $elements[] = $this->inlineText(substr($text, 0, $markerPosition + 1));
            $text       = substr($text, $markerPosition + 1);
        }

        $elements[] = $this->inlineText($text);

        foreach ($elements as &$element) {
            $element['autobreak'] ??= false;
        }

        unset($element);

        return $elements;
    }

    /**
     * Build the element for plain text, converting line breaks where needed.
     *
     * @param string $text The plain text.
     * @return Element The text element.
     */
    private function inlineText(string $text): array
    {
        return [
            'elements' => self::pregReplaceElements(
                $this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
                [
                    ['name' => 'br'],
                    ['text' => "\n"],
                ],
                $text
            ),
        ];
    }

    /**
     * Inline code (`` `code` ``).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineCode(array $excerpt): ?array
    {
        $marker = $excerpt['text'][0];

        if (preg_match(
            '/^([' . $marker . ']++)[ ]*+(.+?)[ ]*+(?<![' . $marker . '])\1(?!' . $marker . ')/s',
            $excerpt['text'],
            $matches
        ) !== 1
        ) {
            return null;
        }

        return [
            'extent'  => strlen($matches[0]),
            'element' => [
                'name' => 'code',
                'text' => preg_replace('/[ ]*+\n/', ' ', $matches[2]),
            ],
        ];
    }

    /**
     * Email autolink (`<user@example.com>`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineEmailTag(array $excerpt): ?array
    {
        $hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

        $commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@'
            . $hostnameLabel . '(?:\.' . $hostnameLabel . ')*';

        if (!str_contains($excerpt['text'], '>')
            || preg_match('/^<((mailto:)?' . $commonMarkEmail . ')>/i', $excerpt['text'], $matches) !== 1
        ) {
            return null;
        }

        $url = $matches[1];

        if (!isset($matches[2])) {
            $url = "mailto:$url";
        }

        return [
            'extent'  => strlen($matches[0]),
            'element' => [
                'name'       => 'a',
                'text'       => $matches[1],
                'attributes' => ['href' => $url],
            ],
        ];
    }

    /**
     * Emphasis (`*em*`, `_em_`) and strong (`**strong**`, `__strong__`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineEmphasis(array $excerpt): ?array
    {
        if (!isset($excerpt['text'][1])) {
            return null;
        }

        $marker = $excerpt['text'][0];

        if ($excerpt['text'][1] === $marker
            && preg_match(self::STRONG_REGEX[$marker], $excerpt['text'], $matches) === 1
        ) {
            $emphasis = 'strong';
        } elseif (preg_match(self::EM_REGEX[$marker], $excerpt['text'], $matches) === 1) {
            $emphasis = 'em';
        } else {
            return null;
        }

        return [
            'extent'  => strlen($matches[0]),
            'element' => [
                'name'    => $emphasis,
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $matches[1],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    /**
     * Backslash escape (`\*`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when the escaped character is not special.
     */
    private function inlineEscapeSequence(array $excerpt): ?array
    {
        if (isset($excerpt['text'][1]) && in_array($excerpt['text'][1], self::SPECIAL_CHARACTERS, true)) {
            return [
                'element' => ['rawHtml' => $excerpt['text'][1]],
                'extent'  => 2,
            ];
        }

        return null;
    }

    /**
     * Image (`![alt](src "title")`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineImage(array $excerpt): ?array
    {
        if (!isset($excerpt['text'][1]) || $excerpt['text'][1] !== '[') {
            return null;
        }

        $excerpt['text'] = substr($excerpt['text'], 1);

        $link = $this->inlineLink($excerpt);

        if ($link === null) {
            return null;
        }

        $attributes = [
            'src' => $link['element']['attributes']['href'],
            'alt' => $link['element']['handler']['argument'],
        ];

        $attributes += $link['element']['attributes'];

        unset($attributes['href']);

        return [
            'extent'  => $link['extent'] + 1,
            'element' => [
                'name'       => 'img',
                'attributes' => $attributes,
                'autobreak'  => true,
            ],
        ];
    }

    /**
     * Link (`[text](url "title")` or reference style `[text][id]`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineLink(array $excerpt): ?array
    {
        $element = [
            'name'         => 'a',
            'handler'      => [
                'function'    => 'lineElements',
                'argument'    => null,
                'destination' => 'elements',
            ],
            'nonNestables' => ['Url', 'Link'],
            'attributes'   => [
                'href'  => null,
                'title' => null,
            ],
        ];

        $extent    = 0;
        $remainder = $excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches) !== 1) {
            return null;
        }

        $element['handler']['argument'] = $matches[1];

        $extent   += strlen($matches[0]);
        $remainder = substr($remainder, $extent);

        if (preg_match(
            '/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/',
            $remainder,
            $matches
        ) === 1
        ) {
            $element['attributes']['href'] = $matches[1];

            if (isset($matches[2])) {
                $element['attributes']['title'] = substr($matches[2], 1, -1);
            }

            $extent += strlen($matches[0]);
        } else {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches) === 1) {
                $definition = $matches[1] !== '' ? $matches[1] : $element['handler']['argument'];
                $extent    += strlen($matches[0]);
            } else {
                $definition = $element['handler']['argument'];
            }

            $reference = $this->referenceDefinitions[strtolower($definition)] ?? null;

            if ($reference === null) {
                return null;
            }

            $element['attributes']['href']  = $reference['url'];
            $element['attributes']['title'] = $reference['title'];
        }

        return [
            'extent'  => $extent,
            'element' => $element,
        ];
    }

    /**
     * Inline raw HTML (`<span>`, `</span>`, `<!-- comment -->`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineMarkup(array $excerpt): ?array
    {
        if ($this->markupEscaped || $this->safeMode || !str_contains($excerpt['text'], '>')) {
            return null;
        }

        $matches = [];

        $matched = match (true) {
            $excerpt['text'][1] === '/'
            => preg_match('/^<\/\w[\w-]*+[ ]*+>/s', $excerpt['text'], $matches) === 1,
            $excerpt['text'][1] === '!'
            => preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $excerpt['text'], $matches) === 1,
            $excerpt['text'][1] !== ' '
            => preg_match(
                '/^<\w[\w-]*+(?:[ ]*+' . self::REGEX_HTML_ATTRIBUTE . ')*+[ ]*+\/?>/s',
                $excerpt['text'],
                    $matches
            ) === 1,
            default => false,
        };

        if (!$matched || !isset($matches[0])) {
            return null;
        }

        return [
            'element' => ['rawHtml' => $matches[0]],
            'extent'  => strlen($matches[0]),
        ];
    }

    /**
     * HTML entity (`&amp;`, `&#169;`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineSpecialCharacter(array $excerpt): ?array
    {
        if (substr($excerpt['text'], 1, 1) !== ' '
            && str_contains($excerpt['text'], ';')
            && preg_match('/^&(#?+[0-9a-zA-Z]++);/', $excerpt['text'], $matches) === 1
        ) {
            return [
                'element' => ['rawHtml' => '&' . $matches[1] . ';'],
                'extent'  => strlen($matches[0]),
            ];
        }

        return null;
    }

    /**
     * Strikethrough (`~~text~~`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineStrikethrough(array $excerpt): ?array
    {
        if (!isset($excerpt['text'][1])) {
            return null;
        }

        if ($excerpt['text'][1] === '~' && preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $excerpt['text'], $matches) === 1) {
            return [
                'extent'  => strlen($matches[0]),
                'element' => [
                    'name'    => 'del',
                    'handler' => [
                        'function'    => 'lineElements',
                        'argument'    => $matches[1],
                        'destination' => 'elements',
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * Bare URL (`https://example.com`), linked only when enabled.
     *
     * @param Excerpt $excerpt The excerpt starting at the marker (`:`).
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineUrl(array $excerpt): ?array
    {
        if (!$this->urlsLinked || !isset($excerpt['text'][2]) || $excerpt['text'][2] !== '/') {
            return null;
        }

        if (!str_contains($excerpt['context'], 'http')
            || preg_match(
                '/\bhttps?+:[\/]{2}[^\s<]+\b\/*+/ui',
                $excerpt['context'],
                $matches,
                PREG_OFFSET_CAPTURE
            ) !== 1
        ) {
            return null;
        }

        $url = $matches[0][0];

        return [
            'extent'   => strlen($url),
            'position' => $matches[0][1],
            'element'  => [
                'name'       => 'a',
                'text'       => $url,
                'attributes' => ['href' => $url],
            ],
        ];
    }

    /**
     * URL autolink (`<https://example.com>`).
     *
     * @param Excerpt $excerpt The excerpt starting at the marker.
     * @return Inline|null The inline, or null when it does not match.
     */
    private function inlineUrlTag(array $excerpt): ?array
    {
        if (!str_contains($excerpt['text'], '>')
            || preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $excerpt['text'], $matches) !== 1
        ) {
            return null;
        }

        return [
            'extent'  => strlen($matches[0]),
            'element' => [
                'name'       => 'a',
                'text'       => $matches[1],
                'attributes' => ['href' => $matches[1]],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Resolve an element's `handler` (lazy inline/block parsing) into child elements.
     *
     * @param Element $element The element, possibly carrying a `handler`.
     * @return Element The element with the handler resolved and removed.
     * @throws LogicException When the handler names an unknown function.
     */
    private function handle(array $element): array
    {
        if (!isset($element['handler'])) {
            return $element;
        }

        $nonNestables = $element['nonNestables'] ?? [];
        $argument     = $element['handler']['argument'];
        $destination  = $element['handler']['destination'];

        $element[$destination] = match ($element['handler']['function']) {
            'lineElements'  => $this->lineElements($argument, $nonNestables),
            'linesElements' => $this->linesElements($argument),
            'li'            => $this->li($argument),
            default         => throw new LogicException('Unknown handler: ' . $element['handler']['function']),
        };

        unset($element['handler']);

        return $element;
    }

    /**
     * Render one element (and its children) to HTML.
     *
     * @param Element $element The element to render.
     * @return string The HTML markup.
     */
    private function element(array $element): string
    {
        if ($this->safeMode) {
            $element = $this->sanitiseElement($element);
        }

        $element = $this->handle($element);
        $name    = $element['name'] ?? null;
        $markup  = '';

        if ($name !== null) {
            $markup .= '<' . $name;

            foreach ($element['attributes'] ?? [] as $attribute => $value) {
                if ($value === null) {
                    continue;
                }

                $markup .= " $attribute=\"" . self::escape((string) $value) . '"';
            }
        }

        $text          = null;
        $permitRawHtml = false;

        if (isset($element['text'])) {
            $text = $element['text'];
        } elseif (isset($element['rawHtml'])) {
            // very strongly consider an alternative before emitting raw HTML
            $text          = $element['rawHtml'];
            $permitRawHtml = !$this->safeMode || ($element['allowRawHtmlInSafeMode'] ?? false);
        }

        $hasContent = $text !== null || isset($element['element']) || isset($element['elements']);

        if ($hasContent) {
            $markup .= $name !== null ? '>' : '';

            if (isset($element['elements'])) {
                $markup .= $this->elements($element['elements']);
            } elseif (isset($element['element'])) {
                $markup .= $this->element($element['element']);
            } else {
                $markup .= $permitRawHtml ? $text : self::escape((string) $text, true);
            }

            $markup .= $name !== null ? '</' . $name . '>' : '';
        } elseif ($name !== null) {
            $markup .= ' />';
        }

        return $markup;
    }

    /**
     * Render a list of elements to HTML, inserting line breaks between block elements.
     *
     * @param list<Element> $elements The elements to render.
     * @return string The HTML markup.
     */
    private function elements(array $elements): string
    {
        $markup    = '';
        $autoBreak = true;

        foreach ($elements as $element) {
            if ($element === []) {
                continue;
            }

            $autoBreakNext = (bool) ($element['autobreak'] ?? isset($element['name']));

            // (autobreak === false) covers both sides of an element
            $autoBreak = $autoBreak && $autoBreakNext;

            $markup   .= ($autoBreak ? "\n" : '') . $this->element($element);
            $autoBreak = $autoBreakNext;
        }

        return $markup . ($autoBreak ? "\n" : '');
    }

    /**
     * Parse the lines of a list item. A tight item unwraps its first paragraph.
     *
     * @param list<string> $lines The item's lines.
     * @return list<Element> The item's child elements.
     */
    private function li(array $lines): array
    {
        $elements = $this->linesElements($lines);

        if (!in_array('', $lines, true)
            && isset($elements[0]['name'])
            && $elements[0]['name'] === 'p'
        ) {
            unset($elements[0]['name']);
        }

        return $elements;
    }

    /**
     * Replace every match of `$regexp` in `$text` with `$replacement`, returning
     * the pieces as a list of elements.
     *
     * @param string $regexp The regular expression to look for.
     * @param list<Element> $replacement Elements inserted for every match.
     * @param string $text The text to split.
     * @return list<Element> Text and replacement elements, in order.
     */
    private static function pregReplaceElements(string $regexp, array $replacement, string $text): array
    {
        $newElements = [];

        while (preg_match($regexp, $text, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $matches[0][1];

            $newElements[] = ['text' => substr($text, 0, $offset)];

            foreach ($replacement as $element) {
                $newElements[] = $element;
            }

            $text = substr($text, $offset + strlen($matches[0][0]));
        }

        $newElements[] = ['text' => $text];

        return $newElements;
    }

    // -------------------------------------------------------------------------
    // Safe mode & helpers
    // -------------------------------------------------------------------------

    /**
     * Remove dangerous attributes (`on*` handlers, malformed names, unsafe URLs).
     *
     * @param Element $element The element to sanitise.
     * @return Element The sanitised element.
     */
    private function sanitiseElement(array $element): array
    {
        if (!isset($element['name'])) {
            unset($element['attributes']);

            return $element;
        }

        if (isset(self::SAFE_URL_ATTRIBUTES[$element['name']])) {
            $element = $this->filterUnsafeUrlInAttribute($element, self::SAFE_URL_ATTRIBUTES[$element['name']]);
        }

        foreach ($element['attributes'] ?? [] as $attribute => $value) {
            $attribute = (string) $attribute;

            // filter out badly parsed attributes and dump `on*` event attributes
            if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/', $attribute) !== 1
                || self::striAtStart($attribute, 'on')
            ) {
                unset($element['attributes'][$attribute]);
            }
        }

        return $element;
    }

    /**
     * Neutralise a URL attribute whose scheme is not whitelisted.
     *
     * @param Element $element The element holding the attribute.
     * @param string $attribute The URL attribute name (`href` or `src`).
     * @return Element The element, with `:` percent-encoded when the scheme is unsafe.
     */
    private function filterUnsafeUrlInAttribute(array $element, string $attribute): array
    {
        $url = (string) ($element['attributes'][$attribute] ?? '');

        if (array_any(
            self::SAFE_LINKS_WHITELIST,
            static fn (string $scheme): bool => self::striAtStart($url, $scheme)
        )
        ) {
            return $element;
        }

        $element['attributes'][$attribute] = str_replace(':', '%3A', $url);

        return $element;
    }

    /**
     * Escape text for HTML output.
     *
     * @param string $text The text to escape.
     * @param bool $allowQuotes When true, quotes are left untouched (element content).
     * @return string The escaped text.
     */
    private static function escape(string $text, bool $allowQuotes = false): string
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    /**
     * Case-insensitive "starts with".
     *
     * @param string $string The haystack.
     * @param string $needle The prefix to look for.
     * @return bool True when `$string` begins with `$needle`.
     */
    private static function striAtStart(string $string, string $needle): bool
    {
        return strncasecmp($string, $needle, strlen($needle)) === 0;
    }
}
