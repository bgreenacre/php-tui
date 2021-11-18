<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use Exception;
use Clue\React\Utf8\Sequencer as Utf8Sequencer;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\Stream\ReadableStreamInterface;

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
        "\x1b[A" => self::KEY_UP,
        "\x1b[B" => self::KEY_DOWN,
        "\x1b[C" => self::KEY_RIGHT,
        "\x1b[D" => self::KEY_LEFT,
        "\x1b[E" => self::KEY_CLEAR,
        "\x1b[F" => self::KEY_END,
        "\x1b[H" => self::KEY_HOME,
        "\x1bOA" => self::KEY_UP,
        "\x1bOB" => self::KEY_DOWN,
        "\x1bOC" => self::KEY_RIGHT,
        "\x1bOD" => self::KEY_LEFT,
        "\x1bOE" => self::KEY_CLEAR,
        "\x1bOF" => self::KEY_END,
        "\x1bOH" => self::KEY_HOME,
        "\n" => self::KEY_ENTER,
        "\t" => self::KEY_TAB,
        "\x1b" => self::KEY_ESCAPE,
        "\x1b\x1b" => [ 'name' => self::KEY_ESCAPE, 'meta' => true ],
        "\b" => self::KEY_BACKSPACE,
        "\x7f" => self::KEY_BACKSPACE,
        "\x1b\x7f" => [ 'name' => self::KEY_BACKSPACE, 'meta' => true],
        "\x1b\b" => [ 'name' => self::KEY_BACKSPACE, 'meta' => true ],
        "\x04" => self::KEY_TERMINATE,
        "\x1b[1~" => self::KEY_HOME,
        "\x1b[2~" => self::KEY_INSERT,
        "\x1b[3~" => self::KEY_DEL,
        "\x1b[4~" => self::KEY_END,
        "\x1b[5~" => self::KEY_PGUP,
        "\x1b[6~" => self::KEY_PGDOWN,
        "\x1b[[5~" => self::KEY_PGUP,
        "\x1b[[6~" => self::KEY_PGDOWN,
        "\x1b[7~" => self::KEY_HOME,
        "\x1b[8~" => self::KEY_END,
        "\x1bOP"  => self::KEY_F1,
        "\x1bOQ"  => self::KEY_F2,
        "\x1bOR"  => self::KEY_F3,
        "\x1bOS"  => self::KEY_F4,
        "\x1b[11~"  => self::KEY_F1,
        "\x1b[12~"  => self::KEY_F2,
        "\x1b[13~"  => self::KEY_F3,
        "\x1b[14~"  => self::KEY_F4,
        "\x1b[[A"  => self::KEY_F1,
        "\x1b[[B"  => self::KEY_F2,
        "\x1b[[C"  => self::KEY_F3,
        "\x1b[[D"  => self::KEY_F4,
        "\x1b[[E"  => self::KEY_F5,
        "\x1b[15~"  => self::KEY_F5,
        "\x1b[17~"  => self::KEY_F6,
        "\x1b[18~"  => self::KEY_F7,
        "\x1b[19~"  => self::KEY_F8,
        "\x1b[20~"  => self::KEY_F9,
        "\x1b[21~"  => self::KEY_F10,
        "\x1b[23~"  => self::KEY_F11,
        "\x1b[24~"  => self::KEY_F12,
    ];

    protected $sequencer;
    protected $close = false;
    protected $buffer = '';

    public function __construct(ReadableStreamInterface $input)
    {
        $this->sequencer = new Utf8Sequencer($input);
        $this->sequencer->on('data', [$this, 'decoder']);

        // process all stream events (forwarded from input stream)
        $this->sequencer->on('end', [$this, 'handleEnd']);
        $this->sequencer->on('error', [$this, 'handleError']);
        $this->sequencer->on('close', [$this, 'close']);
    }

    public function decoder($data): void
    {
        $key = [
            'sequence' => $data,
            'name'     => null,
            'ctrl'     => false,
            'meta'     => false,
            'shift'    => false,
        ];

        if (isset(self::CODES[$data])) {
            if (is_array(self::CODES[$data])) {
                $key = $key + self::CODES[$data];
            } else {
                $key['name'] = self::CODES[$data];
            }
        } else if (strlen($data) === 1 && preg_match('/^[A-Z]$/', $data)) {
            // shift + letter
            $key['shift'] = true;
        } else if (strlen($data) === 1 && $data <= "0x1a") {
            // ctrl + letter
            $key['ctrl'] = true;
            $key['name'] = chr(ord($data[0]) + ord('a') - 1);
        } else if (preg_match('/^' . self::META_KEYCODE . '$/', $data, $parts)) {
            // meta + character key
            $key['name'] = strtolower($parts[1]);
            $key['meta'] = true;
            $key['shift'] = preg_match('/^[A-Z]$/', $parts[1]) !== false;
        } else if (preg_match('/^' . self::FUNC_KEYCODE . '/', $data, $parts)) {
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

        $ch = strlen($data) === 1 ? $data : null;
        $this->emit('keypress', [$ch, $key]);
    }

    public function isMouse($data): bool
    {
        foreach(self::IS_MOUSE as $match) {
            if (preg_match($match, $data)) {
                return true;
            }
        }

        return false;
    }

    /** @internal */
    public function handleEnd(): void
    {
        if (! $this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(Exception $error): void
    {
        $this->emit('error', [$error]);
        $this->close();
    }

    public function close(): void
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
