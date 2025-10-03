<?php

declare(strict_types=1);

namespace JTL\Console;

/**
 * @since 5.6.0
 * @see https://github.com/fiasco/symfony-console-style-markdown
 * @license MIT
 */
class MarkdownRenderer
{
    /**
     * @var array<int, string>
     */
    protected array $markdown;

    protected int $pointer = 0;

    /**
     * @param array<int, string> $lines
     */
    public function __construct(array $lines)
    {
        $this->markdown = $lines;
    }

    public static function createFromMarkdown(string $markdown): self
    {
        return new self(\explode(\PHP_EOL, $markdown));
    }

    public function __toString()
    {
        $render = $this->markdown;
        for ($this->pointer = 0; $this->pointer < \count($render); $this->pointer++) {
            $line = &$render[$this->pointer];
            if ($this->isTitle($line)) {
                $line = $this->renderTitle($line);
            } elseif ($this->isCodeBlock($line)) {
                $this->renderCodeBlock($render);
            }
            if ($this->hasBold($line)) {
                $line = $this->renderBold($line);
            }
        }

        return \implode(\PHP_EOL, $render);
    }

    public function isTitle(string $line): bool
    {
        return (bool)\preg_match('/^#+.+$/', $line);
    }

    public function renderTitle(string $line): string
    {
        return '<options=bold;fg=yellow>' . $line . '</>';
    }

    public function isCodeBlock(string $line): bool
    {
        return \str_starts_with($line, '```');
    }

    /**
     * @param array<int, string> $markdown
     */
    public function renderCodeBlock(array &$markdown): void
    {
        do {
            $markdown[$this->pointer] = '<fg=cyan>' . $markdown[$this->pointer] . '</>';
            $this->pointer++;
        } while (isset($markdown[$this->pointer]) && !$this->isCodeBlock($markdown[$this->pointer]));
        $markdown[$this->pointer] = '<fg=cyan>' . $markdown[$this->pointer] . '</>';
    }

    public function hasBold(string $line): bool
    {
        return (bool)\preg_match('/\*\*(.+)\*\*/', $line);
    }

    public function renderBold(string $line): string
    {
        return \preg_replace('/\*\*(.+)\*\*/', '<options=bold>$1</>', $line) ?? $line;
    }
}
