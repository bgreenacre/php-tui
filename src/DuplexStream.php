<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use Evenement\EventEmitter;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableResourceStream;

class DuplexStream extends EventEmitter implements DuplexStreamInterface {

    protected $cursor;
    protected $input;
    protected $output;
    protected $ending = false;
    protected $closed = false;
    protected $terminal;
    protected $loop;

    public function __construct(ReadableStreamInterface $input = null, WritableStreamInterface $output = null, LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();

        $this->input  = $input ?? new ReadableResourceStream(STDIN, $this->loop);
        $this->output = $output ?? new WritableResourceStream(STDOUT, $this->loop);

        // handle all output events
        $this->output->on('error', [$this, 'handleError']);
        $this->output->on('close', [$this, 'handleCloseOutput']);
    }

    public function getInputStream() : ReadableStreamInterface
    {
        return $this->input;
    }

    public function getOutputStream() : WritableStreamInterface
    {
        return $this->output;
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function isReadable()
    {
        return $this->input->isReadable();
    }

    public function isWritable()
    {
        return $this->output->isWritable();
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function write($data)
    {
        // return false if already ended, return true if writing empty string
        if ($this->ending || $data === '') {
            return !$this->ending;
        }

    }

    public function end($data = null)
    {
        if ($this->ending) {
            return;
        }

        if ($data !== null) {
            $this->write($data);
        }

        $this->ending = true;

        $this->input->close();
        $this->output->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->ending = true;
        $this->closed = true;

        $this->input->close();
        $this->output->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleCloseOutput()
    {
        if (!$this->input->isReadable()) {
            $this->close();
        }
    }
}
