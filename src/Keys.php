<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use Clue\React\Term\ControlCodeParser;
use Clue\React\Utf8\Sequencer as Utf8Sequencer;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\Stream\ReadableStreamInterface;
use Symfony\Component\String\UnicodeString;

class Keys extends EventEmitter
{
    const KEY_UP = 'up';
    const KEY_DOWN = 'down';
    const KEY_LEFT = 'left';
    const KEY_RIGHT = 'right';
    const KEY_CLEAR = 'clear';
    const KEY_ENTER = 'enter';
    const KEY_ESCAPE = 'escape';
    const KEY_TAB = 'tab';
    const KEY_BACKSPACE = 'backspace';
    const KEY_TERMINATE = 'terminate';
    const KEY_END = 'end';
    const KEY_HOME = 'home';
    const KEY_DEL = 'del';
    const KEY_INSERT = 'insert';
    const KEY_PGUP = 'pageup';
    const KEY_PGDOWN = 'pagedown';
    const KEY_F1 = 'f1';
    const KEY_F2 = 'f2';
    const KEY_F3 = 'f3';
    const KEY_F4 = 'f4';
    const KEY_F5 = 'f5';
    const KEY_F6 = 'f6';
    const KEY_F7 = 'f7';
    const KEY_F8 = 'f8';
    const KEY_F9 = 'f9';
    const KEY_F10 = 'f10';
    const KEY_F11 = 'f11';
    const KEY_F12 = 'f12';

    const CODES = [
        "\033[A" => self::KEY_UP,
        "\033[B" => self::KEY_DOWN,
        "\033[C" => self::KEY_RIGHT,
        "\033[D" => self::KEY_LEFT,
        "\033[E" => self::KEY_CLEAR,
        "\033[F" => self::KEY_END,
        "\033[G" => self::KEY_HOME,
        "\033OA" => self::KEY_UP,
        "\033OB" => self::KEY_DOWN,
        "\033OC" => self::KEY_RIGHT,
        "\033OD" => self::KEY_LEFT,
        "\033OE" => self::KEY_CLEAR,
        "\033OF" => self::KEY_END,
        "\033OG" => self::KEY_HOME,
        "\n" => self::KEY_ENTER,
        "\t" => self::KEY_TAB,
        "\x1b" => self::KEY_ESCAPE,
        "\x1b\x1b" => self::KEY_ESCAPE,
        "\x7f" => self::KEY_BACKSPACE,
        "\x04" => self::KEY_TERMINATE,
        "\033[1~" => self::KEY_HOME,
        "\033[2~" => self::KEY_INSERT,
        "\033[3~" => self::KEY_DEL,
        "\033[4~" => self::KEY_END,
        "\033[5~" => self::KEY_PGUP,
        "\033[6~" => self::KEY_PGDOWN,
        "\033[[5~" => self::KEY_PGUP,
        "\033[[6~" => self::KEY_PGDOWN,
        "\033[7~" => self::KEY_HOME,
        "\033[8~" => self::KEY_END,
        "\033[OP"  => self::KEY_F1,
        "\033[OQ"  => self::KEY_F2,
        "\033[OR"  => self::KEY_F3,
        "\033[OS"  => self::KEY_F4,
        "\033[11~"  => self::KEY_F1,
        "\033[12~"  => self::KEY_F2,
        "\033[13~"  => self::KEY_F3,
        "\033[14~"  => self::KEY_F4,
        "\033[[A"  => self::KEY_F1,
        "\033[[B"  => self::KEY_F2,
        "\033[[C"  => self::KEY_F3,
        "\033[[D"  => self::KEY_F4,
        "\033[[E"  => self::KEY_F5,
        "\033[15~"  => self::KEY_F5,
        "\033[17~"  => self::KEY_F6,
        "\033[18~"  => self::KEY_F7,
        "\033[19~"  => self::KEY_F8,
        "\033[20~"  => self::KEY_F9,
        "\033[21~"  => self::KEY_F10,
        "\033[23~"  => self::KEY_F11,
        "\033[24~"  => self::KEY_F12,
    ];

    protected $input;
    protected $baseEmitter;
    protected $parser;
    protected $sequencer;
    protected $close = false;

    public function __construct(ReadableStreamInterface $input, EventEmitterInterface $baseEmitter)
    {
        $this->input = $input;
        $this->baseEmitter = $baseEmitter;

        $parser = new ControlCodeParser($this->input);

        $parser->on('csi', [$this, 'decoder']);
        $parser->on('osc', [$this, 'decoder']);
        $parser->on('c1', [$this, 'decoder']);
        $parser->on('c0', [$this, 'decoder']);

        $this->sequencer = new Utf8Sequencer($parser);
        $this->sequencer->on('data', [$this, 'fallback']);

        // process all stream events (forwarded from input stream)
        $this->sequencer->on('end', [$this, 'handleEnd']);
        $this->sequencer->on('error', [$this, 'handleError']);
        $this->sequencer->on('close', [$this, 'close']);
    }

    public function decoder($code)
    {
        if ($code === "\r") {
            $code = "\n";
        }

        $event = isset(self::CODES[$code]) ? self::CODES[$code] : null;

        if ($this->baseEmitter && $this->baseEmitter->listeners('keypress')) {
            $this->baseEmitter->emit('keypress', [$code, $event]);
        }

        $this->emit('keypress', [$code, $event]);
    }

    public function fallback($data)
    {
        $str = new UnicodeString($data);
        $buffer = '';

        foreach ($str->split('//', null, PREG_SPLIT_NO_EMPTY) as $char)
        {
            $strChar = $char->toString();

            if ($this->baseEmitter && $this->baseEmitter->listeners('keypress'))
            {
                $this->baseEmitter->emit('keypress', [$char, null]);
            }
            else if ($this->listeners('keypress'))
            {
                $this->emit('keypress', [$char, null]);
            }
            else
            {
                $buffer .= $char;
            }
        }

        if ($buffer) {
            $this->emit('data', [$buffer]);
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if (! $this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', [$error]);
        $this->close();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }
}
