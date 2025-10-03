<?php

declare(strict_types=1);

namespace JTL\Console;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

/**
 * Class ConsoleIO
 * @package JTL\Console
 */
class ConsoleIO extends SymfonyStyle
{
    public const MAX_LINE_LENGTH = 120;

    protected int $lastMessagesLength = 0;

    protected bool $overwrite = true;

    private ?SymfonyQuestionHelper $questionHelper = null;

    private ?ProgressBar $progressBar = null;

    private int $lineLength;

    private BufferedOutput $bufferedOutput;

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly ?HelperSet $helperSet = null
    ) {
        $formatter = clone $output->getFormatter();

        $this->bufferedOutput = new BufferedOutput($output->getVerbosity(), false, $formatter);
        $this->lineLength     = $this->getTerminalWidth() - (int)(\DIRECTORY_SEPARATOR === '\\');

        parent::__construct($input, $output);
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getHelperSet(): ?HelperSet
    {
        return $this->helperSet;
    }

    /**
     * @inheritdoc
     */
    public function isQuiet(): bool
    {
        return $this->getVerbosity() >= OutputInterface::VERBOSITY_QUIET;
    }

    public function isNormal(): bool
    {
        return $this->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL;
    }

    /**
     * @inheritdoc
     */
    public function isVerbose(): bool
    {
        return $this->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * @inheritdoc
     */
    public function isVeryVerbose(): bool
    {
        return $this->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * @inheritdoc
     */
    public function isDebug(): bool
    {
        return $this->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }

    public function overwrite(string $message): self
    {
        $lines = \explode("\n", $message);
        if ($this->lastMessagesLength !== 0) {
            foreach ($lines as $i => $line) {
                $len = Helper::width(Helper::removeDecoration($this->bufferedOutput->getFormatter(), $line));
                if ($this->lastMessagesLength > $len) {
                    $lines[$i] = $line . \str_repeat("\x20", $this->lastMessagesLength - $len);
                }
            }
        }
        if ($this->overwrite) {
            $this->write("\x0D");
        }

        $this->lastMessagesLength = 0;
        foreach ($lines as $line) {
            $len = Helper::width(Helper::removeDecoration($this->bufferedOutput->getFormatter(), $line));
            if ($len > $this->lineLength) {
                $line = \substr($line, 0, $this->lineLength);
            }

            $this->write($line);

            if ($len > $this->lastMessagesLength) {
                $this->lastMessagesLength = $len;
            }
        }

        return $this;
    }

    public function progress(callable $process, ?string $format = null, bool $clearMessage = true): self
    {
        $progress = parent::createProgressBar();
        if ($format === null) {
            $format = '%percent:3s%% [%bar%] %current% of %max%';
        }
        $progress->setFormat($format);
        $progress->setMessage('');
        $progress->setEmptyBarCharacter(' ');
        $progress->setBarCharacter('<comment>=</comment>');

        $lastPercent = 0;
        $lastMessage = null;
        $lastRedraw  = \microtime(true);

        $callback = static function (
            $total,
            $current,
            $message = ''
        ) use (
            &$progress,
            &$lastRedraw,
            &$lastPercent,
            &$lastMessage
        ): void {
            if ($progress->getMaxSteps() === 0) {
                $progress->start($total);
            }

            // update frequence 250ms or on percent value changed
            $off = (\microtime(true) - $lastRedraw) * 1000;
            if ($off > 250 || $lastPercent !== $current || $lastMessage !== $message) {
                $progress->setMessage($message);
                if ($current > $lastPercent) {
                    $progress->setProgress($current);
                }
                $progress->display();
                $lastRedraw  = \microtime(true);
                $lastPercent = $current;
                $lastMessage = $message;
            }
        };
        $process($callback);
        if ($clearMessage) {
            $progress->setMessage('');
        }
        $progress->finish();
        $this->writeln('');

        return $this;
    }

    public function setStep(int $current, int $limit, string $step): void
    {
        $this->setLabel('Step ' . $current . ' of ' . $limit, $step);
    }

    public function setLabel(string $title, ?string $sub = null): void
    {
        $this->writeln('');
        $this->writeln('<comment>' . $title . '</comment> ' . ($sub !== null ? '<info>' . $sub . '</info>' : ''));
        $this->writeln('');
    }

    public function isInteractive(): bool
    {
        return $this->getInput()->hasOption('no-interaction') === false;
    }

    /**
     * @inheritdoc
     * @param string|string[] $messages
     */
    public function block(
        string|array $messages,
        ?string $type = null,
        ?string $style = null,
        string $prefix = ' ',
        bool $padding = false,
        bool $escape = false,
    ): void {
        $this->autoPrependBlock();

        $messages = \is_array($messages) ? \array_values($messages) : [$messages];
        $lines    = [];
        // add type
        if ($type !== null) {
            $messages[0] = \sprintf('[%s] %s', $type, $messages[0]);
        }

        // wrap and add newlines for each element
        foreach ($messages as $key => $message) {
            $message = OutputFormatter::escape($message);
            $lines   = \array_merge(
                $lines,
                \explode(
                    \PHP_EOL,
                    \wordwrap($message, $this->lineLength - Helper::width($prefix), \PHP_EOL, true)
                )
            );

            if (\count($messages) > 1 && $key < \count($messages) - 1) {
                $lines[] = '';
            }
        }

        if ($padding && $this->isDecorated()) {
            \array_unshift($lines, '');
            $lines[] = '';
        }
        $length = 0;
        $widths = \array_map(
            fn(string $line): int => Helper::width(Helper::removeDecoration($this->getFormatter(), $line)),
            $lines
        );
        if (\count($widths) > 0) {
            $length = \max($widths);
        }
        $length += \strlen($prefix) * 2;
        foreach ($lines as &$line) {
            $line = \sprintf('%s%s', $prefix, $line);
            $line .= \str_repeat(' ', $length - Helper::width(Helper::removeDecoration($this->getFormatter(), $line)));

            if ($style) {
                $line = \sprintf('<%s>%s</>', $style, $line);
            }
        }
        unset($line);

        $this->writeln($lines);
        $this->newLine();
    }

    /**
     * @inheritdoc
     */
    public function title(string $message): void
    {
        $this->autoPrependBlock();
        $this->writeln(
            [
                \sprintf('<comment>%s</comment>', $message),
                \sprintf(
                    '<comment>%s</comment>',
                    \str_repeat('=', Helper::width(Helper::removeDecoration($this->getFormatter(), $message)))
                ),
            ]
        );
        $this->newLine();
    }

    /**
     * @inheritdoc
     */
    public function section(string $message): void
    {
        $this->autoPrependBlock();
        $this->writeln(
            [
                \sprintf('<comment>%s</comment>', $message),
                \sprintf(
                    '<comment>%s</comment>',
                    \str_repeat('-', Helper::width(Helper::removeDecoration($this->getFormatter(), $message)))
                ),
            ]
        );
        $this->newLine();
    }

    /**
     * @param string[] $elements
     */
    public function listing(array $elements): void
    {
        $this->autoPrependText();
        $elements = \array_map(static fn($element): string => \sprintf(' * %s', $element), $elements);
        $this->writeln($elements);
        $this->newLine();
    }

    /**
     * @param string|string[] $message
     */
    public function text(string|array $message): void
    {
        $this->autoPrependText();
        $messages = \is_array($message) ? \array_values($message) : [$message];
        foreach ($messages as $msg) {
            $this->writeln(\sprintf(' %s', $msg));
        }
    }

    /**
     * @param string|string[] $message
     */
    public function comment(string|array $message): void
    {
        $this->autoPrependText();
        $messages = \is_array($message) ? \array_values($message) : [$message];
        foreach ($messages as $msg) {
            $this->writeln(\sprintf('<fg=white;bg=magenta>%s</>', $msg));
        }
    }

    /**
     * @param string[]|string $message
     */
    public function verbose(array|string $message): void
    {
        $this->block($message, null, 'fg=black;bg=cyan', ' ', true);
    }

    /**
     * @param string[]|string $message
     */
    public function success(string|array $message): void
    {
        $this->block($message, null, 'fg=black;bg=green', ' ', true);
    }

    /**
     * @param string[]|string $message
     */
    public function error(string|array $message): void
    {
        $this->block($message, null, 'fg=white;bg=red', ' ', true);
    }

    /**
     * @param string[]|string $message
     */
    public function warning(string|array $message): void
    {
        $this->block($message, null, 'fg=black;bg=yellow', ' ', true);
    }

    /**
     * @param string[]|string $message
     */
    public function note(string|array $message): void
    {
        $this->block($message, null, 'fg=white;bg=blue', ' ', true);
    }

    /**
     * @param string[]|string $message
     */
    public function caution(string|array $message): void
    {
        $this->block($message, null, 'fg=white;bg=red', ' ', true);
    }

    /**
     * @param string[]                 $headers
     * @param list<array<int, string>> $rows
     * @param array<mixed>             $options
     */
    public function table(array $headers, array $rows, array $options = []): void
    {
        $options = \array_merge(['style' => 'symfony-style-guide'], $options);
        $headers = \array_map(static fn($value): string => \sprintf('<info>%s</info>', $value), $headers);

        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->setStyle($options['style']);
        if (isset($options['columnWidth']) && \count($options['columnWidth']) > 0) {
            $table->setColumnWidths($options['columnWidth']);
        }
        $table->render();
        $this->newLine();
    }

    /**
     * @inheritdoc
     */
    public function ask(
        string $question,
        string|bool|int|float|null $default = null,
        ?callable $validator = null
    ): mixed {
        $instance = new Question($question, $default);
        $instance->setValidator($validator);

        return $this->askQuestion($instance);
    }

    /**
     * @inheritdoc
     */
    public function askHidden(string $question, ?callable $validator = null): mixed
    {
        $instance = new Question($question);

        $instance->setHidden(true);
        $instance->setValidator($validator);

        return $this->askQuestion($instance);
    }

    /**
     * @inheritdoc
     */
    public function confirm(string $question, bool $default = true, string $regex = '/^y/i'): bool
    {
        return $this->askQuestion(new ConfirmationQuestion($question, $default, $regex));
    }

    /**
     * @inheritdoc
     * @param array<mixed> $choices
     */
    public function choice(string $question, array $choices, mixed $default = null, bool $multiSelect = false): mixed
    {
        if ($default !== null) {
            $values  = \array_flip($choices);
            $default = $values[$default];
        }

        return $this->askQuestion(new ChoiceQuestion($question, $choices, $default));
    }

    /**
     * @inheritdoc
     */
    public function progressStart(int $max = 0): void
    {
        $this->progressBar = $this->createProgressBar($max);
        $this->progressBar->start();
    }

    /**
     * @inheritdoc
     */
    public function progressAdvance(int $step = 1): void
    {
        $this->getProgressBar()->advance($step);
    }

    /**
     * @inheritdoc
     */
    public function progressFinish(): void
    {
        $this->getProgressBar()->finish();
        $this->newLine(2);
        $this->progressBar = null;
    }

    public function createProgressBar(int $max = 0): ProgressBar
    {
        $progressBar = parent::createProgressBar($max);

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        }

        return $progressBar;
    }

    public function askQuestion(Question $question): mixed
    {
        if ($this->input->isInteractive()) {
            $this->autoPrependBlock();
        }

        if (!$this->questionHelper) {
            $this->questionHelper = new SymfonyQuestionHelper();
        }

        $answer = $this->questionHelper->ask($this->input, $this, $question);

        if ($this->input->isInteractive()) {
            $this->newLine();
        }

        return $answer;
    }

    /**
     * @inheritdoc
     * @phpstan-param string|iterable<string> $messages
     */
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        parent::writeln($messages, $type);
        $this->bufferedOutput->writeln($this->reduceBuffer($messages), $type);
    }

    /**
     * @inheritdoc
     * @phpstan-param string|iterable<string> $messages
     */
    public function write(string|iterable $messages, bool $newline = false, int $type = self::OUTPUT_NORMAL): void
    {
        parent::write($messages, $newline, $type);
        $this->bufferedOutput->write($this->reduceBuffer($messages), $newline, $type);
    }

    /**
     * @inheritdoc
     */
    public function newLine(int $count = 1): void
    {
        parent::newLine($count);
        $this->bufferedOutput->write(\str_repeat("\n", $count));
    }

    private function getProgressBar(): ProgressBar
    {
        if (!$this->progressBar) {
            throw new RuntimeException('The ProgressBar is not started.');
        }

        return $this->progressBar;
    }

    private function getTerminalWidth(): int
    {
        $terminal   = new Terminal();
        $dimensions = [$terminal->getWidth(), $terminal->getHeight()];

        return $dimensions[0] ?: self::MAX_LINE_LENGTH;
    }

    private function autoPrependBlock(): void
    {
        $chars = \substr(\str_replace(\PHP_EOL, "\n", $this->bufferedOutput->fetch()), -2);

        if (!isset($chars[0])) {
            $this->newLine(); // empty history, so we should start with a new line.
            return;
        }
        // Prepend new line for each non LF chars (This means no blank line was output before)
        $this->newLine(2 - \substr_count($chars, "\n"));
    }

    private function autoPrependText(): void
    {
        $fetched = $this->bufferedOutput->fetch();
        // Prepend new line if last char isn't EOL:
        if (!\str_ends_with($fetched, "\n")) {
            $this->newLine();
        }
    }

    /**
     * @phpstan-param string|iterable<string> $messages
     * @return array<mixed>
     */
    private function reduceBuffer(string|iterable $messages): array
    {
        // We need to know if the two last chars are PHP_EOL
        // Preserve the last 4 chars inserted (PHP_EOL on windows is two chars) in the history buffer
        return \array_map(
            static fn(string $value): string => \substr($value, -4),
            \array_merge([$this->bufferedOutput->fetch()], (array)$messages)
        );
    }
}
