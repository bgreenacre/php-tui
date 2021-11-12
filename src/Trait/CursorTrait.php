<?php

declare(strict_types=1);

namespace PhpTui\PhpTui\Trait;

use function React\Promise\Stream\buffer;

trait CursorTrait {

    protected static $row = 0;
    protected static $col = 0;

    public function moveUp(int $lines = 1): self
    {
        $this->output->write(sprintf("\x1b[%dA", $lines));

        self::$row = self::$row - $lines;

        return $this;
    }

    public function moveDown(int $lines = 1): self
    {
        $this->output->write(sprintf("\x1b[%dB", $lines));

        self::$row = self::$row + $lines;

        return $this;
    }

    public function moveRight(int $columns = 1): self
    {
        $this->output->write(sprintf("\x1b[%dC", $columns));

        self::$col = self::$col + $columns;

        return $this;
    }

    public function moveLeft(int $columns = 1): self
    {
        $this->output->write(sprintf("\x1b[%dD", $columns));

        self::$col = self::$col - $columns;

        return $this;
    }

    public function moveToColumn(int $column): self
    {
        $this->output->write(sprintf("\x1b[%dG", $column));

        self::$col = $column;

        return $this;
    }

    public function moveToPosition(int $column, int $row): self
    {
        $this->output->write(sprintf("\x1b[%d;%dH", $row + 1, $column));

        self::$col = $column;
        self::$row = $row + 1;

        return $this;
    }

    public function savePosition(): self
    {
        $this->output->write("\x1b7");

        return $this;
    }

    public function restorePosition(): self
    {
        $this->output->write("\x1b8");

        return $this;
    }

    public function hide(): self
    {
        $this->output->write("\x1b[?25l");

        return $this;
    }

    public function show(): self
    {
        $this->output->write("\x1b[?25h\x1b[?0c");

        return $this;
    }

    /**
     * Clears all the output from the current line.
     */
    public function clearLine(): self
    {
        $this->output->write("\x1b[2K");

        return $this;
    }

    /**
     * Clears all the output from the current line after the current position.
     */
    public function clearLineAfter(): self
    {
        $this->output->write("\x1b[K");

        return $this;
    }

    /**
     * Clears all the output from the cursors' current position to the end of the screen.
     */
    public function clearOutput(): self
    {
        $this->output->write("\x1b[0J");

        return $this;
    }

    /**
     * Clears the entire screen.
     */
    public function clearScreen(): self
    {
        $this->output->write("\x1b[2J");

        return $this;
    }

    public function getCurrentPosition() : array
    {
        return [self::$col, self::$row];
    }
}
