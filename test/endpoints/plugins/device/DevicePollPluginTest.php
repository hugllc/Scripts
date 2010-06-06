<?php
/**
 * Tests the filter class
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2010 Hunt Utilities Group, LLC
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
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */

/** Get our classes */
require_once dirname(__FILE__).'/DeviceProcessPluginTestBase.php';
require_once dirname(__FILE__)
    .'/../../../../endpoints/plugins/device/DevicePollPlugin.php';
require_once HUGNET_INCLUDE_PATH."/processes/DeviceProcess.php";
/**
 * Test class for filter.
 * Generated by PHPUnit_Util_Skeleton on 2007-10-30 at 08:44:56.
 *
 * @category   Devices
 * @package    HUGnetLibTest
 * @subpackage Default
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DevicePollPluginTest extends DeviceProcessPluginTestBase
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
            "sockets" => array(
                array(
                    "dummy" => true,
                ),
            ),
            "script_gateway" => 1,
            "admin_email" => "test@dflytech.com",
            "poll" => array(
                "enable" => true,
            ),
            "test" => true,
        );
        $this->config = &ConfigContainer::singleton();
        $this->config->forceConfig($config);
        $this->config->sockets->forceDeviceID("000019");
        $this->socket = &$this->config->sockets->getSocket("default");
        $this->pdo = &$this->config->servers->getPDO();
        $this->device = array(
            "id"         => 0x000019,
            "DeviceID"   => "000019",
            "HWPartNum"  => "0039-26-00-P",
            "FWPartNum"  => "0039-26-00-P",
        );

        $this->control = new DeviceProcess(array(), $this->device);
        $this->o = new DevicePollPlugin(array(), $this->control);
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
            array("DevicePollPlugin"),
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
                    "poll" => array(
                        "enable" => true,
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
                    "poll" => array(
                        "enable" => false,
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
        $control = new DeviceProcess(array(), $device);
        $o = new DevicePollPlugin(array(), $control);
        $this->assertAttributeSame($enable, "enable", $o, "Enable is wrong");
        if ($enable) {
            $this->assertAttributeSame(
                $gatewayKey, "gatewayKey", $o, "Gateway Key is Wrong"
            );
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
                array(
                    "id" => 0x000021,
                    "DeviceID" => "000021",
                    "PollInterval" => 1,
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => 0,
                        ),
                    ),
                ),
                true,
            ),
            array(
                array(
                    "id" => 0x000021,
                    "DeviceID" => "000021",
                    "PollInterval" => 100,
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => time(),
                        ),
                    ),
                ),
                false,
            ),
        );
    }
    /**
    * test the constructor
    *
    * @param array $dev    The device to send
    * @param bool  $expect The expected return value
    *
    * @return null
    *
    * @dataProvider dataReady
    */
    public function testReady($dev, $expect)
    {
        $d = new DeviceContainer($dev);
        $this->assertSame($expect, $this->o->ready($d));
    }

    /**
    * data provider for testPacketConsumer
    *
    * @return array
    */
    public static function dataPacketConsumer()
    {
        return array(
            array(
                array(
                    "sockets" => array(
                        array(
                            "dummy" => true,
                        ),
                    ),
                    "script_gateway" => 1,
                    "admin_email" => "test@dflytech.com",
                    "poll" => array(
                        "enable" => true,
                    ),
                    "test" => true,
                ),
                array(
                    "DeviceName" => "Hello",
                    "id" => 0x123456,
                    "DeviceID" => "123456",
                    "GatewayKey" => 3,
                    "HWPartNum" => "0039-28-01-A",
                    "FWPartNum" => "0039-20-13-C",
                    "FWVersion" => "1.2.3",
                    "Active" => 0,
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => time(),
                            "PollFail" => 23,
                            "LastPollTry" => time(),
                        ),
                    ),
                ),
                0x123456,
                array(
                    "To" => "000000",
                    "From" => "123456",
                    "Command" => PacketContainer::COMMAND_POWERUP,
                    "group" => "default",
                ),
                array(
                    "DriverInfo" => array(
                        "TimeConstant" => 0,
                    ),
                    "id"         => 0x123456,
                    "DeviceID"          => "123456",
                    "DeviceName"        => "Hello",
                    "HWPartNum" => "0039-28-01-A",
                    "FWPartNum" => "0039-20-13-C",
                    "FWVersion" => "1.2.3",
                    "RawSetup"=> "000012345600392801410039201343010203FFFFFF00",
                    "Active"            => "1",
                    "GatewayKey"        => "1",
                    "ControllerKey"     => "0",
                    "ControllerIndex"   => "0",
                    "Driver" => "e00392800",
                    "PollInterval"      => "0",
                    "ActiveSensors"     => "0",
                    "sensors"           => array(),
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => 0,
                            "PollFail" => 0,
                            "LastPollTry" => 0,
                        ),
                    ),
                ),
                "",
            ),
            array(
                array(
                    "sockets" => array(
                        array(
                            "dummy" => true,
                        ),
                    ),
                    "script_gateway" => 1,
                    "admin_email" => "test@dflytech.com",
                    "poll" => array(
                        "enable" => false,
                    ),
                    "test" => true,
                ),
                array(
                    "DeviceName" => "Hello",
                    "id" => 0x123456,
                    "DeviceID" => "123456",
                    "GatewayKey" => 3,
                    "HWPartNum" => "0039-28-01-A",
                    "FWPartNum" => "0039-20-13-C",
                    "FWVersion" => "1.2.3",
                    "Active" => 0,
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => 1275689023,
                            "PollFail" => 23,
                            "LastPollTry" => 1275689023,
                        ),
                    ),
                ),
                0x123456,
                array(
                    "To" => "000000",
                    "From" => "123456",
                    "Command" => PacketContainer::COMMAND_POWERUP,
                    "group" => "default",
                ),
                array(
                    "DriverInfo" => array(
                        "TimeConstant" => 0,
                    ),
                    "id"         => 0x123456,
                    "DeviceID"          => "123456",
                    "DeviceName"        => "Hello",
                    "HWPartNum" => "0039-28-01-A",
                    "FWPartNum" => "0039-20-13-C",
                    "FWVersion" => "1.2.3",
                    "RawSetup"=> "000012345600392801410039201343010203FFFFFF00",
                    "Active"            => "0",
                    "GatewayKey"        => "3",
                    "ControllerKey"     => "0",
                    "ControllerIndex"   => "0",
                    "Driver" => "e00392800",
                    "PollInterval"      => "0",
                    "ActiveSensors"     => "0",
                    "sensors"           => array(),
                    "params" => array(
                        "DriverInfo" => array(
                            "LastPoll" => 1275689023,
                            "PollFail" => 23,
                            "LastPollTry" => 1275689023,
                        ),
                    ),
                ),
                "",
            ),
            array(
                array(
                    "sockets" => array(
                        array(
                            "dummy" => true,
                        ),
                    ),
                    "script_gateway" => 1,
                    "admin_email" => "test@dflytech.com",
                    "poll" => array(
                        "enable" => true,
                    ),
                    "test" => true,
                ),
                array(
                ),
                0x123456,
                array(
                    "To" => "000000",
                    "From" => "123456",
                    "Command" => "5C",
                    "group" => "default",
                ),
                array(
                    "DriverInfo" => array(
                        "TimeConstant" => 0,
                    ),
                    "id" => 0,
                    "RawSetup" => "000000000000000000000000000000000000FFFFFF00",
                    "sensors" => array(),
                    "params" => array(),
                ),
                "",
            ),
        );
    }

    /**
    * test the set routine when an extra class exists
    *
    * @param array  $config  The config to use
    * @param array  $preload The data to preload into the devices table
    * @param string $id      The id of the device to check
    * @param string $pkt     The packet string to use
    * @param string $expect  The expected return
    *
    * @return null
    *
    * @dataProvider dataPacketConsumer
    */
    public function testPacketConsumer($config, $preload, $id, $pkt, $expect)
    {
        $this->config->forceConfig($config);
        $control = new DeviceProcess(array(), $this->device);
        $o = new DevicePollPlugin(array(), $control);
        $pdo = &$this->config->servers->getPDO();
        $d = new DeviceContainer($preload);
        $d->insertRow(true);

        $p = new PacketContainer($pkt);
        $o->packetConsumer($p);
        $stmt = $pdo->query("SELECT * FROM `devices` WHERE `id`=".$id);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $d->clearData();
        $d->fromArray($rows[0]);
        $this->assertSame($expect, $d->toArray(false));
    }

    /**
    * data provider for testMain
    *
    * @return array
    */
    public static function dataMain()
    {
        return array(
            array(
                array(
                    "id" => hexdec("123456"),
                    "DeviceID" => "123456",
                    "GatewayKey" => 1,
                    "PollInterval" => 10,
                ),
                (string)new PacketContainer(array(
                    "From" => "123456",
                    "To" => "000019",
                    "Command" => PacketContainer::COMMAND_REPLY,
                    "Data" => "000012345600391101410039201343000009FFFFFF50",
                )),
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_GETDATA,
                    "Data" => "",
                )),
            ),
            array(
                array(
                ),
                "",
                "",
            ),
            array(
                array(
                    "id" => hexdec("123456"),
                    "DeviceID" => "123456",
                    "HWPartNum" => "0039-21-01-A",
                    "FWPartNum" => "0039-20-01-C",
                    "FWVersion" => "1.2.3",
                    "GatewayKey" => 1,
                    "PollInterval" => 10,
                ),
                (string)new PacketContainer(array(
                    "From" => "123456",
                    "To" => "000019",
                    "Command" => PacketContainer::COMMAND_REPLY,
                    "Data" => "000012345600392101410039200143000009FFFFFF50",
                )),
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_GETDATA,
                    "Data" => "",
                )),
            ),
            array(
                array(
                    "id" => hexdec("123456"),
                    "DeviceID" => "123456",
                    "HWPartNum" => "0039-21-01-A",
                    "FWPartNum" => "0039-20-01-C",
                    "FWVersion" => "1.2.3",
                    "GatewayKey" => 1,
                    "PollInterval" => 10,
                    "params" => array(
                        "DriverInfo" => array(
                            "PollFail" => 29,
                        ),
                    ),
                ),
                "",
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_GETDATA,
                    "Data" => "",
                )).
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_GETDATA,
                    "Data" => "",
                )).
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_FINDECHOREQUEST,
                    "Data" => "",
                )).
                (string)new PacketContainer(array(
                    "To" => "123456",
                    "From" => "000019",
                    "Command" => PacketContainer::COMMAND_GETDATA,
                    "Data" => "",
                )),
            ),
        );
    }

    /**
    * test the set routine when an extra class exists
    *
    * @param array  $preload The data to preload into the devices table
    * @param string $read    The read string for the socket
    * @param string $expect  The expected return
    *
    * @return null
    *
    * @dataProvider dataMain
    */
    public function testMain($preload, $read, $expect)
    {
        $d = new DeviceContainer($preload);
        $this->socket->readString = $read;
        $this->o->main($d);
        $this->assertSame($expect, $this->socket->writeString);
    }

}
?>
