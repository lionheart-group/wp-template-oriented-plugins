<?php

namespace TonkatsuPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every translation call passes the plugin's text domain as a literal.
 *
 * WordPress.org loads translations by slug, and its tooling only picks up
 * string literals, so a variable, a constant or a stale domain would leave
 * that string untranslated.
 */
class TextDomainTest extends TestCase
{
    private const DOMAIN = 'tonkatsu-seo';

    /**
     * Translation functions and the position of their text domain argument.
     */
    private const FUNCTIONS = [
        '__' => 1, '_e' => 1, 'esc_html__' => 1, 'esc_html_e' => 1, 'esc_attr__' => 1, 'esc_attr_e' => 1,
        '_x' => 2, '_ex' => 2, 'esc_html_x' => 2, 'esc_attr_x' => 2, '_n' => 3, '_nx' => 4,
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/tonkatsu-seo.php'];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $cases = [];
        foreach ($files as $file) {
            $cases[substr($file, strlen($root) + 1)] = [$file];
        }

        return $cases;
    }

    /**
     * @dataProvider sourceFiles
     */
    public function testTranslationCallsUseTheLiteralTextDomain(string $file): void
    {
        foreach ($this->translationCalls($file) as [$function, $line, $domain]) {
            $this->assertSame(
                "'" . self::DOMAIN . "'",
                $domain,
                sprintf('%s() on line %d must use the literal text domain.', $function, $line)
            );
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return list<array{string, int, ?string}> [function, line, domain argument source]
     */
    private function translationCalls(string $file): array
    {
        $tokens = token_get_all((string) file_get_contents($file));
        $calls = [];

        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !isset(self::FUNCTIONS[$token[1]])) {
                continue;
            }

            // Skip method calls and declarations such as `function __()`.
            $previous = $this->neighbour($tokens, $i, -1);
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            $next = $this->neighbour($tokens, $i, 1, $open);
            if ($next !== '(') {
                continue;
            }

            $arguments = $this->arguments($tokens, $open);
            $calls[] = [$token[1], $token[2], $arguments[self::FUNCTIONS[$token[1]]] ?? null];
        }

        return $calls;
    }

    /**
     * The nearest non-whitespace token before (-1) or after (1) $i.
     *
     * @param array<int, mixed> $tokens
     */
    private function neighbour(array $tokens, int $i, int $step, ?int &$index = null): mixed
    {
        for ($j = $i + $step; isset($tokens[$j]); $j += $step) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $index = $j;
            return $tokens[$j];
        }

        return null;
    }

    /**
     * Top-level arguments of the call whose "(" is at $open, as trimmed source.
     *
     * @param array<int, mixed> $tokens
     * @return list<string>
     */
    private function arguments(array $tokens, int $open): array
    {
        $arguments = [];
        $current = '';
        $depth = 0;

        for ($j = $open + 1; isset($tokens[$j]); $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

            if (in_array($text, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                $arguments[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $text;
        }
        $arguments[] = trim($current);

        return $arguments;
    }
}
