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

namespace Erebot {
    class Identity
    {
        protected $identity;

        public function __construct($identity)
        {
            $this->identity = $identity;
        }

        public function getNick()
        {
            return $this->identity;
        }

        public function match($admin, \Erebot\Interfaces\IrcCollator $collator)
        {
            return ($admin == $this->identity);
        }
    }
}

namespace {
abstract class  TextWrapper
implements      \Erebot\Interfaces\TextWrapper
{
    private $_chunks;

    public function __construct($text)
    {
        $this->_chunks = explode(' ', $text);
    }

    public function __toString()
    {
        return implode(' ', $this->_chunks);
    }

    public function getTokens($start, $length = 0, $separator = " ")
    {
        if ($length !== 0)
            return implode(" ", array_slice($this->_chunks, $start, $length));
        return implode(" ", array_slice($this->_chunks, $start));
    }

    public function offsetGet($offset)
    {
        return $this->_chunks[$offset];
    }

    public function count()
    {
        return count($this->_chunks);
    }
}

class   TestModuleHelper
extends \Erebot\Module\Admin
{
    public function reload($flags)
    {
        parent::reload($flags);
        $this->admins = array('admin');
    }
}

class   AdminTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new TestModuleHelper(NULL);
        parent::setUp();
        $this->_module->reloadModule(
            $this->_connection,
            \Erebot\Module\Base::RELOAD_MEMBERS
        );

        $this->_disconnect = $this->getMock(
            '\\Erebot\\Interfaces\\Event\\Disconnect',
            array(), array(), '', FALSE, FALSE
        );

        $this->_eventsProducer = $this->getMock(
            '\\Erebot\\Interfaces\\IrcParser',
            array(), array(), '', FALSE, FALSE
        );

        $this->_eventsProducer
            ->expects($this->any())
            ->method('makeEvent')
            ->will($this->returnValue($this->_disconnect));

        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->throwException(new \Erebot\NotFoundException()));

        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->throwException(new \Erebot\NotFoundException()));

        $this->_connection
            ->expects($this->any())
            ->method('getEventsProducer')
            ->will($this->returnValue($this->_eventsProducer));

        $this->_textMock = $this->getMockForAbstractClass(
            'TextWrapper',
            array(),
            '',
            FALSE,
            FALSE
        );
    }

    public function tearDown()
    {
        $this->_module->unloadModule();
        parent::tearDown();
    }

    public function disconnect($msg)
    {
        $this->_outputBuffer[] = 'QUIT :'.$msg;
    }

    protected function _getEvent($msg)
    {
        $event = $this->getMock(
            '\\Erebot\\Interfaces\\Event\\ChanText',
            array(), array(), '', FALSE, FALSE
        );

        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));
        $event
            ->expects($this->any())
            ->method('getText')
            ->will($this->returnValue(new $this->_textMock($msg)));
        $event
            ->expects($this->any())
            ->method('getChan')
            ->will($this->returnValue('#test'));

        // On the first call, emulates a message from a non-admin,
        // on the second call, emulates a message from an admin.
        $event
            ->expects($this->any())
            ->method('getSource')
            ->will($this->onConsecutiveCalls(
                new \Erebot\Identity('foo'),
                new \Erebot\Identity('admin')
            ));

        return $event;
    }

    protected function _triggerTest($callback, $input, $output)
    {
        if (!is_array($output))
            $output = array($output);

        $event = $this->_getEvent($input);
        $this->_outputBuffer = array();
        call_user_func($callback, $this->_eventHandler, $event);
        $this->assertSame(0, count($this->_outputBuffer));
        call_user_func($callback, $this->_eventHandler, $event);
        $this->assertSame(count($output), count($this->_outputBuffer));
        $this->assertSame($output, $this->_outputBuffer);
    }

    public function testPart()
    {
        $callback = array($this->_module, 'handlePart');

        $this->_triggerTest($callback, '!part', "PART #test :");
        $this->_triggerTest(
            $callback,
            '!part Part message',
            "PART #test :Part message"
        );

        $this->_triggerTest($callback, '!part #test', "PART #test :");
        $this->_triggerTest(
            $callback,
            '!part #test Part message',
            "PART #test :Part message"
        );

        $this->_triggerTest($callback, '!part *', "PART 0 :");
        $this->_triggerTest(
            $callback,
            '!part * Part message',
            "PART 0 :Part message"
        );
    }

    public function testQuit()
    {
        $disconnect =  $this->getMock(
            '\\Erebot\\Interfaces\\Event\\Base\\Generic',
            array(), array(), '', FALSE, FALSE
        );

        $this->_connection
            ->expects($this->any())
            ->method('makeEvent')
            ->will($this->returnValue($disconnect));
        $this->_connection
            ->expects($this->any())
            ->method('disconnect')
            ->will($this->returnCallback(array($this, 'disconnect')));

        $callback = array($this->_module, 'handleQuit');
        $this->_triggerTest(
            $callback,
            '!quit Quit message',
            "QUIT :Quit message"
        );
    }

    public function testVoice()
    {
        $callback = array($this->_module, 'handleVoice');
        $this->_triggerTest($callback, '!voice', "MODE #test +v :admin");
        $this->_triggerTest($callback, '!voice bar', "MODE #test +v :bar");
        $this->_triggerTest(
            $callback,
            '!voice bar baz',
            array(
                "MODE #test +v :bar",
                "MODE #test +v :baz",
            )
        );
    }

    public function testDeVoice()
    {
        $callback = array($this->_module, 'handleDeVoice');
        $this->_triggerTest($callback, '!devoice', "MODE #test -v :admin");
        $this->_triggerTest($callback, '!devoice bar', "MODE #test -v :bar");
        $this->_triggerTest(
            $callback,
            '!devoice bar baz',
            array(
                "MODE #test -v :bar",
                "MODE #test -v :baz",
            )
        );
    }

    public function testOp()
    {
        $callback = array($this->_module, 'handleOp');
        $this->_triggerTest($callback, '!op', "MODE #test +o :admin");
        $this->_triggerTest($callback, '!op bar', "MODE #test +o :bar");
        $this->_triggerTest(
            $callback,
            '!op bar baz',
            array(
                "MODE #test +o :bar",
                "MODE #test +o :baz",
            )
        );
    }

    public function testDeOp()
    {
        $callback = array($this->_module, 'handleDeOp');
        $this->_triggerTest($callback, '!deop', "MODE #test -o :admin");
        $this->_triggerTest($callback, '!deop bar', "MODE #test -o :bar");
        $this->_triggerTest(
            $callback,
            '!deop bar baz',
            array(
                "MODE #test -o :bar",
                "MODE #test -o :baz",
            )
        );
    }

    public function testJoin()
    {
        $callback = array($this->_module, 'handleJoin');
        $this->_triggerTest($callback, '!join #foo bar', 'JOIN #foo bar');
    }
}
} // namespace
