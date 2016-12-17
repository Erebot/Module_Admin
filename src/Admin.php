<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Erebot\Module;

/**
 * \brief
 *      A module that provides several commands
 *      intended for administrators.
 */
class Admin extends \Erebot\Module\Base implements \Erebot\Interfaces\HelpEnabled
{
    /// A list of handlers registered by this module.
    protected $handlers;

    /// A list of triggers registered by this module.
    protected $triggers;

    /// List of administrators.
    protected $admins;

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot::Module::Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->connection->getModule(
                '\\Erebot\\Module\\TriggerRegistry'
            );
            $matchAny  = \Erebot\Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                foreach ($this->triggers as $name => $value) {
                    $this->connection->removeEventHandler(
                        $this->handlers[$name]
                    );
                    $registry->freeTriggers($value, $matchAny);
                }
            }

            $this->handlers = $this->triggers = array();

            $triggers = array(
                            'part'      => 'handlePart',
                            'quit'      => 'handleQuit',
                            'voice'     => 'handleVoice',
                            'devoice'   => 'handleDeVoice',
                            'halfop'    => 'handleHalfOp',
                            'dehalfop'  => 'handleDeHalfOp',
                            'op'        => 'handleOp',
                            'deop'      => 'handleDeOp',
                            'protect'   => 'handleProtect',
                            'deprotect' => 'handleDeProtect',
                            'owner'     => 'handleOwner',
                            'deowner'   => 'handleDeOwner',
                        );

            $fmt = $this->getFormatter(false);
            foreach ($triggers as $default => $handler) {
                $trigger = $this->parseString('trigger_'.$default, $default);
                $this->triggers[$default] =
                    $registry->registerTriggers($trigger, $matchAny);
                if ($this->triggers[$default] === null) {
                    $msg = $fmt->_(
                        'Could not register trigger for admin command '.
                        '"<var name="command"/>"',
                        array('command' => $default)
                    );
                    throw new \Exception($msg);
                }

                $this->handlers[$default] = new \Erebot\EventHandler(
                    \Erebot\CallableWrapper::wrap(array($this, $handler)),
                    new \Erebot\Event\Match\All(
                        new \Erebot\Event\Match\Type(
                            '\\Erebot\\Interfaces\\Event\\ChanText'
                        ),
                        new \Erebot\Event\Match\Any(
                            new \Erebot\Event\Match\TextStatic($trigger, true),
                            new \Erebot\Event\Match\TextWildcard(
                                $trigger.' *',
                                true
                            )
                        )
                    )
                );
                $this->connection->addEventHandler($this->handlers[$default]);
            }

            // Join
            $trigger = $this->parseString('trigger_join', 'join');
            $this->triggers['join'] =
                $registry->registerTriggers($trigger, $matchAny);
            if ($this->triggers['join'] === null) {
                $msg = $fmt->_(
                    'Could not register trigger for admin command "join"'
                );
                throw new Exception($msg);
            }

            $this->handlers['join'] = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleJoin')),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type(
                        '\\Erebot\\Interfaces\\Event\\Base\\TextMessage'
                    ),
                    new \Erebot\Event\Match\Any(
                        new \Erebot\Event\Match\TextWildcard(
                            $trigger.' &',
                            true
                        ),
                        new \Erebot\Event\Match\TextWildcard(
                            $trigger.' & *'.
                            true
                        )
                    )
                )
            );
            $this->connection->addEventHandler($this->handlers['join']);

            // Reload
            $trigger = $this->parseString('trigger_reload', 'reload');
            $this->triggers['reload'] =
                $registry->registerTriggers($trigger, $matchAny);
            if ($this->triggers['reload'] === null) {
                $msg = $fmt->_(
                    'Could not register trigger '.
                    'for admin command "reload"'
                );
                throw new Exception($msg);
            }

            $this->handlers['reload'] = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleReload')),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type(
                        '\\Erebot\\Interfaces\\Event\\Base\\TextMessage'
                    ),
                    new \Erebot\Event\Match\Any(
                        new \Erebot\Event\Match\TextStatic($trigger, true),
                        new \Erebot\Event\Match\TextWildcard($trigger.' *', true)
                    )
                )
            );
            $this->connection->addEventHandler($this->handlers['reload']);
            $this->admins = array_filter(
                explode(
                    ' ',
                    str_replace(',', ' ', trim($this->parseString('admins', '')))
                )
            );
        }
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot::Interfaces::Event::Base_TextMessage $event
     *      Some help request.
     *
     * \param Erebot::Interfaces::TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        \Erebot\Interfaces\Event\Base\TextMessage $event,
        \Erebot\Interfaces\TextWrapper $words
    ) {
        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        if ($words[0] !== get_called_class()) {
            return false;
        }

        // Help on module.
        if (count($words) == 1) {
            $msg = $this->getFormatter($chan)->_(
                "This module provides several commands that require ".
                "administrator privileges."
            );
            $this->sendMessage($target, $msg);
            return true;
        }
    }

    /**
     * Tests whether the given identity refers
     * to an administrator or not.
     *
     * \param Erebot::Identity $identity
     *      Identity to test.
     *
     * \retval bool
     *      \b true if the given identity refers to
     *      an administrator, \b false otherwise.
     */
    protected function isAdmin(\Erebot\Identity $identity)
    {
        $factory = $this->getFactory('!Styling');
        $styles = new $factory($this->mainCfg->getTranslator(get_class()));
        $user   = $identity->getMask(\Erebot\Interfaces\Identity::CANON_IPV4);
        $this->logger and $this->logger->debug(
            $styles->_(
                'Checking whether <var name="user"/> is an administrator',
                array('user' => $user)
            )
        );
        $collator = $this->connection->getCollator();
        foreach ($this->admins as $admin) {
            if ($identity->match($admin, $collator)) {
                $this->logger and $this->logger->info(
                    $styles->_(
                        '<var name="user"/> was granted administrator access',
                        array('user' => $user)
                    )
                );
                return true;
            }
        }
        $this->logger and $this->logger->warn(
            $styles->_(
                '<var name="user"/> is not a valid administrator',
                array('user' => $user)
            )
        );
        return false;
    }

    /**
     * Handles a request to make the bot
     * leave an IRC channel.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to make the bot leave an IRC channel.
     *      The request may contain the name of the IRC
     *      channel to leave or "*" to make it leave all
     *      channels the bot is currently on.
     *      If missing, the current channel will be left.
     *      You may also pass some message that will be
     *      displayed to other users when leaving the
     *      channel.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handlePart(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Base\TextMessage $event
    ) {
        if (!$this->isAdmin($event->getSource())) {
            return;
        }
        $text       = $event->getText();
        $chans      = $text->getTokens(1, 1);
        $message    = $text->getTokens(2);

        if ($chans == '*') {
            $targets    = '0';
        } elseif ($this->connection->isChannel((string) substr($chans, 0, 1))) {
            $targets    = $chans;
        } else {
            $targets    = $event->getChan();
            $message    = $text->getTokens(1);
        }

        $styles = new \Erebot\Styling($this->mainCfg->getTranslator(get_class()));
        $this->logger and $this->logger->info(
            $styles->_(
                'Leaving <var name="targets"/> as requested by <var name="user"/>',
                array(
                    'user' => $event->getSource()->getMask(\Erebot\Interfaces\Identity::CANON_IPV4),
                    'targets' => $targets,
                )
            )
        );
        $this->sendCommand('PART '.$targets.' :'.$message);
    }

    /**
     * Handles a request to make the bot
     * disconnect from the current server.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to make the bot disconnect from the
     *      current IRC server.
     *      You may pass a message that will be used as the
     *      quit message.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleQuit(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Base\TextMessage $event
    ) {
        if (!$this->isAdmin($event->getSource())) {
            return;
        }
        $text   = $event->getText();
        $msg    = $text->getTokens(1);
        if (rtrim($msg) == '') {
            $msg = null;
        }

        $eventsProducer = $this->connection->getEventsProducer();
        $disconnection = $eventsProducer->makeEvent('!Disconnect');
        $this->connection->dispatch($disconnection);
        if (!$disconnection->preventDefault()) {
            $styles     = new \Erebot\Styling($this->mainCfg->getTranslator(get_class()));
            $this->logger and $this->logger->info(
                $styles->_(
                    'Disconnecting as requested by <var name="user"/>',
                    array('user' => $event->getSource()->getMask(\Erebot\Interfaces\Identity::CANON_IPV4))
                )
            );
            $this->connection->disconnect($msg);
        }
    }

    /**
     * Adds or removes a channel status for some user
     * on some IRC channel.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      The original request to add or remove a channel
     *      status from some user.
     *
     * \param string $mode
     *      A string describing the type of change to apply.
     *      ie. something like "+v" (voice) or "-o" (deop).
     */
    protected function setMode(\Erebot\Interfaces\Event\Base\TextMessage $event, $mode)
    {
        $styles = new \Erebot\Styling($this->mainCfg->getTranslator(get_class()));
        $source = $event->getSource();
        if (!$this->isAdmin($source)) {
            return;
        }

        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        $fmt = $this->getFormatter($chan);
        $msg = $fmt->_('This server does not support this operation');

        try {
            $capabilities = $this->connection->getModule(
                '\\Erebot\\Module\\ServerCapabilities',
                null,
                false
            );
            try {
                if (!$capabilities->isChannelPrivilege(substr($mode, 1))) {
                    $this->sendMessage($target, $msg);
                    return;
                }
            } catch (\Erebot\InvalidValueException $e) {
                // This should never happen, but still...
                return false;
            }
        } catch (\Erebot\NotFoundException $e) {
            // By default, we're strict about what modes
            // can be changed to follow RFC 1459.
            if (!in_array(substr($mode, 1), array('o', 'v'))) {
                $this->sendMessage($target, $msg);
                return;
            }
        }

        $text       = $event->getText();
        $nbNicks    = count($text) - 1;
        $prefix     = 'MODE '.$event->getChan().' '.$mode.' :';

        if (!$nbNicks) {
            $this->logger and $this->logger->info(
                $styles->_(
                    'Setting mode <var name="mode"/> ' .
                    'on <var name="target"/> ' .
                    'in channel <var name="chan"/> ' .
                    'as requested by <var name="user"/>',
                    array(
                        'mode' => $mode,
                        'chan' => $event->getChan(),
                        'target' => $source->getNick(),
                        'user' => $source->getMask(\Erebot\Interfaces\Identity::CANON_IPV4),
                    )
                )
            );
            $this->sendCommand($prefix.$source->getNick());
            return;
        }

        for ($i = 1; $i <= $nbNicks; $i++) {
            $this->logger and $this->logger->info(
                $styles->_(
                    'Setting mode <var name="mode"/> ' .
                    'on <var name="target"/> ' .
                    'in channel <var name="chan"/> ' .
                    'as requested by <var name="user"/>',
                    array(
                        'mode' => $mode,
                        'chan' => $event->getChan(),
                        'target' => $text[$i],
                        'user' => $source->getMask(\Erebot\Interfaces\Identity::CANON_IPV4),
                    )
                )
            );
            $this->sendCommand($prefix.$text[$i]);
        }
    }

    /**
     * Handles a request to give someone
     * the voice status (+v).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to give someone the voice status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleVoice(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $this->setMode($event, '+v');
    }

    /**
     * Handles a request to take the voice
     * status from someone (-v).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to remove the voice status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeVoice(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $this->setMode($event, '-v');
    }

    /**
     * Handles a request to give someone
     * the half-operator status (+h).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to give someone the half-operator status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleHalfOp(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '+h');
    }

    /**
     * Handles a request to take the half-operator
     * status from someone (-h).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to remove the half-operator status
     *      from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeHalfOp(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '-h');
    }

    /**
     * Handles a request to give someone
     * the operator status (+o).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to give someone the operator status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleOp(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $this->setMode($event, '+o');
    }

    /**
     * Handles a request to take the operator
     * status from someone (-o).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to remove the operator status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeOp(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $this->setMode($event, '-o');
    }

    /**
     * Handles a request to give someone
     * the protected status (+a).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to give someone the protected status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleProtect(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '+a');
    }

    /**
     * Handles a request to take the protected
     * status from someone (-a).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to remove the protected status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeProtect(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '-a');
    }

    /**
     * Handles a request to give someone
     * the owner status (+q).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to give someone the owner status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleOwner(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '+q');
    }

    /**
     * Handles a request to take the owner
     * status from someone (-q).
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to remove the owner status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeOwner(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        /// @FIXME: The mode should not be hardcoded like that.
        $this->setMode($event, '-q');
    }

    /**
     * Handles a request to join some IRC channel.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to join the IRC channel with the given
     *      name (passed as an additional parameter).
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleJoin(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Base\TextMessage $event
    ) {
        if (!$this->isAdmin($event->getSource())) {
            return;
        }

        $text   = $event->getText();
        $args   = $text->getTokens(1);
        $this->sendCommand('JOIN '.$args);
    }

    /**
     * Handles a request to reload the bot's modules.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Request to reload all modules used by the bot.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleReload(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Base\TextMessage $event
    ) {
        if (!$this->isAdmin($event->getSource())) {
            return;
        }

        $bot = $this->connection->getBot();
        $bot->reload();
        return;

        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        $fmt = $this->getFormatter($chan);
        if (!function_exists('runkit_import')) {
            $msg = $fmt->_(
                'The runkit extension is needed to perform hot reloads'
            );
            $this->sendMessage($chan, $msg);
            return;
        }

        $files  = get_included_files();
        $wrong  = array();
        foreach ($files as $file) {
            if (substr($file, -4) == '.php') {
                $parts = explode(DIRECTORY_SEPARATOR, substr($file, 0, -4));
                while (count($parts)) {
                    $class = implode('_', $parts);
                    if (interface_exists($class, false)) {
                        continue 2;
                    }

                    if (class_exists($class, false)) {
                        if ($parts[0] != 'Erebot') {
                            continue 2;
                        }
                        break;
                    }
                    array_shift($parts);
                }
                if (!count($parts)) {
                    continue;
                }

                echo "Reloading $file ($class)\n";
                $ok = @runkit_import(
                    $file,
                    RUNKIT_IMPORT_FUNCTIONS |
                    RUNKIT_IMPORT_CLASSES
                );

                if (!$ok) {
                    $wrong[] = $file;
                } else {
                    echo "Reloaded $file ($class)\n";
                }
            }
        }

        if (count($wrong)) {
            $msg = $fmt->_(
                'The following files could not be reloaded: '.
                '<for from="files" item="file"><var name="file"/></for>',
                array('files' => $wrong)
            );
            $this->sendMessage($target, $msg);
            return;
        }
        $msg = $fmt->_('Successfully reloaded files.');
        $this->sendMessage($target, $msg);
    }
}
