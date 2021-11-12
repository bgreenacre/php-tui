<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use Clue\React\Utf8\Sequencer as Utf8Sequencer;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\Stream\ReadableStreamInterface;
use Symfony\Component\String\UnicodeString;

class Keys extends EventEmitter
{
    const IS_MOUSE = [
        '/\x1b\[M/',
        '/\x1b\[M([\x00\u0020-\uffff]{3})/',
        '/\x1b\[(\d+;\d+;\d+)M/',
        '/\x1b\[<(\d+;\d+;\d+)([mM])/',
        '/\x1b\[<(\d+;\d+;\d+;\d+)&w/',
        '/\x1b\[24([0135])~\[(\d+),(\d+)\]\r/',
        '/\x1b\[(O|I)/',
    ];

    const META_KEYCODE = '(?:\x1b)([a-zA-Z0-9])';
    const FUNC_KEYCODE = '(?:\x1b+)(O|N|\\[|\\[\\[)(?:(\\d+)(?:;(\\d+))?([~^$])|(?:M([@ #!a`])(.)(.))|(?:1;)?(\\d+)?([a-zA-Z]))';
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
        "[A" => self::KEY_UP,
        "[B" => self::KEY_DOWN,
        "[C" => self::KEY_RIGHT,
        "[D" => self::KEY_LEFT,
        "[E" => self::KEY_CLEAR,
        "[F" => self::KEY_END,
        "[H" => self::KEY_HOME,
        "OA" => self::KEY_UP,
        "OB" => self::KEY_DOWN,
        "OC" => self::KEY_RIGHT,
        "OD" => self::KEY_LEFT,
        "OE" => self::KEY_CLEAR,
        "OF" => self::KEY_END,
        "OH" => self::KEY_HOME,
        "\n" => self::KEY_ENTER,
        "\t" => self::KEY_TAB,
        "\x1b" => self::KEY_ESCAPE,
        "\x1b\x1b" => self::KEY_ESCAPE,
        "\x7f" => self::KEY_BACKSPACE,
        "\x04" => self::KEY_TERMINATE,
        "[1~" => self::KEY_HOME,
        "[2~" => self::KEY_INSERT,
        "[3~" => self::KEY_DEL,
        "[4~" => self::KEY_END,
        "[5~" => self::KEY_PGUP,
        "[6~" => self::KEY_PGDOWN,
        "[[5~" => self::KEY_PGUP,
        "[[6~" => self::KEY_PGDOWN,
        "[7~" => self::KEY_HOME,
        "[8~" => self::KEY_END,
        "OP"  => self::KEY_F1,
        "OQ"  => self::KEY_F2,
        "OR"  => self::KEY_F3,
        "OS"  => self::KEY_F4,
        "[11~"  => self::KEY_F1,
        "[12~"  => self::KEY_F2,
        "[13~"  => self::KEY_F3,
        "[14~"  => self::KEY_F4,
        "[[A"  => self::KEY_F1,
        "[[B"  => self::KEY_F2,
        "[[C"  => self::KEY_F3,
        "[[D"  => self::KEY_F4,
        "[[E"  => self::KEY_F5,
        "[15~"  => self::KEY_F5,
        "[17~"  => self::KEY_F6,
        "[18~"  => self::KEY_F7,
        "[19~"  => self::KEY_F8,
        "[20~"  => self::KEY_F9,
        "[21~"  => self::KEY_F10,
        "[23~"  => self::KEY_F11,
        "[24~"  => self::KEY_F12,
    ];

    protected $input;
    protected $parser;
    protected $sequencer;
    protected $close = false;
    protected $buffer = [];

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', [$this, 'handleData']);
        $this->input->on('end', [$this, 'handleEnd']);
        $this->input->on('error', [$this, 'handleError']);
        $this->input->on('close', [$this, 'close']);

        // $this->sequencer = new Utf8Sequencer($this);
        // $this->sequencer->on('data', [$this, 'fallback']);

        // // process all stream events (forwarded from input stream)
        // $this->sequencer->on('end', [$this, 'handleEnd']);
        // $this->sequencer->on('error', [$this, 'handleError']);
        // $this->sequencer->on('close', [$this, 'close']);
    }

    public function handleData($data) : void
    {
        if ($this->isMouse($data)) {
            return;
        }

        $this->buffer = [];
        $wholeThing = new UnicodeString($data);

        foreach ($wholeThing->match('/' . self::FUNC_KEYCODE . '|' . self::META_KEYCODE . '|' . "\x1b/", PREG_OFFSET_CAPTURE) as $match) {
            if ($match[0]) {
                $this->buffer = [ ...$this->buffer, ...$wholeThing->slice(0, $match[1])->split('//', null, PREG_SPLIT_NO_EMPTY) ];
                $this->buffer[] = new UnicodeString($match[0]);
                $wholeThing = $wholeThing->slice($match[1] + (new UnicodeString($match[0]))->length());

                if ($wholeThing->length() === 0) {
                    break;
                }
            }
        }

        $this->buffer = [ ...$this->buffer, ...$wholeThing->split('//', null, PREG_SPLIT_NO_EMPTY)];

        foreach ($this->buffer as $str) {
            $key = [
                'sequence' => $str,
                'name' => null,
                'ctrl' => false,
                'meta' => false,
                'shift' => false,
            ];

            if ($str->equalsTo("\r")) {
                // carriage return
                $key['name'] = 'return';
            }
            else if ($str->equalsTo("\n")) {
                // enter
                $key['name'] = 'enter';
            }
            else if ($str->equalsTo("\t")) {
                // tab
                $key['name'] = 'tab';
            }
            else if ($str->equalsTo("\b") || $str->equalsTo("\x7f") || $str->equalsTo("\x1b\x7f") || $str->equalsTo("\x1b\b")) {
                // backspace or ctrl+h
                $key['name'] = 'backspace';
                $key['meta'] = $str->indexOf("\x1b") === 0;
            }
            else if ($str->equalsTo("\x1b") || $str->equalsTo("\x1b\x1b")) {
                // escape key
                $key['name'] = 'escape';
                $key['meta'] = $str->length() === 2;
            }
            else if ($str->equalsTo(' ') || $str->equalsTo("\x1b ")) {
                $key['name'] = 'space';
                $key['meta'] = $str->length() === 2;
            }
            else if ($str->length() === 1 && $str->toString() <= "\x1a") {
                // ctrl+letter
                $key['name'] = chr($str->codePointsAt(0)[0] + mb_ord('a') - 1);
                $key['ctrl'] = true;
            }
            else if ($str->length() === 1 && $str->toString() >= 'a' && $str->toString() <= 'z') {
                // lowercase letter
                $key['name'] = $str->toString();
            }
            else if ($str->length() === 1 && $str->toString() >= 'A' && $str->toString() <= 'Z') {
                // shift+letter
                $key['name'] = $str->lower();
                $key['shift'] = true;
            }
            else {
                $parts = $str->match('/^' . self::META_KEYCODE . '$/');

                if ( ! empty($parts)) {
                    $part = new UnicodeString($parts[1]);

                    // meta+character key
                    $key['name'] = $part->lower();
                    $key['meta'] = true;
                    $key['shift'] = ! empty($part->match('/^[A-Z]$/'));
                }

                $parts = $str->match('/^' . self::FUNC_KEYCODE . '/');

                if ( ! empty($parts)) {
                    $code = $parts[1]
                        . $parts[2]
                        . $parts[4]
                        . $parts[9];

                    $modifier = ($parts[3] || $parts[8] || 1) - 1;

                    $key['ctrl'] = !!($modifier & 4);
                    $key['meta'] = !!($modifier & 10);
                    $key['shift'] = !!($modifier & 1);
                    $key['code'] = $code;
                    $key['name'] = isset(self::CODES[$code]) ? self::CODES[$code] : null;
                }
            }

            $ch = ($str->length() === 1) ? $str->toString() : null;

            if ($key['name'] || $ch) {
                $this->emit('keypress', [$ch, $key]);
            }
        }
    }

    public function isMouse($data) : bool
    {
        foreach(self::IS_MOUSE as $match) {
            if ( ! empty((new UnicodeString($data))->match($match))) {
                return true;
            }
        }

        return false;
    }

    /** @internal */
    public function handleEnd() : void
    {
        if (! $this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $error) : void
    {
        $this->emit('error', [$error]);
        $this->close();
    }

    public function close() : void
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
