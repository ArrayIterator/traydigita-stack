<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

#
#
# ParseDown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

use function array_combine;
use function array_merge;
use function array_slice;
use function call_user_func;
use function end;
use function explode;
use function function_exists;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function method_exists;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function str_repeat;
use function str_replace;
use function strcspn;
use function strlen;
use function strpbrk;
use function strpos;
use function strspn;
use function strstr;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use const ENT_NOQUOTES;
use const ENT_QUOTES;
use const PREG_OFFSET_CAPTURE;

class ParseDown
{
    # ~

    public const string VERSION = '1.8.0';

    protected bool $breaksEnabled = false;

    protected bool $markupEscaped = false;

    protected bool $safeMode = false;

    protected bool $urlsLinked = true;

    protected bool $strictMode = false;

    /**
     * @var string[]
     */
    protected array $unmarkedBlockTypes = [
        'Code',
    ];

    /**
     * @var string[]
     */
    protected array $safeLinksWhitelist = [
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

    #
    # Lines
    #
    /**
     * @var array<array-key, string[]> $BlockTypes
     */
    protected array $BlockTypes = [
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

    #
    # Inline Elements
    #

    /**
     * @var array<string, string[]>
     */
    protected array $InlineTypes = [
        '!' => ['Image'],
        '&' => ['SpecialCharacter'],
        '*' => ['Emphasis'],
        ':' => ['Url'],
        '<' => ['UrlTag', 'EmailTag', 'Markup'],
        '[' => ['Link'],
        '_' => ['Emphasis'],
        '`' => ['Code'],
        '~' => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    # ~

    protected string $inlineMarkerList = '!*_&[:<`~\\';

    #
    # Fields
    #

    /** @var array<string, array<string, mixed>> */
    protected array $DefinitionData = [];

    #
    # Read-Only

    /**
     * @var string[]
     */
    protected array $specialCharacters = [
        '\\',
        '`',
        '*',
        '_',
        '{',
        '}',
        '[',
        ']',
        '(',
        ')',
        '>',
        '#',
        '+',
        '-',
        '.',
        '!',
        '|',
        '~'
    ];

    /**
     * @var array<string, string>
     */
    protected array $StrongRegex = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
    ];

    /**
     * @var array<string, string>
     */
    protected array $EmRegex = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    /**
     * @var string
     */
    protected string $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

    /**
     * @var string[]
     */
    protected array $voidElements = [
        'area',
        'base',
        'br',
        'col',
        'command',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
    ];

    /**
     * @var string[]
     */
    protected array $textLevelElements = [
        'a',
        'br',
        'bdo',
        'abbr',
        'blink',
        'nextid',
        'acronym',
        'basefont',
        'b',
        'em',
        'big',
        'cite',
        'small',
        'spacer',
        'listing',
        'i',
        'rp',
        'del',
        'code',
        'strike',
        'marquee',
        'q',
        'rt',
        'ins',
        'font',
        'strong',
        's',
        'tt',
        'kbd',
        'mark',
        'u',
        'xm',
        'sub',
        'nobr',
        'sup',
        'ruby',
        'var',
        'span',
        'wbr',
        'time',
    ];

    final public function __construct()
    {
    }

    public function text(string $text): string
    {
        $Elements = $this->textElements($text);

        # convert to markup
        $markup = $this->elements($Elements);

        # trim line breaks
        return trim($markup, "\n");
    }

    /** @return list<array<string, mixed>> */
    protected function textElements(string $text): array
    {
        # make sure no definitions are set
        $this->DefinitionData = [];

        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        return $this->linesElements($lines);
    }

    public function setBreaksEnabled(bool $breaksEnabled): static
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    public function setMarkupEscaped(bool $markupEscaped): static
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    public function setUrlsLinked(bool $urlsLinked): static
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    public function setSafeMode(bool $safeMode): static
    {
        $this->safeMode = $safeMode;

        return $this;
    }

    public function setStrictMode(bool $strictMode): static
    {
        $this->strictMode = $strictMode;

        return $this;
    }


    # ~

    #
    # Blocks
    #

    /** @param list<string> $lines */
    protected function lines(array $lines): string
    {
        return $this->elements($this->linesElements($lines));
    }

    /** @param list<string> $lines
     * @return list<array<string, mixed>>
     */
    protected function linesElements(array $lines): array
    {
        $Elements = [];
        $CurrentBlock = null;

        foreach ($lines as $line) {
            if (rtrim($line) === '') {
                if (isset($CurrentBlock)) {
                    $CurrentBlock['interrupted'] = (isset($CurrentBlock['interrupted'])
                        ? $CurrentBlock['interrupted'] + 1 : 1
                    );
                }

                continue;
            }

            while (($beforeTab = strstr($line, "\t", true)) !== false) {
                $column = function_exists('mb_strlen')
                    ? mb_strlen($beforeTab, 'UTF-8')
                    : preg_match_all('/./us', $beforeTab);

                $shortage = 4 - ($column === false ? strlen($beforeTab) : $column) % 4;

                $line = $beforeTab
                    . str_repeat(' ', $shortage)
                    . substr($line, strlen($beforeTab) + 1);
            }

            $indent = strspn($line, ' ');

            $text = $indent > 0 ? substr($line, $indent) : $line;

            # ~

            $Line = ['body' => $line, 'indent' => $indent, 'text' => $text];

            # ~

            if (isset($CurrentBlock['continuable'])) {
                $methodName = 'block' . $CurrentBlock['type'] . 'Continue';
                $Block = $this->$methodName($Line, $CurrentBlock);

                if (isset($Block)) {
                    $CurrentBlock = $Block;

                    continue;
                } else {
                    if ($this->isBlockCompletable($CurrentBlock['type'])) {
                        $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
                        $CurrentBlock = $this->$methodName($CurrentBlock);
                    }
                }
            }

            # ~

            $marker = $text[0];

            # ~

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->BlockTypes[$marker])) {
                foreach ($this->BlockTypes[$marker] as $blockType) {
                    $blockTypes [] = $blockType;
                }
            }

            #
            # ~

            foreach ($blockTypes as $blockType) {
                $Block = $this->{"block$blockType"}($Line, $CurrentBlock);

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

            # ~

            if (isset($CurrentBlock) && $CurrentBlock['type'] === 'Paragraph') {
                $Block = $this->paragraphContinue($Line, $CurrentBlock);
            }

            if (isset($Block)) {
                $CurrentBlock = $Block;
            } else {
                if (isset($CurrentBlock)) {
                    $Elements[] = $this->extractElement($CurrentBlock);
                }

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        # ~

        if (isset($CurrentBlock['continuable']) && $this->isBlockCompletable($CurrentBlock['type'])) {
            $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
            $CurrentBlock = $this->$methodName($CurrentBlock);
        }

        # ~

        if (isset($CurrentBlock)) {
            $Elements[] = $this->extractElement($CurrentBlock);
        }

        # ~

        return $Elements;
    }

    /** @param array<string, mixed> $Component
     * @return array<string, mixed>
     */
    protected function extractElement(array $Component): array
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

    protected function isBlockContinuable(string $Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Continue');
    }

    protected function isBlockCompletable(string $Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Complete');
    }

    #
    # Code

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     *     body?: string,
     * } $Line
     * @param array<string, mixed>|null $Block
     * @return array<string, mixed>|null
     */
    protected function blockCode(array $Line, ?array $Block = null): ?array
    {
        if (!$Block || !is_int($Line['indent'] ?? null) || !is_string($Line['body'] ?? null)
            || !isset($Block['type'])
        ) {
            return null;
        }
        if ($Block['type'] === 'Paragraph' && !isset($Block['interrupted'])) {
            return null;
        }

        if ($Line['indent'] >= 4) {
            $text = substr($Line['body'], 4);

            return [
                'element' => [
                    'name' => 'pre',
                    'element' => [
                        'name' => 'code',
                        'text' => $text,
                    ],
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     *     body?: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockCodeContinue(array $Line, array $Block): ?array
    {
        if (!is_int($Line['indent'] ?? null) || !is_string($Line['body'] ?? null)) {
            return null;
        }
        if ($Line['indent'] >= 4) {
            if (isset($Block['interrupted'])) {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

                unset($Block['interrupted']);
            }

            $Block['element']['element']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['element']['text'] .= $text;

            return $Block;
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     element: array<string, mixed>&array{
     *         element: array<string, mixed>&array{
     *             text: string,
     *         },
     *     },
     * } $Block
     * @return array<string, mixed>
     */
    protected function blockCodeComplete(array $Block): array
    {
        return $Block;
    }

    #
    # Comment

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     *     body?: string,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockComment(array $Line): ?array
    {
        if ($this->markupEscaped || $this->safeMode || !is_string($Line['text'] ?? null)
            || !is_string($Line['body'] ?? null)
        ) {
            return null;
        }

        if (str_starts_with($Line['text'], '<!--')) {
            $Block = [
                'element' => [
                    'rawHtml' => $Line['body'],
                    'autobreak' => true,
                ],
            ];

            if (str_contains($Line['text'], '-->')) {
                $Block['closed'] = true;
            }

            return $Block;
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     *     body?: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockCommentContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['closed']) || !is_string($Line['body'] ?? null) || !is_string($Line['text'] ?? null)) {
            return null;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        if (str_contains($Line['text'], '-->')) {
            $Block['closed'] = true;
        }

        return $Block;
    }

    #
    # Fenced Code

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockFencedCode(array $Line): ?array
    {
        if (!is_string($Line['text'] ?? null)) {
            return null;
        }
        $marker = $Line['text'][0];

        $openerLength = strspn($Line['text'], $marker);

        if ($openerLength < 3) {
            return null;
        }

        $infostring = trim(substr($Line['text'], $openerLength), "\t ");

        if (str_contains($infostring, '`')) {
            return null;
        }

        $Element = [
            'name' => 'code',
            'text' => '',
        ];

        if ($infostring !== '') {
            /**
             * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
             * Every HTML element may have a class attribute specified.
             * The attribute, if specified, must have a value that is a set
             * of space-separated tokens representing the various classes
             * that the element belongs to.
             * [...]
             * The space characters, for the purposes of this specification,
             * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
             * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
             * U+000D CARRIAGE RETURN (CR).
             */
            $language = substr($infostring, 0, strcspn($infostring, " \t\n\f\r"));

            $Element['attributes'] = ['class' => "language-$language"];
        }

        return [
            'char' => $marker,
            'openerLength' => $openerLength,
            'element' => [
                'name' => 'pre',
                'element' => $Element,
            ],
        ];
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     *     body?: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockFencedCodeContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['complete']) || !is_string($Line['body'] ?? null)) {
            return null;
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

            unset($Block['interrupted']);
        }

        if (($len = strspn($Line['text'], $Block['char'])) >= $Block['openerLength']
            && rtrim(substr($Line['text'], $len), ' ') === ''
        ) {
            $Block['element']['element']['text'] = substr($Block['element']['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['element']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    /**
     * @param array<string, mixed>&array{
     *     element: array<string, mixed>&array{
     *         element: array<string, mixed>&array{
     *             text: string,
     *         },
     *     },
     * } $Block
     * @return array<string, mixed>
     */
    protected function blockFencedCodeComplete(array $Block): array
    {
        return $Block;
    }

    #
    # Header

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockHeader(array $Line): ?array
    {
        if (!is_string($Line['text'] ?? null)) {
            return null;
        }
        $level = strspn($Line['text'], '#');

        if ($level > 6) {
            return null;
        }

        $text = trim($Line['text'], '#');

        if ($this->strictMode && isset($text[0]) && $text[0] !== ' ') {
            return null;
        }

        $text = trim($text, ' ');

        return [
            'element' => [
                'name' => 'h' . $level,
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $text,
                    'destination' => 'elements',
                ]
            ],
        ];
    }

    #
    # List

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     * } $Line
     * @param array<string, mixed>|null $CurrentBlock
     * @return array<string, mixed>|null
     */
    protected function blockList(array $Line, ?array $CurrentBlock = null): ?array
    {
        if (!is_string($Line['text'] ?? null) || !is_int($Line['indent'] ?? null)) {
            return null;
        }
        list($name, $pattern) = $Line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]{1,9}+[.\)]'];

        if (preg_match('/^(' . $pattern . '( ++|$))(.*+)/', $Line['text'], $matches)) {
            $contentIndent = strlen($matches[2]);

            if ($contentIndent >= 5) {
                $contentIndent -= 1;
                $matches[1] = substr($matches[1], 0, -$contentIndent);
                $matches[3] = str_repeat(' ', $contentIndent) . $matches[3];
            } elseif ($contentIndent === 0) {
                $matches[1] .= ' ';
            }

            /**
             * @var array{
             *     0: string,
             *     1: string,
             *     2: string,
             *     3: string
             * } $matches
             */
            $markerWithoutWhitespace = strstr($matches[1], ' ', true);
            $markerWithoutWhitespace = $markerWithoutWhitespace === false ? $matches[1] : $markerWithoutWhitespace;
            $Block = [
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'data' => [
                    'type' => $name,
                    'marker' => $matches[1],
                    'markerType' => ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1)),
                ],
                'element' => [
                    'name' => $name,
                    'elements' => [],
                ],
            ];

            $Block['data']['markerTypeRegex'] = preg_quote($Block['data']['markerType'], '/');
            if ($name === 'ol') {
                $listStart = ltrim((string)strstr($matches[1], $Block['data']['markerType'], true), '0') ?: '0';

                if ($listStart !== '1') {
                    if (isset($CurrentBlock)
                        && $CurrentBlock['type'] === 'Paragraph'
                        && !isset($CurrentBlock['interrupted'])
                    ) {
                        return null;
                    }

                    $Block['element']['attributes'] = ['start' => $listStart];
                }
            }

            $Block['li'] = [
                'name' => 'li',
                'handler' => [
                    'function' => 'li',
                    'argument' => !empty($matches[3]) ? [$matches[3]] : [],
                    'destination' => 'elements'
                ]
            ];

            $Block['element']['elements'][] = &$Block['li'];

            return $Block;
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Block
     * @param array<string, mixed> $Line
     * @return array<string, mixed>|null
     */
    protected function blockListContinue(array $Line, array $Block): ?array
    {
        if (!is_string($Line['text'] ?? null) || !is_int($Line['indent'] ?? null)) {
            return null;
        }
        if (!is_array($Block['li'] ?? null) || !is_array($Block['data'] ?? null)) {
            return null;
        }
        if (isset($Block['interrupted']) && empty($Block['li']['handler']['argument'])) {
            return null;
        }
        if (!is_int($Block['indent'] ?? null) || !is_string($Block['data']['marker'] ?? null)) {
            return null;
        }
        $requiredIndent = ($Block['indent'] + strlen($Block['data']['marker']));

        if ($Line['indent'] < $requiredIndent
            && (
                (
                    $Block['data']['type'] === 'ol'
                    && preg_match(
                        '/^[0-9]++' . $Block['data']['markerTypeRegex'] . '(?: ++(.*)|$)/',
                        $Line['text'],
                        $matches
                    )
                ) || (
                    $Block['data']['type'] === 'ul'
                    && preg_match(
                        '/^' . $Block['data']['markerTypeRegex'] . '(?: ++(.*)|$)/',
                        $Line['text'],
                        $matches
                    )
                )
            )
        ) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'][] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = $matches[1] ?? '';

            $Block['indent'] = $Line['indent'];

            $Block['li'] = [
                'name' => 'li',
                'handler' => [
                    'function' => 'li',
                    'argument' => [$text],
                    'destination' => 'elements'
                ]
            ];

            $Block['element']['elements'][] = &$Block['li'];

            return $Block;
        } elseif ($Line['indent'] < $requiredIndent && $this->blockList($Line)) {
            return null;
        }

        if ($Line['text'][0] === '[' && $this->blockReference($Line)) {
            return $Block;
        }

        if ($Line['indent'] >= $requiredIndent) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'][] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], $requiredIndent);

            $Block['li']['handler']['argument'][] = $text;

            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $text = preg_replace('/^ {0,' . $requiredIndent . '}+/', '', $Line['body']);

            $Block['li']['handler']['argument'][] = $text;

            return $Block;
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     loose?: bool,
     *     element: array<string, mixed>&array{
     *         elements: list<array<string, mixed>>,
     *     },
     * } $Block
     * @return array<string, mixed>
     */
    protected function blockListComplete(array $Block): array
    {
        if (isset($Block['loose']) || !is_array($Block['element']['elements'] ?? null)) {
            foreach ($Block['element']['elements'] as &$li) {
                if (is_array($li)) {
                    continue;
                }
                if (end($li['handler']['argument']) !== '') {
                    $li['handler']['argument'][] = '';
                }
            }
        }

        return $Block;
    }

    #
    # Quote

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockQuote(array $Line): ?array
    {
        if (!is_string($Line['text'] ?? null)) {
            return null;
        }
        if (preg_match('/^> ?+(.*+)/', $Line['text'], $matches)) {
            return [
                'element' => [
                    'name' => 'blockquote',
                    'handler' => [
                        'function' => 'linesElements',
                        'argument' => (array)$matches[1],
                        'destination' => 'elements',
                    ]
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockQuoteContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted']) || !is_string($Line['text'] ?? null)) {
            return null;
        }

        if ($Line['text'][0] === '>' && preg_match('/^> ?+(.*+)/', $Line['text'], $matches)) {
            $Block['element']['handler']['argument'][] = $matches[1];

            return $Block;
        }

        $Block['element']['handler']['argument'][] = $Line['text'];
        return $Block;
    }

    #
    # Rule

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockRule(array $Line): ?array
    {
        if (!is_string($Line['text'] ?? null)) {
            return null;
        }
        $marker = $Line['text'][0];

        if (substr_count($Line['text'], $marker) >= 3 && rtrim($Line['text'], " $marker") === '') {
            return [
                'element' => [
                    'name' => 'hr',
                ],
            ];
        }
        return null;
    }

    #
    # Setext

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     indent?: int,
     * } $Line
     * @param array<string, mixed>|null $Block
     * @return array<string, mixed>|null
     */
    protected function blockSetextHeader(array $Line, ?array $Block = null): ?array
    {
        if (!$Block || !is_string($Block['type'] ?? null) || !is_int($Line['indent'] ?? null)) {
            return null;
        }
        if ($Block['type'] !== 'Paragraph' || isset($Block['interrupted']) || strlen($Line['text']) < 1) {
            return null;
        }

        if ($Line['indent'] < 4 && rtrim(rtrim($Line['text'], ' '), $Line['text'][0]) === '') {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
        return null;
    }

    #
    # Markup

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockMarkup(array $Line): ?array
    {
        if ($this->markupEscaped || $this->safeMode) {
            return null;
        }

        if (preg_match(
            '/^<\/?+(\w*)(?: *+' . $this->regexHtmlAttribute . ')*+ *+(\/)?>/',
            $Line['text'],
            $matches
        )) {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements)) {
                return null;
            }

            return [
                'name' => $matches[1],
                'element' => [
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     *     body?: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockMarkupContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['closed']) || isset($Block['interrupted'])) {
            return null;
        }

        if (!is_string($Line['body'] ?? null)) {
            return null;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        return $Block;
    }

    #
    # Reference

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @return array<string, mixed>|null
     */
    protected function blockReference(array $Line): ?array
    {
        if (!is_string($Line['text'] ?? null)) {
            return null;
        }
        if (str_contains($Line['text'], ']')
            && preg_match('~^\[(.+?)]: *+<?(\S+?)>?(?: +["\'(](.+)["\')])? *+$~', $Line['text'], $matches)
        ) {
            $id = strtolower($matches[1]);

            $Data = [
                'url' => $matches[2],
                'title' => $matches[3] ?? null,
            ];

            $this->DefinitionData['Reference'][$id] = $Data;

            return [
                'element' => [],
            ];
        }
        return null;
    }

    #
    # Table

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @param array<string, mixed>|null $Block
     * @return array<string, mixed>|null
     */
    protected function blockTable(array $Line, ?array $Block = null): ?array
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph' || isset($Block['interrupted'])) {
            return null;
        }

        if (!str_contains($Block['element']['handler']['argument'], '|')
            && !str_contains($Line['text'], '|')
            && !str_contains($Line['text'], ':')
            || str_contains($Block['element']['handler']['argument'], "\n")
        ) {
            return null;
        }

        if (rtrim($Line['text'], ' -:|') !== '') {
            return null;
        }

        $alignments = [];

        $divider = $Line['text'];

        $divider = trim($divider);
        $divider = trim($divider, '|');

        $dividerCells = explode('|', $divider);

        foreach ($dividerCells as $dividerCell) {
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

            $alignments [] = $alignment;
        }

        # ~

        $HeaderElements = [];

        $header = $Block['element']['handler']['argument'];

        $header = trim($header);
        $header = trim($header, '|');

        $headerCells = explode('|', $header);

        if (count($headerCells) !== count($alignments)) {
            return null;
        }

        foreach ($headerCells as $index => $headerCell) {
            $headerCell = trim($headerCell);

            $HeaderElement = [
                'name' => 'th',
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $headerCell,
                    'destination' => 'elements',
                ]
            ];

            if (isset($alignments[$index])) {
                $alignment = $alignments[$index];

                $HeaderElement['attributes'] = [
                    'style' => "text-align: $alignment;",
                ];
            }

            $HeaderElements [] = $HeaderElement;
        }

        # ~

        $Block = [
            'alignments' => $alignments,
            'identified' => true,
            'element' => [
                'name' => 'table',
                'elements' => [],
            ],
        ];

        $Block['element']['elements'][] = [
            'name' => 'thead',
        ];

        $Block['element']['elements'][] = [
            'name' => 'tbody',
            'elements' => [],
        ];

        $Block['element']['elements'][0]['elements'][] = [
            'name' => 'tr',
            'elements' => $HeaderElements,
        ];

        return $Block;
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function blockTableContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) {
            return null;
        }

        if (count($Block['alignments']) === 1 || $Line['text'][0] === '|' || strpos($Line['text'], '|')) {
            $Elements = [];

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);

            $cells = array_slice($matches[0], 0, count($Block['alignments']));

            foreach ($cells as $index => $cell) {
                $cell = trim($cell);

                $Element = [
                    'name' => 'td',
                    'handler' => [
                        'function' => 'lineElements',
                        'argument' => $cell,
                        'destination' => 'elements',
                    ]
                ];

                if (isset($Block['alignments'][$index])) {
                    $Element['attributes'] = [
                        'style' => 'text-align: ' . $Block['alignments'][$index] . ';',
                    ];
                }

                $Elements [] = $Element;
            }

            $Element = [
                'name' => 'tr',
                'elements' => $Elements,
            ];

            $Block['element']['elements'][1]['elements'][] = $Element;

            return $Block;
        }
        return null;
    }

    #
    # ~
    #

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @return array{
     *     type: string,
     *     element: array{
     *         name: string,
     *         handler: array{
     *             function: string,
     *             argument: string,
     *             destination: string,
     *         },
     *     },
     * }
     */
    protected function paragraph(array $Line): array
    {
        return [
            'type' => 'Paragraph',
            'element' => [
                'name' => 'p',
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $Line['text'],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>&array{
     *     text: string,
     * } $Line
     * @param array<string, mixed> $Block
     * @return array<string, mixed>|null
     */
    protected function paragraphContinue(array $Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) {
            return null;
        }

        $Block['element']['handler']['argument'] .= "\n" . $Line['text'];

        return $Block;
    }

    #
    # ~
    #

    /**
     * @param string $text
     * @param array<string, mixed> $nonNestables
     * @return string
     */
    public function line(string $text, array $nonNestables = []): string
    {
        return $this->elements($this->lineElements($text, $nonNestables));
    }

    /**
     * @param string $text
     * @param array<string, mixed> $nonNestables
     * @return list<array<string, mixed>>
     */
    protected function lineElements(string $text, array $nonNestables = []): array
    {
        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $Elements = [];

        $nonNestables = (empty($nonNestables)
            ? []
            : array_combine($nonNestables, $nonNestables)
        );

        # $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, $this->inlineMarkerList)) {
            $marker = $excerpt[0];

            $markerPosition = strlen($text) - strlen($excerpt);

            $Excerpt = ['text' => $excerpt, 'context' => $text];

            foreach ($this->InlineTypes[$marker] as $inlineType) {
                # check to see if the current inline type is nestable in the current context

                if (isset($nonNestables[$inlineType])) {
                    continue;
                }

                $Inline = $this->{"inline$inlineType"}($Excerpt);

                if (!isset($Inline)) {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) && $Inline['position'] > $markerPosition) {
                    continue;
                }

                # sets a default inline position

                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                # cause the new element to 'inherit' our non nestables


                $Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
                    ? array_merge($Inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables;

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $InlineText = $this->inlineText($unmarkedText);
                $Elements[] = $InlineText['element'];

                # compile the inline
                $Elements[] = $this->extractElement($Inline);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $InlineText = $this->inlineText($unmarkedText);
            $Elements[] = $InlineText['element'];

            $text = substr($text, $markerPosition + 1);
        }

        $InlineText = $this->inlineText($text);
        $Elements[] = $InlineText['element'];

        foreach ($Elements as &$Element) {
            if (!isset($Element['autobreak'])) {
                $Element['autobreak'] = false;
            }
        }

        return $Elements;
    }

    #
    # ~
    #

    /**
     * @param string $text
     * @return array<string, mixed>
     */
    protected function inlineText(string $text): array
    {
        $Inline = [
            'extent' => strlen($text),
            'element' => [],
        ];

        $Inline['element']['elements'] = self::pregReplaceElements(
            $this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
            [
                ['name' => 'br'],
                ['text' => "\n"],
            ],
            $text
        );

        return $Inline;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineCode(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null) || !isset($Excerpt['text'][0])) {
            return null;
        }
        $marker = $Excerpt['text'][0];
        if (preg_match(
            '/^([' . $marker . ']++) *+(.+?) *+(?<![' . $marker . '])\1(?!' . $marker . ')/s',
            $Excerpt['text'],
            $matches
        )) {
            $text = $matches[2];
            $text = preg_replace('/ *+\n/', ' ', $text);

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'code',
                    'text' => $text,
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineEmailTag(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        $hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

        $commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@'
            . $hostnameLabel . '(?:\.' . $hostnameLabel . ')*';

        if (str_contains($Excerpt['text'], '>')
            && preg_match("/^<((mailto:)?$commonMarkEmail)>/i", $Excerpt['text'], $matches)
        ) {
            $url = $matches[1];

            if (!isset($matches[2])) {
                $url = "mailto:$url";
            }

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineEmphasis(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (!isset($Excerpt['text'][1])) {
            return null;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker && preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'strong';
        } elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return null;
        }

        return [
            'extent' => strlen($matches[0]),
            'element' => [
                'name' => $emphasis,
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $matches[1],
                    'destination' => 'elements',
                ]
            ],
        ];
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineEscapeSequence(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (isset($Excerpt['text'][1]) && in_array($Excerpt['text'][1], $this->specialCharacters)) {
            return [
                'element' => ['rawHtml' => $Excerpt['text'][1]],
                'extent' => 2,
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineImage(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '[') {
            return null;
        }

        $Excerpt['text'] = substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null) {
            return null;
        }

        $Inline = [
            'extent' => $Link['extent'] + 1,
            'element' => [
                'name' => 'img',
                'attributes' => [
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['handler']['argument'],
                ],
                'autobreak' => true,
            ],
        ];

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        return $Inline;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineLink(array $Excerpt): ?array
    {
        $Element = [
            'name' => 'a',
            'handler' => [
                'function' => 'lineElements',
                'argument' => null,
                'destination' => 'elements',
            ],
            'nonNestables' => ['Url', 'Link'],
            'attributes' => [
                'href' => null,
                'title' => null,
            ],
        ];

        $extent = 0;

        $remainder = $Excerpt['text'] ?? null;
        if (!is_string($remainder)) {
            return null;
        }
        if (preg_match('/\[((?:[^][]++|(?R))*+)]/', $remainder, $matches)) {
            $Element['handler']['argument'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        } else {
            return null;
        }

        if (preg_match(
            '/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?: +("[^"]*+"|\'[^\']*+\'))?\s*+[)]/',
            $remainder,
            $matches
        )) {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2])) {
                $Element['attributes']['title'] = substr($matches[2], 1, -1);
            }

            $extent += strlen($matches[0]);
        } else {
            if (preg_match('/^\s*\[(.*?)]/', $remainder, $matches)) {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['handler']['argument'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            } else {
                $definition = strtolower($Element['handler']['argument']);
            }

            if (!isset($this->DefinitionData['Reference'][$definition])) {
                return null;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return [
            'extent' => $extent,
            'element' => $Element,
        ];
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineMarkup(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if ($this->markupEscaped || $this->safeMode || !str_contains($Excerpt['text'], '>')) {
            return null;
        }

        if ($Excerpt['text'][1] === '/' && preg_match('/^<\/\w[\w-]*+ *+>/s', $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] === '!' && preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] !== ' ' && preg_match(
            '/^<\w[\w-]*+(?: *+' . $this->regexHtmlAttribute . ')*+ *+\/?>/s',
            $Excerpt['text'],
            $matches
        )) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineSpecialCharacter(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (substr($Excerpt['text'], 1, 1) !== ' ' && str_contains($Excerpt['text'], ';')
            && preg_match('/^&(#?+[0-9a-zA-Z]++);/', $Excerpt['text'], $matches)
        ) {
            return [
                'element' => ['rawHtml' => '&' . $matches[1] . ';'],
                'extent' => strlen($matches[0]),
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineStrikethrough(array $Excerpt): ?array
    {
        if (!is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (!isset($Excerpt['text'][1])) {
            return null;
        }

        if ($Excerpt['text'][1] === '~' && preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'del',
                    'handler' => [
                        'function' => 'lineElements',
                        'argument' => $matches[1],
                        'destination' => 'elements',
                    ]
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineUrl(array $Excerpt): ?array
    {
        if ($this->urlsLinked !== true || !is_string($Excerpt['text'] ?? null)) {
            return null;
        }
        if (!isset($Excerpt['text'][2]) || $Excerpt['text'][2] !== '/') {
            return null;
        }

        if (str_contains($Excerpt['context'], 'http')
            && preg_match('/\bhttps?+:\/{2}[^\s<]+\b\/*+/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)
        ) {
            $url = $matches[0][0];

            return [
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $Excerpt
     * @return array<string, mixed>|null
     */
    protected function inlineUrlTag(array $Excerpt): ?array
    {
        if (is_string($Excerpt['text'] ?? null) && str_contains($Excerpt['text'], '>') && preg_match(
            '/^<(\w++:\/{2}[^ >]++)>/i',
            $Excerpt['text'],
            $matches
        )) {
            $url = $matches[1];

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    # ~

    protected function unmarkedText(string $text): string
    {
        $Inline = $this->inlineText($text);
        return is_array($Inline['element'] ?? null) ? $this->element($Inline['element']) : '';
    }

    #
    # Handlers
    #

    /** @param array<string, mixed> $Element
     * @return array<string, mixed>
     */
    protected function handle(array $Element): array
    {
        if (isset($Element['handler'])) {
            if (!is_array($Element['nonNestables'] ?? null)) {
                $Element['nonNestables'] = [];
            }

            if (is_string($Element['handler'])) {
                $function = $Element['handler'];
                $argument = $Element['text'];
                unset($Element['text']);
                $destination = 'rawHtml';
            } else {
                $function = $Element['handler']['function'];
                $argument = $Element['handler']['argument'];
                $destination = $Element['handler']['destination'];
            }

            $Element[$destination] = $this->{$function}($argument, $Element['nonNestables']);

            if ($destination === 'handler') {
                $Element = $this->handle($Element);
            }

            unset($Element['handler']);
        }

        return $Element;
    }

    /**
     * @param array<string, mixed> $Element
     * @return array<string, mixed>
     */
    protected function handleElementRecursive(array $Element): array
    {
        return $this->elementApplyRecursive([$this, 'handle'], $Element);
    }

    /**
     * @param array<string, mixed> $Elements
     * @return array<string, mixed>
     */
    protected function handleElementsRecursive(array $Elements): array
    {
        return $this->elementsApplyRecursive([$this, 'handle'], $Elements);
    }

    /**
     * Apply a closure to an element and all of its children.
     *
     * @param callable(array<string, mixed> $arg): array<string, mixed> $closure
     * The closure to apply to the element and its children.
     * @param array<string, mixed> $Element The element to apply the closure to.
     * @return array<string, mixed> The modified element.
     */
    protected function elementApplyRecursive(callable $closure, array $Element): array
    {
        $Element = call_user_func($closure, $Element);

        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursive($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementApplyRecursive($closure, $Element['element']);
        }

        return $Element;
    }

    /**
     * Apply a closure to an element and all of its children, depth first.
     *
     * @param callable(array<string, mixed> $arg): array<string, mixed> $closure
     * The closure to apply to the element and its children.
     * @param array<string, mixed> $Element The element to apply the closure to.
     * @return array<string, mixed> The modified element.
     */
    protected function elementApplyRecursiveDepthFirst(callable $closure, array $Element): array
    {
        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['element']);
        }

        return call_user_func($closure, $Element);
    }

    /**
     * Apply a closure to an array of elements and all of their children.
     *
     * @param callable(array<string, mixed> $arg): array<string, mixed> $closure
     * The closure to apply to the elements and their children.
     * @param array<string, mixed> $Elements The elements to apply the closure to.
     * @return array<string, mixed> The modified elements.
     */
    protected function elementsApplyRecursive(callable $closure, array $Elements): array
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursive($closure, $Element);
        }

        return $Elements;
    }

    /**
     * Apply a closure to an array of elements and all of their children, depth first.
     *
     * @param callable(array<string, mixed> $arg): array<string, mixed> $closure
     * The closure to apply to the elements and their children.
     * @param array<string, mixed> $Elements The elements to apply the closure to.
     * @return array<string, mixed> The modified elements.
     */
    protected function elementsApplyRecursiveDepthFirst(callable $closure, array $Elements): array
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursiveDepthFirst($closure, $Element);
        }

        return $Elements;
    }

    /**
     * @param array<string, mixed> $Element
     * @return string
     */
    protected function element(array $Element): string
    {
        if ($this->safeMode) {
            $Element = $this->sanitiseElement($Element);
        }

        # identity map if element has no handler
        $Element = $this->handle($Element);

        $hasName = isset($Element['name']);

        $markup = '';

        if ($hasName) {
            $markup .= '<' . $Element['name'];

            if (isset($Element['attributes'])) {
                foreach ($Element['attributes'] as $name => $value) {
                    if ($value === null) {
                        continue;
                    }

                    $markup .= " $name=\"" . self::escape($value) . '"';
                }
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text'])) {
            $text = $Element['text'];
        } elseif (isset($Element['rawHtml'])) {
            // very strongly consider an alternative if you're writing an
            // extension
            $text = $Element['rawHtml'];
            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        $hasContent = isset($text) || isset($Element['element']) || isset($Element['elements']);

        if ($hasContent && isset($text)) {
            $markup .= $hasName ? '>' : '';
            if (isset($Element['elements'])) {
                $markup .= $this->elements($Element['elements']);
            } elseif (isset($Element['element'])) {
                $markup .= $this->element($Element['element']);
            } else {
                if (!$permitRawHtml) {
                    $markup .= self::escape($text, true);
                } else {
                    $markup .= $text;
                }
            }

            $markup .= $hasName ? '</' . $Element['name'] . '>' : '';
        } elseif ($hasName) {
            $markup .= ' />';
        }

        return $markup;
    }

    /** @param list<array<string, mixed>> $Elements */
    protected function elements(array $Elements): string
    {
        $markup = '';

        $autoBreak = true;

        foreach ($Elements as $Element) {
            if (empty($Element)) {
                continue;
            }

            $autoBreakNext = ($Element['autobreak'] ?? isset($Element['name']));
            // (autobreak === false) covers both sides of an element
            $autoBreak = !$autoBreak ? $autoBreak : $autoBreakNext;

            $markup .= ($autoBreak ? "\n" : '') . $this->element($Element);
            $autoBreak = $autoBreakNext;
        }

        $markup .= $autoBreak ? "\n" : '';

        return $markup;
    }

    # ~

    /** @param list<string> $lines
     * @return list<array<string, mixed>>
     */
    protected function li(array $lines): array
    {
        $Elements = $this->linesElements($lines);

        if (!in_array('', $lines)
            && is_array($Elements[0] ?? null)
            && isset($Elements[0]['name'])
            && $Elements[0]['name'] === 'p'
        ) {
            unset($Elements[0]['name']);
        }

        return $Elements;
    }

    #
    # AST Convenience
    #

    /**
     * Replace occurrences $regexp with $Elements in $text. Return an array of
     * elements representing the replacement.
     */
    /** @param list<array<string, mixed>> $Elements
     * @return list<array<string, mixed>>
     */
    protected static function pregReplaceElements(string $regexp, array $Elements, string $text): array
    {
        $newElements = [];

        while (preg_match($regexp, $text, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $before = substr($text, 0, $offset);
            $after = substr($text, $offset + strlen($matches[0][0]));

            $newElements[] = ['text' => $before];

            foreach ($Elements as $Element) {
                $newElements[] = $Element;
            }

            $text = $after;
        }

        $newElements[] = ['text' => $text];

        return $newElements;
    }

    /** @param array<string, mixed> $Element
     * @return array<string, mixed>
     */
    protected function sanitiseElement(array $Element): array
    {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt = [
            'a' => 'href',
            'img' => 'src',
        ];

        if (!isset($Element['name'])) {
            unset($Element['attributes']);
            return $Element;
        }

        if (isset($safeUrlNameToAtt[$Element['name']])) {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if (!empty($Element['attributes'])) {
            foreach ($Element['attributes'] as $att => $val) {
                # filter out badly parsed attribute
                if (!preg_match($goodAttribute, $att)) {
                    unset($Element['attributes'][$att]);
                } elseif (self::striAtStart($att, 'on')) {
                    # dump on-event attribute
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    /** @param array<string, mixed> $Element
     * @return array<string, mixed>
     */
    protected function filterUnsafeUrlInAttribute(array $Element, string $attribute): array
    {
        $value = $Element['attributes'][$attribute] ?? null;
        if (!is_string($value)) {
            return $Element;
        }

        foreach ($this->safeLinksWhitelist as $scheme) {
            if (self::striAtStart($value, $scheme)) {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', $value);

        return $Element;
    }

    #
    # Static Methods
    #

    protected static function escape(string $text, bool $allowQuotes = false): string
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart(string $string, string $needle): bool
    {
        $len = strlen($needle);

        if ($len > strlen($string)) {
            return false;
        } else {
            return strtolower(substr($string, 0, $len)) === strtolower($needle);
        }
    }

    /**
     * Singleton instance management
     * @var array<string, static>
     */
    /** @var array<string, static> */
    private static array $instances = [];

    public static function instance(string $name = 'default'): static
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

    public static function removeInstance(string $name = 'default'): void
    {
        unset(self::$instances[$name]);
    }

    public static function clearInstances(): void
    {
        self::$instances = [];
    }

    /**
     * Get all instances of the class
     * @return array<string, static>
     */
    /** @return array<string, static> */
    public static function getInstances(): array
    {
        return self::$instances;
    }
}
