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
require_once 'SyncPluginTestBase.php';
require_once SCRIPTS_CODE_BASE.'endpoints/plugins/sync/DevicesTableSyncPlugin.php';
require_once HUGNET_INCLUDE_PATH."/processes/PeriodicPlugins.php";
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
class DevicesTableSyncPluginTest extends SyncPluginTestBase
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
            "servers" => array(
                array(
                    "driver" => "sqlite",
                    "file" => ":memory:",
                    "group" => "default",
                ),
                array(
                    "driver" => "sqlite",
                    "file" => ":memory:",
                    "group" => "remote",
                ),
            ),
            "script_gateway" => 1,
        );
        $this->config = &ConfigContainer::singleton();
        $this->config->forceConfig($config);
        $this->socket = &$this->config->sockets->getSocket("default");
        $this->pdo = &$this->config->servers->getPDO("default");
        $this->rpdo = &$this->config->servers->getPDO("remote");
        $this->device = array(
            "id"         => 0xFE0019,
            "DeviceID"   => "FE0019",
            "HWPartNum"  => "0039-26-00-P",
            "FWPartNum"  => "0039-26-00-P",
            "ControllerKey"  => 19,
        );

        $this->control = new PeriodicPlugins(array(), $this->device);
        $this->o = new DevicesTableSyncPlugin(array(), $this->control);
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
            array("DevicesTableSyncPlugin"),
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
                    "pluginData" => array(
                        "DevicesTableSyncPlugin" => array(
                            "enable" => false,
                        ),
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
        $o = new DevicesTableSyncPlugin(array(), $control);
        $this->assertAttributeSame($enable, "enable", $o, "Enable is wrong");
        if ($enable) {
            $dev = $this->readAttribute($o, "device");
            $this->assertInternalType("object", $dev, "device is not an object");
            $this->assertTrue(
                is_a($dev, "DeviceContainer"), "device Class is wrong"
            );
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
        $o = new DevicesTableSyncPluginTestStub(array(), $this->control);
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
        $dev = new DeviceContainer();
        return array(
            array( // #0 Nothing to do at all
                array(
                ),
                array(
                ),
                array(
                ),
                array(
                ),
                array(
                ),
            ),
            array( // #1 New devices both remote->local and local->remote
                array(
                    array(
                        "id" => 0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                        ),
                    ),
                ),
                array(
                    array(
                        "id" => 0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                        ),
                    ),
                ),
                array(
                ),
                array(
                    array(
                        "id" => (string)0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011900391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                            ),
                            $dev
                        ),
                    ),
                    array(
                        "id" => (string)0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011A00391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                            ),
                            $dev
                        ),
                    ),
                ),
                array(
                    array(
                        "id" => (string)0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011900391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                            ),
                            $dev
                        ),
                    ),
                    array(
                        "id" => (string)0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011A00391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                            ),
                            $dev
                        ),
                    ),
                ),
            ),
            array( // #2 Two devices to update, 1 locked, one not
                array(
                    array(
                        "id" => 0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-22-01-A",
                        "FWPartNum" => "0039-20-01-C",
                        "FWVersion" => "0.7.1",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "5",
                        "ControllerIndex" => "1",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                            "LastModified" => 0,
                            "DriverInfo" => array(
                                "LastConfig" => 10,
                                "LastPoll" => 11,
                                "LastContact" => 12,
                            ),
                        ),
                    ),
                    array(
                        "id" => 0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                            "LastModified" => 1,
                            "DriverInfo" => array(
                                "LastConfig" => 9,
                                "LastPoll" => 10,
                                "LastContact" => 13
                            ),
                        ),
                    ),
                ),
                array(
                    array(
                        "id" => 0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                            "LastModified" => 0,
                            "DriverInfo" => array(
                                "LastConfig" => 10,
                                "LastPoll" => 11,
                                "LastContact" => 12,
                            ),
                        ),
                    ),
                    array(
                        "id" => 0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "Name",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "DriverInfo" => array(
                        ),
                        "RawSetup" => "",
                        "Active" => "0",
                        "GatewayKey" => 1,
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "Location",
                        "DeviceJob" => "Job",
                        "Driver" => "e00391200",
                        "PollInterval" => "10",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => array(
                            "forceSensors" => true,
                            "Sensors" => 2,
                            "PhysicalSensors" => 2,
                        ),
                        "params" => array(
                            "LastModified" => 1,
                            "DriverInfo" => array(
                                "LastConfig" => 9,
                                "LastPoll" => 10,
                                "LastContact" => 13
                            ),
                        ),
                    ),
                ),
                array(
                    array(
                        "id" => 0xFE0019,
                        "type" => "device",
                        "lockData" => "000119",
                        "expiration" => 10000000000000, // Way in the future
                    ),
                ),
                array(
                    array(
                        "id" => (string)0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "Name",
                        "HWPartNum" => "0039-22-01-A",
                        "FWPartNum" => "0039-20-01-C",
                        "FWVersion" => "0.7.1",
                        "RawSetup" => "000000011900392201410039200143000701FFFFFF00",
                        "Active" => "0",
                        "GatewayKey" => "1",
                        "ControllerKey" => "5",
                        "ControllerIndex" => "1",
                        "DeviceLocation" => "Location",
                        "DeviceJob" => "Job",
                        "Driver" => "eDEFAULT",
                        "PollInterval" => "10",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                                "LastModified" => 1,
                                "DriverInfo" => array(
                                    "LastConfig" => 10,
                                    "LastPoll" => 11,
                                    "LastContact" => 12,
                                ),
                            ),
                            $dev
                        ),
                    ),
                    array(
                        "id" => (string)0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011A00391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                                "LastModified" => 1,
                                "DriverInfo" => array(
                                    "LastConfig" => 10,
                                    "LastPoll" => 11,
                                    "LastContact" => 13,
                                ),
                            ),
                            $dev
                        ),
                    ),
                ),
                array(
                    array(
                        "id" => (string)0x119,
                        "DeviceID" => "000119",
                        "DeviceName" => "Name",
                        "HWPartNum" => "0039-22-01-A",
                        "FWPartNum" => "0039-20-01-C",
                        "FWVersion" => "0.7.1",
                        "RawSetup" => "000000011900392201410039200143000701FFFFFF00",
                        "Active" => "0",
                        "GatewayKey" => "1",
                        "ControllerKey" => "5",
                        "ControllerIndex" => "1",
                        "DeviceLocation" => "Location",
                        "DeviceJob" => "Job",
                        "Driver" => "eDEFAULT",
                        "PollInterval" => "10",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                                "LastModified" => 1,
                                "DriverInfo" => array(
                                    "LastConfig" => 10,
                                    "LastPoll" => 11,
                                    "LastContact" => 13,
                                ),
                            ),
                            $dev
                        ),
                    ),
                    array(
                        "id" => (string)0x11A,
                        "DeviceID" => "00011A",
                        "DeviceName" => "",
                        "HWPartNum" => "0039-12-02-A",
                        "FWPartNum" => "0039-20-03-C",
                        "FWVersion" => "0.7.0",
                        "RawSetup" => "000000011A00391202410039200343000700FFFFFF00",
                        "Active" => "1",
                        "GatewayKey" => "1",
                        "ControllerKey" => "0",
                        "ControllerIndex" => "0",
                        "DeviceLocation" => "",
                        "DeviceJob" => "",
                        "Driver" => "e00391200",
                        "PollInterval" => "0",
                        "ActiveSensors" => "0",
                        "DeviceGroup" => "FFFFFF",
                        "sensors" => (string)new DeviceSensorsContainer(
                            array(
                                "forceSensors" => true,
                                "Sensors" => 2,
                                "PhysicalSensors" => 2,
                            ),
                            $dev
                        ),
                        "params" => (string)new DeviceParamsContainer(
                            array(
                                "LastModified" => 0,
                                "DriverInfo" => array(
                                    "LastConfig" => 10,
                                    "LastPoll" => 11,
                                    "LastContact" => 12,
                                ),
                            ),
                            $dev
                        ),
                    ),
                ),
            ),
        );
    }
    /**
    * test the constructor
    *
    * @param array $local        The local devices to load
    * @param array $remote       The remote devices to load
    * @param array $locks        The locks to load
    * @param array $localExpect  The expected local devices
    * @param array $remoteExpect The expected local devices
    *
    * @return null
    *
    * @dataProvider dataMain
    */
    public function testMain($local, $remote, $locks, $localExpect, $remoteExpect)
    {
        $last = time();
        $this->pdo->query("DELETE FROM `devices`");
        $this->rpdo->query("DELETE FROM `devices`");
        $d = new DeviceContainer();
        foreach ($local as $dev) {
            $d->clearData();
            $d->fromAny($dev);
            $d->insertRow(true);
        }
        $r = new DeviceContainer(array("group" => "remote"));
        foreach ($remote as $rdev) {
            $r->clearData();
            $r->fromAny($rdev);
            $r->insertRow(true);
        }
        $lock = new LockTable();
        foreach ((array)$locks as $key => $val) {
            $lock->clearData();
            $lock->fromAny($val);
            $lock->insertRow(true);
        }
        $ret = $this->o->main();
        $stmt = $this->pdo->query("SELECT * FROM `devices`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame($localExpect, $rows, "Local Wrong");
        $stmt = $this->rpdo->query("SELECT * FROM `devices`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame($remoteExpect, $rows, "Remote Wrong");
        $ret = $this->readAttribute($this->o, "last");
        $this->assertGreaterThanOrEqual($last, $ret, "Last set wrong");

    }
    /**
    * test the constructor
    *
    * @return null
    */
    public function testMainNoRemote()
    {
        $config = array(
            "servers" => array(
                array(
                    "driver" => "sqlite",
                    "file" => ":memory:",
                    "group" => "default",
                ),
                // No remote server
            ),
            "script_gateway" => 1,
        );
        $this->config = &ConfigContainer::singleton();
        $this->config->forceConfig($config);
        $o = new DevicesTableSyncPlugin(array(), $this->control);
        $ret = $o->main();
        // If it didn't run there will be no 'last'
        $this->assertAttributeSame(0, "last", $o);
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
class DevicesTableSyncPluginTestStub extends DevicesTableSyncPlugin
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