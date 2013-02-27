<?php

/**
 * Transforms a command into a URL to redirect to.
 */
class Parser {

    /**
     * The number of commands executed. A command can contain one or more
     * subcommands, each of which can contain subcommands of their own.
     * We limit the total number of commands executed in a command request.
     */
    protected $commandCount;

    /**
     * The maximum number of commands that can be triggered in a request.
     */
    protected $maxCommandCount = 10;

    /**
     * Returns a URL corresponding to the given command, performing any
     * required initialization.
     *
     * @param string $commandString  the command plus arguments, e.g., gim porsche
     * @param string $defaultCommand  command to use if the first word is not
     *                                a recognized command
     */
    public function parse($commandString, $defaultCommand) {
        // Great idea from Michele Trimarchi: if the user types in something that looks like
        // a URL, just go to it. [Jon Aquino 2005-06-23]
        if ($this->looksLikeUrl($commandString)) {
            return $this->prefixWithHttp($commandString);
        }
        $this->commandCount = 0;
        $this->parseProper($commandString, $defaultCommand);
    }

    /**
     * Returns a URL corresponding to the given command or subcommand.
     *
     * @param string $commandString  the command plus arguments, e.g., gim porsche
     * @param string $defaultCommand  command to use if the first word is not
     *                                a recognized command
     */
    protected function parseProper($commandString, $defaultCommand) {
        $parts = preg_split('/\s+/', $commandString);
        $name = $parts[0];
        $args = implode(' ', array_slice($parts, 1));
        $commandStore = new CommandStore();
        $command = $commandStore->findCommand($name);
        if (!$command && !$defaultCommand) {
            throw new Exception('Could not find command ' . $name);
        }
        if (!$command) {
            $command = $commandStore->findCommand($defaultCommand);
            $args = $commandString;
        }
        $url = $this->applyArgs($command, $args);
        $url = $this->applySubcommands($url);
        return $url;
    }

    /**
     * Applies the arguments to the Command's URL.
     *
     * @param Command $command  the command to run
     * @param string $args  the arguments (string of switches) to pass to the command
     * @return string  the Command's URL with the arguments substituted in
     */
    protected function applyArgs($command, $args) {
        $switches = $command->getSwitches();
        $parts = preg_split('/\s+/', $args);
        $currentSwitch = '%s';
        foreach ($parts as $part) {
            if (array_key_exists($part, $switches)) {
                $currentSwitch = $part;
                $switches[$currentSwitch] = null;
            } else {
                $switches[$currentSwitch] = trim($switches[$currentSwitch] . ' ' . $part);
            }
        }
        return $command->applySwitches($switches);
    }

    /**
     * Expands any subcommands in the URL. For example, in http://foo.com?{random 100},
     * {random 100} will be expanded.
     *
     * @param string $url  a URL which may contain subcommands
     */
    protected function applySubcommands($url) {
        $pattern = '/\{(.*?)\}/';
        while (preg_match($pattern, $url)) {
            $url = preg_replace_callback($pattern, array($this, 'parseSubcommand'), $url, 1);
        }
        return $url;
    }

    /**
     * Returns a URL corresponding to the given  subcommand.
     *
     * @param array $matches  regular-expression matches from #applySubcommands
     */
    protected function parseSubcommand($matches) {
        $subcommandString = $matches[1];
        $this->commandCount += 1;
        if ($this->commandCount > $this->maxCommandCount) {
            throw new Exception('Too many subcommands: ' . $subcommandString);
        }
        return $this->parseProper($subcommandString, null);
    }

}