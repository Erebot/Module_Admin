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
    static protected $_metadata = array(
        'requires'  =>  array(
            'Erebot_Module_TriggerRegistry',
        ),
    );
    protected $_handlers;
    protected $_triggers;

    public function reload($flags)
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

                $filter = new Erebot_TextFilter($this->_mainCfg);
                $filter->addPattern(Erebot_TextFilter::TYPE_STATIC,      $trigger, TRUE);
                $filter->addPattern(Erebot_TextFilter::TYPE_WILDCARD,    $trigger.' *', TRUE);
                $this->_handlers[$default] = new Erebot_EventHandler(
                    array($this, $handler),
                    'Erebot_Interface_Event_TextMessage',
                    NULL, $filter
                );
                $this->_connection->addEventHandler($this->_handlers[$default]);
            }

            // Join
            $trigger = $this->parseString('trigger_join', 'join');
            $this->_triggers['join'] = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_triggers['join'] === NULL) {
                $message    = $this->_translator->gettext('Could not register trigger '.
                                'for admin command "join"');
                throw new Exception($message);
            }

            $filter = new Erebot_TextFilter($this->_mainCfg);
            $filter->addPattern(Erebot_TextFilter::TYPE_WILDCARD,    $trigger.' &',      TRUE);
            $filter->addPattern(Erebot_TextFilter::TYPE_WILDCARD,    $trigger.' & *',    TRUE);
            $this->_handlers['join'] = new Erebot_EventHandler(
                array($this, 'handleJoin'),
                'Erebot_Interface_Event_TextMessage',
                NULL, $filter
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

            $filter = new Erebot_TextFilter($this->_mainCfg);
            $filter->addPattern(Erebot_TextFilter::TYPE_STATIC,      $trigger, TRUE);
            $filter->addPattern(Erebot_TextFilter::TYPE_WILDCARD,    $trigger.' *', TRUE);
            $this->_handlers['reload'] = new Erebot_EventHandler(
                array($this, 'handleReload'),
                'Erebot_Interface_Event_TextMessage',
                NULL, $filter
            );
            $this->_connection->addEventHandler($this->_handlers['reload']);
        }
    }

    public function handlePart(Erebot_Interface_Event_TextMessage $event)
    {
        $text       = $event->getText();
        $chans      = Erebot_Utils::gettok($text, 1, 1);
        $message    = Erebot_Utils::gettok($text, 2);

        if ($chans == '*')
            $targets    = '0';
        else if (substr($chans, 0, 1) == '#')
            $targets    = $chans;
        else {
            $targets    = $event->getChan();
            $message    = Erebot_Utils::gettok($text, 1);
        }

        $this->sendCommand('PART '.$targets.' :'.$message);
    }

    public function handleQuit(Erebot_Interface_Event_TextMessage $event)
    {
        $text   = $event->getText();
        $msg    = Erebot_Utils::gettok($text, 1);
        if (rtrim($msg) == '')
            $msg = NULL;
        $exitEvent = new Erebot_Event_Exit($this->_connection);
        $this->_connection->dispatchEvent($exitEvent);
        $this->_connection->disconnect($msg);
    }

    public function handleVoice(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleDeVoice(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleHalfOp(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleDeHalfOp(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleOp(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleDeOp(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleProtect(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleDeProtect(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleOwner(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleDeOwner(Erebot_Interface_Event_TextMessage $event)
    {
        
    }

    public function handleJoin(Erebot_Interface_Event_TextMessage $event)
    {
        $text   = $event->getText();
        $args   = ErebotUtils::gettok($text, 1);

        $this->sendCommand('JOIN '.$args);
    }

    public function handleReload(Erebot_Interface_Event_TextMessage &$event)
    {
        if ($event instanceof Erebot_Interface_Event_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $translator = $this->getTranslator($chan);
        if (!function_exists('runkit_import')) {
            $msg = $translator->gettext('The runkit extension is needed to perform hot-reload.');
            $this->sendMessage($chan, $msg);
            return;
        }

        $files  = get_included_files();
        $wrong  = array();
        foreach ($files as $file) {
            if (substr($file, -4) == '.php') {
                $ok	= runkit_import($file,
                    RUNKIT_IMPORT_FUNCTIONS |
                    RUNKIT_IMPORT_CLASSES   |
                    RUNKIT_IMPORT_OVERRIDE
                );

                if (!$ok)
                    $wrong[] = $file;
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
        else
            $msg = $translator->gettext('Successfully reloaded files.');
            $this->sendMessage($target, $msg);
    }
}

