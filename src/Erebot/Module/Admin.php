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

class   Erebot_Module_Admin
extends Erebot_Module_Base
{
    protected $_handlers;
    protected $_triggers;

    public function _reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                foreach ($this->_triggers as $name => $value) {
                    $this->_connection->removeEventHandler($this->_handlers[$name]);
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

            foreach ($triggers as $default => $handler) {
                $trigger = $this->parseString('trigger_'.$default, $default);
                $this->_triggers[$default] = $registry->registerTriggers($trigger, $matchAny);
                if ($this->_triggers[$default] === NULL) {
                    $message    = $this->gettext('Could not register trigger '.
                                    'for admin command "<var name="command"'.
                                    '/>"');
                    $tpl        = Erebot_Styling($message, $this->_translator);
                    $tpl->assign('command', $default);
                    throw new Exception($tpl->render());
                }

                $this->_handlers[$default] = new Erebot_EventHandler(
                    new Erebot_Callable(array($this, $handler)),
                    new Erebot_Event_Match_All(
                        new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_Base_TextMessage'),
                        new Erebot_Event_Match_Any(
                            new Erebot_Event_Match_TextStatic($trigger, TRUE),
                            new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                        )
                    )
                );
                $this->_connection->addEventHandler($this->_handlers[$default]);
            }

            // Join
            $trigger = $this->parseString('trigger_join', 'join');
            $this->_triggers['join'] = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_triggers['join'] === NULL) {
                $message = $this->_translator->gettext(
                    'Could not register trigger for admin command "join"');
                throw new Exception($message);
            }

            $this->_handlers['join'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleJoin')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_Base_TextMessage'),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextWildcard($trigger.' &', TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' & *'. TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['join']);

            // Reload
            $trigger = $this->parseString('trigger_reload', 'reload');
            $this->_triggers['reload'] = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_triggers['reload'] === NULL) {
                $message    = $this->_translator->gettext('Could not register trigger '.
                                'for admin command "reload"');
                throw new Exception($message);
            }

            $this->_handlers['reload'] = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleReload')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_Base_TextMessage'),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($trigger, TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handlers['reload']);
            $this->_admins = array();
            $admins = array_filter(
                explode(
                    ' ',
                    str_replace(',', ' ',
                        trim($this->parseString('admins', ''))
                    )
                )
            );
            foreach ($admins as $admin) {
                $identity           = new Erebot_Identity($admin);
                $this->_admins[]    = $identity->getMask();
            }
        }
    }

    protected function _unload()
    {
    }

    protected function isAdmin($identity)
    {
        if (!count($this->_admins))
            return TRUE;

        foreach ($this->_admins as $admin)
            if ($identity->match($admin))
                return TRUE;
        return FALSE;
    }

    public function handlePart(Erebot_Interface_Event_Base_TextMessage $event)
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

    public function handleQuit(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
        $text   = $event->getText();
        $msg    = $text->getTokens(1);
        if (rtrim($msg) == '')
            $msg = NULL;

        $this->_connection->dispatch($this->_connection->makeEvent('!Exit'));
        $this->_connection->disconnect($msg);
    }

    public function handleVoice(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleDeVoice(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleHalfOp(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleDeHalfOp(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleOp(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleDeOp(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleProtect(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleDeProtect(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleOwner(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleDeOwner(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
    }

    public function handleJoin(Erebot_Interface_Event_Base_TextMessage $event)
    {
        if (!$this->isAdmin($event->getSource()))
            return;
        $text   = $event->getText();
        $args   = $text->getTokens(1);

        $this->sendCommand('JOIN '.$args);
    }

    public function handleReload(Erebot_Interface_Event_Base_TextMessage &$event)
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

        $translator = $this->getTranslator($chan);
        if (!function_exists('runkit_import')) {
            $msg = $translator->gettext('The runkit extension is needed to perform hot reloads');
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
                $ok = @runkit_import($file,
                    RUNKIT_IMPORT_FUNCTIONS |
                    RUNKIT_IMPORT_CLASSES   |
                    0
#                    RUNKIT_IMPORT_OVERRIDE
                );

                if (!$ok)
                    $wrong[] = $file;
                else
                    echo "Reloaded $file ($class)\n";
            }
        }

        if (count($wrong)) {
            $msg = $translator->gettext('The following files could not be '.
                'reloaded: <for from="files" item="file"><var name="file"/>'.
                '</for>');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('files', $wrong);
            $this->sendMessage($target, $tpl->render());
            return;
        }
        $msg = $translator->gettext('Successfully reloaded files.');
        $this->sendMessage($target, $msg);
    }
}

