<?php
/**
 * Tests the filter class
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Devices
 * @package    HUGnetLibTest
 * @subpackage Default
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */

/** Get our classes */
require_once dirname(__FILE__).'/CheckPluginTestBase.php';
require_once dirname(__FILE__)
    .'/../../../../endpoints/plugins/check/CriticalErrorCheckPlugin.php';
require_once HUGNET_INCLUDE_PATH."/processes/PeriodicCheck.php";
/**
 * Test class for filter.
 * Generated by PHPUnit_Util_Skeleton on 2007-10-30 at 08:44:56.
 *
 * @category   Devices
 * @package    HUGnetLibTest
 * @subpackage Default
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class CriticalErrorCheckPluginTest extends CheckPluginTestBase
{

    /**
    * Sets up the fixture, for example, open a network connection.
    * This method is called before a test is executed.
    *
    * @return null
    *
    * @access protected
    */
    protected function setUp()
    {

        $config = array(
            "script_gateway" => 1,
            "admin_email" => "test@dflytech.com",
            "check" => array(
                "send_daily" => true,
            ),
            "test" => true,
        );
        $this->config = &ConfigContainer::singleton();
        $this->config->forceConfig($config);
        $this->socket = &$this->config->sockets->getSocket("default");
        $this->device = array(
            "id"         => 0x000019,
            "DeviceID"   => "000019",
            "HWPartNum"  => "0039-26-00-P",
            "FWPartNum"  => "0039-26-00-P",
        );

        $this->control = new PeriodicCheck(array(), $this->device);
        $this->o = new CriticalErrorCheckPlugin(array(), $this->control);
    }

    /**
    * Tears down the fixture, for example, close a network connection.
    * This method is called after a test is executed.
    *
    * @return null
    *
    * @access protected
    */
    protected function tearDown()
    {
        unset($this->o);
    }

    /**
    * Data provider for testRegisterPlugin
    *
    * @return array
    */
    public static function dataRegisterPlugin()
    {
        return array(
            array("CriticalErrorCheckPlugin"),
        );
    }

    /**
    * Data provider for testConstructor
    *
    * @return array
    */
    public static function dataConstructor()
    {
        return array(
            array(
                array(
                    "script_gateway" => 1,
                    "admin_email" => "test@dflytech.com",
                    "check" => array(
                        "send_daily" => true,
                    ),
                ),
                array(
                    "id"         => 0x000019,
                    "DeviceID"   => "000019",
                    "HWPartNum"  => "0039-26-00-P",
                    "FWPartNum"  => "0039-26-00-P",
                ),
                true,
                1,
                "test@dflytech.com",
            ),
            array(
                array(
                    "script_gateway" => 1,
                    "admin_email" => "",
                    "check" => array(
                        "send_daily" => true,
                    ),
                ),
                array(
                    "id"         => 0x000019,
                    "DeviceID"   => "000019",
                    "HWPartNum"  => "0039-26-00-P",
                    "FWPartNum"  => "0039-26-00-P",
                ),
                false,
                null,
                "",
            ),
        );
    }
    /**
    * test the constructor
    *
    * @param array  $config     The configuration to use
    * @param array  $device     The device array to use
    * @param bool   $enable     Whether the plugin should be enabled
    * @param int    $gatewayKey The gateway key to expect
    * @param string $to         The 'To' to expect
    *
    * @return null
    *
    * @dataProvider dataConstructor
    */
    public function testConstructor(
        $config, $device, $enable, $gatewayKey, $to
    ) {
        $this->config->forceConfig($config);
        $control = new PeriodicPlugins(array(), $device);
        $o = new CriticalErrorCheckPlugin(array(), $control);
        $this->assertAttributeSame($enable, "enable", $o, "Enable is wrong");
        if ($enable) {
            $error = $this->readAttribute($o, "error");
            $this->assertInternalType("object", $error, "error is not an object");
            $this->assertTrue(is_a($error, "ErrorTable"), "ErrorTable is wrong");
            $this->assertAttributeSame(
                $gatewayKey, "gatewayKey", $o, "Gateway Key is Wrong"
            );
            $subject = $this->readAttribute($o, "_subject");
            $this->assertInternalType("string", $subject);
            $this->assertFalse(empty($subject));
        }
    }
    /**
    * Data provider for testReady
    *
    * @return array
    */
    public static function dataReady()
    {
        return array(
            array(
                time(),
                false,
            ),
            array(
                time()-900,
                true,
            ),
        );
    }
    /**
    * test the constructor
    *
    * @param int  $last   The date to set as the last run
    * @param bool $expect The expected return value
    *
    * @return null
    *
    * @dataProvider dataReady
    */
    public function testReady($last, $expect)
    {
        $o = new CriticalErrorCheckPluginTestStub(array(), $this->control);
        $o->last($last);
        $this->assertSame($expect, $o->ready());
    }
    /**
    * Data provider for testConstructor
    *
    * @return array
    */
    public static function dataMain()
    {
        return array(
            array(
                array(
                    array(
                        "id"     => 1,
                        "class"  => "testClass",
                        "method" => "fakeMethod",
                        "errno"  => 5,
                        "error"  => "This is an error message",
                        "Date"   => 1046397540,
                        "Severity" => ErrorTable::SEVERITY_ERROR,
                    ),
                ),
                "test@dflytech.com",
                "Critical Error on [A-Za-z0-9]+",
                null,
            ),
            array(
                array(
                    array(
                        "id"     => 1,
                        "class"  => "testClass",
                        "method" => "fakeMethod",
                        "errno"  => 5,
                        "error"  => "This is an error message",
                        "Date"   => time(),
                        "Severity" => ErrorTable::SEVERITY_CRITICAL,
                    ),
                ),
                "test@dflytech.com",
                "Critical Error on [A-Za-z0-9]+",
                array(
                    "Last Header" => "CRITICAL ERRORS[ \r\n\t]+"
                        ."[#a-zA-Z0-9\t ]+[\r\n]+",
                    "Error" => "([5]+([\t]*[0-9]{4}-[0-9]{2}-[0-9]{2} "
                        ."[0-9]{2}:[0-9]{2}:[0-9]{2}){1}+[\t]+[A-Za-z0-0:\(\) ]+"
                        ."[\r\n]+)+",
                ),
            ),
        );
    }
    /**
    * test the constructor
    *
    * @param array  $errors  The errors to load
    * @param string $to      Regular expression for the to field
    * @param string $subject Regular expression of the subject
    * @param array  $expect  What to expect
    *
    * @return null
    *
    * @dataProvider dataMain
    */
    public function testMain($errors, $to, $subject, $expect)
    {
        $error = new ErrorTable();
        foreach ($errors as $e) {
            $error->clearData();
            $error->fromAny($e);
            $error->insertRow();
        }
        $ret = $this->o->main();
        if (is_array($expect)) {
            $this->assertRegExp(
                "/".$to."/",
                $ret[0],
                "To is wrong"
            );
            $this->assertRegExp(
                "/".$subject."/",
                $ret[1],
                "Subject is wrong"
            );
            foreach ($expect as $k => $e) {
                $this->assertRegExp(
                    "/".$e."/",
                    $ret[2],
                    "$k is not found"
                );
            }
        } else {
            $this->assertSame($expect, $ret);
        }
    }
}
/**
 * Test class for filter.
 * Generated by PHPUnit_Util_Skeleton on 2007-10-30 at 08:44:56.
 *
 * @category   Devices
 * @package    HUGnetLibTest
 * @subpackage Default
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class CriticalErrorCheckPluginTestStub extends CriticalErrorCheckPlugin
{
    /**
    * Set the last time
    *
    * @param int $value The value to set
    *
    * @return bool True if ready to return, false otherwise
    */
    public function last($value)
    {
        $this->last = (int)$value;
    }
}
?>
