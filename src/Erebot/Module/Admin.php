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

/**
 * \brief
 *      A module that provides several commands
 *      intended for administrators.
 */
class   Erebot_Module_Admin
extends Erebot_Module_Base
{
    /// A list of handlers registered by this module.
    protected $_handlers;

    /// A list of triggers registered by this module.
    protected $_triggers;


    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function _reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                foreach ($this->_triggers as $name => $value) {
                    $this->_connection->removeEventHandler(
                        $this->_handlers[$name]
                    );
                    $registry->freeTriggers($value, $matchAny);
                }
            }

            $this->_handlers = $this->_triggers = array();

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

            $fmt = $this->getFormatter(FALSE);
            foreach ($triggers as $default => $handler) {
                $trigger = $this->parseString('trigger_'.$default, $default);
                $this->_triggers[$default] =
                    $registry->registerTriggers($trigger, $matchAny);
                if ($this->_triggers[$default] === NULL) {
                    $msg = $fmt->_(
                        'Could not register trigger for admin command '.
                        '"<var name="command"/>"',
                        array('command' => $default)
                    );
                    throw new Exception($msg);
                }

                $this->_handlers[$default] = new Erebot_EventHandler(
                    new Erebot_Callable(array($this, $handler)),
                    new Erebot_Event_Match_All(
                        new Erebot_Event_Match_InstanceOf(
                            'Erebot_Interface_Event_ChanText'
                        ),
                        new Erebot_Event_Match_Any(
                            new Erebot_Event_Match_TextStatic($trigger, TRUE),
                            new Erebot_Event_Match_TextWildcard(
                                $trigger.' *',
                                TRUE
                            )
                        )
                    )
                );
                $this->_connection->addEventHandler($this->_handlers[$default]);
            }

            // Join
            $trigger = $this->parseString('trigger_join', 'join');
            $this->_triggers['join'] =
                $registry->registerTriggers($trigger, $matchAny);
            if ($this->_triggers['join'] === NULL) {
                $msg = $fmt->_(
                    'Could not register trigger for admin command "join"'
                );
                throw new Exception($msg);
            }

            $this->_handlers['join'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleJoin')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf(
                        'Erebot_Interface_Event_Base_TextMessage'
                    ),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextWildcard(
                            $trigger.' &',
                            TRUE
                        ),
                        new Erebot_Event_Match_TextWildcard(
                            $trigger.' & *'.
                            TRUE
                        )
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['join']);

            // Reload
            $trigger = $this->parseString('trigger_reload', 'reload');
            $this->_triggers['reload'] =
                $registry->registerTriggers($trigger, $matchAny);
            if ($this->_triggers['reload'] === NULL) {
                $msg = $fmt->_(
                    'Could not register trigger '.
                    'for admin command "reload"'
                );
                throw new Exception($msg);
            }

            $this->_handlers['reload'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleReload')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf(
                        'Erebot_Interface_Event_Base_TextMessage'
                    ),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($trigger, TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['reload']);
            $this->_admins = array_filter(
                explode(
                    ' ',
                    str_replace(
                        ',', ' ',
                        trim($this->parseString('admins', ''))
                    )
                )
            );
        }
    }

    /**
     * Tests whether the given identity refers
     * to an administrator or not.
     *
     * \param Erebot_Identity $identity
     *      Identity to test.
     *
     * \retval bool
     *      TRUE if the given identity refers to
     *      an administrator, FALSE otherwise.
     */
    protected function isAdmin(Erebot_Identity $identity)
    {
        $collator = $this->_connection->getCollator();
        foreach ($this->_admins as $admin)
            if ($identity->match($admin, $collator))
                return TRUE;
        return FALSE;
    }

    /**
     * Handles a request to make the bot
     * leave an IRC channel.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
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
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if (!$this->isAdmin($event->getSource()))
            return;
        $text       = $event->getText();
        $chans      = $text->getTokens(1, 1);
        $message    = $text->getTokens(2);

        if ($chans == '*')
            $targets    = '0';
        else if ($this->_connection->isChannel((string) substr($chans, 0, 1)))
            $targets    = $chans;
        else {
            $targets    = $event->getChan();
            $message    = $text->getTokens(1);
        }

        $this->sendCommand('PART '.$targets.' :'.$message);
    }

    /**
     * Handles a request to make the bot
     * disconnect from the current server.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to make the bot disconnect from the
     *      current IRC server.
     *      You may pass a message that will be used as the
     *      quit message.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleQuit(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if (!$this->isAdmin($event->getSource()))
            return;
        $text   = $event->getText();
        $msg    = $text->getTokens(1);
        if (rtrim($msg) == '')
            $msg = NULL;

        $eventsProducer = $this->_connection->getEventsProducer();
        $disconnection = $eventsProducer->makeEvent('!Disconnect');
        $this->_connection->dispatch($disconnection);
        if (!$disconnection->preventDefault())
            $this->_connection->disconnect($msg);
    }

    /**
     * Adds or removes a channel status for some user
     * on some IRC channel.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      The original request to add or remove a channel
     *      status from some user.
     *
     * \param string $mode
     *      A string describing the type of change to apply.
     *      ie. something like "+v" (voice) or "-o" (deop).
     */
    protected function _setMode(
        Erebot_Interface_Event_Base_TextMessage $event,
                                                $mode
    )
    {
        $source = $event->getSource();
        if (!$this->isAdmin($source))
            return;

        try {
            $capabilities = $this->_connection->getModule(
                'Erebot_Module_ServerCapabilities',
                NULL, FALSE
            );
            try {
                if (!$capabilities->isChannelPrivilege(substr($mode, 1)))
                    return;
            }
            catch (Erebot_InvalidValueException $e) {
                // This should never happen, but still...
                return FALSE;
            }
        }
        catch (Erebot_NotFoundException $e) {
            // By default, we're strict about what modes
            // can be changed to follow RFC 1459.
            if (!in_array(substr($mode, 1), array('o', 'v')))
                return;
        }

        $text       = $event->getText();
        $nbNicks    = count($text) - 1;
        $prefix     = 'MODE '.$event->getChan().' '.$mode.' :';

        if (!$nbNicks) {
            $this->sendCommand($prefix.$source->getNick());
            return;
        }

        for ($i = 1; $i <= $nbNicks; $i++)
            $this->sendCommand($prefix.$text[$i]);
    }

    /**
     * Handles a request to give someone
     * the voice status (+v).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to give someone the voice status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleVoice(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '+v');
    }

    /**
     * Handles a request to take the voice
     * status from someone (-v).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to remove the voice status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeVoice(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '-v');
    }

    /**
     * Handles a request to give someone
     * the half-operator status (+h).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to give someone the half-operator status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleHalfOp(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '+h');
    }

    /**
     * Handles a request to take the half-operator
     * status from someone (-h).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to remove the half-operator status
     *      from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeHalfOp(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '-h');
    }

    /**
     * Handles a request to give someone
     * the operator status (+o).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to give someone the operator status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleOp(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '+o');
    }

    /**
     * Handles a request to take the operator
     * status from someone (-o).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to remove the operator status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeOp(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '-o');
    }

    /**
     * Handles a request to give someone
     * the protected status (+a).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to give someone the protected status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleProtect(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '+a');
    }

    /**
     * Handles a request to take the protected
     * status from someone (-a).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to remove the protected status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeProtect(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '-a');
    }

    /**
     * Handles a request to give someone
     * the owner status (+q).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to give someone the owner status.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleOwner(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '+q');
    }

    /**
     * Handles a request to take the owner
     * status from someone (-q).
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to remove the owner status from someone.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleDeOwner(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $this->_setMode($event, '-q');
    }

    /**
     * Handles a request to join some IRC channel.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to join the IRC channel with the given
     *      name (passed as an additional parameter).
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleJoin(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if (!$this->isAdmin($event->getSource()))
            return;

        $text   = $event->getText();
        $args   = $text->getTokens(1);
        $this->sendCommand('JOIN '.$args);
    }

    /**
     * Handles a request to reload the bot's modules.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Request to reload all modules used by the bot.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleReload(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if (!$this->isAdmin($event->getSource()))
            return;

        $bot = $this->_connection->getBot();
        $bot->reload();
        return;

        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

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
                    if (interface_exists($class, FALSE))
                        continue 2;

                    if (class_exists($class, FALSE)) {
                        if ($parts[0] != 'Erebot')
                            continue 2;
                        break;
                    }
                    array_shift($parts);
                }
                if (!count($parts))
                    continue;

#                $reflector = new ReflectionClass($class);
#                if ($reflector->isAbstract())
#                    continue;

#                $blacklist = array(
#                    'Erebot_Module_AutoJoin',
#                    'Erebot_Module_AZ',
#                    'Erebot_Module_TriggerRegistry',
#                    'Erebot_Event_Match_Any',
#                    'Erebot_Event_Match_TextWildcard',
#                    'Erebot_Module_Admin',
#                    'Erebot_Event_Match_TextRegex',
#                    'Erebot_Module_AutoConnect',
#                    'Erebot_Module_Countdown',
#                    'Erebot_Module_Helper',
#                    'Erebot_Module_CtcpResponder',
#                    'Erebot_Event_ChanText',
#                    'Erebot_Module_IrcConnector',
#                    'Erebot_Module_PingReply',
#                );
#                if (in_array($class, $blacklist))
#                    continue;

                echo "Reloading $file ($class)\n";
                $ok = @runkit_import(
                    $file,
                    RUNKIT_IMPORT_FUNCTIONS |
                    RUNKIT_IMPORT_CLASSES
                );

                if (!$ok)
                    $wrong[] = $file;
                else
                    echo "Reloaded $file ($class)\n";
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

