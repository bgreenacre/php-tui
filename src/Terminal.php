<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use React\EventLoop\Loop;
use Symfony\Component\Console\Terminal as SymfonyTerminal;

class Terminal {

    protected $originalConfiguration;
    protected $isCanonical = false;
    protected $height = 0;
    protected $width = 0;
    protected $colourSupport;
    protected $terminal;
    protected $isOSXTerm = false;
    protected $isiTerm2 = false;
    protected $isXFCE = false;
    protected $isTerminator = false;
    protected $isLXDE = false;
    protected $isVTE = false;
    protected $isRxvt = false;
    protected $isXterm = false;
    protected $tmux = false;
    protected $tmuxVersion;

    public function __construct()
    {
        if (\function_exists('exec') === false) {
            throw new \RuntimeException('Function "exec" is required for php-terminal');
        }

        $this->getOriginalConfiguration();
        $this->getOriginalCanonicalMode();
        $this->getColourSupport();
        $this->disableCanonicalMode();
        $this->getTerminal();
    }

    private function getOriginalCanonicalMode() : void
    {
        exec('stty -a', $output);
        $this->isCanonical = (strpos(implode("\n", $output), ' icanon') !== false);
    }

    private function getTerminal() : void
    {
        $this->terminal = getenv('TERM') ?? (PHP_OS_FAMILY === 'Windows' ? 'windows_ansi' : 'xterm');
        $this->terminal = mb_strtolower($this->terminal);
        $this->isOSXTerm = getenv('TERM_PROGRAM') === 'Apple_Terminal';
        $this->isiTerm2 = getenv('TERM_PROGRAM') === 'iTerm.app' || !! getenv('ITERM_SESSION_ID');
        $this->isXFCE = mb_stripos('xfce', getenv('COLORTERM'));
        $this->isTerminator = !! getenv('TERMINATOR_UUID');
        $this->isLXDE = false;
        $this->isVTE = !! getenv('VTE_VERSION') || $this->isXFCE || $this->isTerminator || $this->isLXDE;
        $this->isRxvt = mb_stripos('rxvt', getenv('COLORTERM'));
        $this->isXterm = false;
        $this->tmux = !! getenv('TMUX');
    }

    public function getWidth() : int
    {
        return $this->width ?: $this->width = (int) exec('tput cols');
    }

    public function getHeight() : int
    {
        return $this->height ?: $this->height = (int) exec('tput lines');
    }

    public function refreshDimensions() : self
    {
        $this->height = $this->width = null;
        $this->getHeight();
        $this->getWidth();

        return $this;
    }

    public function isInteractive() : bool
    {
        return SymfonyTerminal::hasSttyAvailable();
    }

    public function getColourSupport() : int
    {
        return $this->colourSupport ?: $this->colourSupport = (int) exec('tput colors');
    }

    private function getOriginalConfiguration() : string
    {
        return $this->originalConfiguration ?: $this->originalConfiguration = exec('stty -g');
    }

    public function supportsColour() : bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM');
        }

        return $this->isInteractive();
    }

    /**
     * Disable canonical input (allow each key press for reading, rather than the whole line)
     *
     * @see https://www.gnu.org/software/libc/manual/html_node/Canonical-or-Not.html
     */
    public function disableCanonicalMode() : void
    {
        if ($this->isCanonical) {
            exec('stty -icanon -echo');
            $this->isCanonical = false;
        }
    }

    /**
     * Enable canonical input - read input by line
     *
     * @see https://www.gnu.org/software/libc/manual/html_node/Canonical-or-Not.html
     */
    public function enableCanonicalMode() : void
    {
        if (!$this->isCanonical) {
            exec('stty icanon echo');
            $this->isCanonical = true;
        }
    }

    /**
     * Is canonical mode enabled or not
     */
    public function isCanonicalMode() : bool
    {
        return $this->isCanonical;
    }

    /**
     * Restore the original terminal configuration
     */
    public function restoreOriginalConfiguration() : void
    {
        exec('stty ' . $this->getOriginalConfiguration());
    }

    /**
     * Restore the original terminal configuration on shutdown.
     */
    public function __destruct()
    {
        $this->restoreOriginalConfiguration();
    }
}
