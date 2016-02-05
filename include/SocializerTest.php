<?php
/**
 * This file houses the socket class
 *
 * PHP Version 5
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2012 Hunt Utilities Group, LLC
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA  02110-1301, USA.
 * </pre>
 *
 * @category   Libraries
 * @package    HUGnetLib
 * @subpackage UI
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2012 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */


/** This is the HUGnet namespace */
namespace HUGnet\processes;

/** This is our base class */
require_once "HUGnetLib/ui/Daemon.php";
/** This is our units class */
require_once "HUGnetLib/devices/inputTable/Driver.php";
/** Displays class */
require_once "HUGnetLib/ui/Displays.php";
/** This is the Battery Coach 104603 endpoint test */
require_once "E104603Test.php";
/** This is the Batery Coach 104603 troubleshoot app */
require_once "E104603TroubleShoot.php";
/** This is the 104607 tester **/
require_once "E104607TroubleShoot.php";

/**
 * This code tests, serializes and programs endpoints with bootloader code.
 *
 * This is an endpoint test class, essentially.  It loads an endpoint without
 * test firmware, runs the tests, writes the serial number and hardware version
 * and then programs the bootloader firmware into the endpoint.
 *
 * @category   Libraries
 * @package    HUGnetLib
 * @subpackage UI
 * @author     Scott Price <prices@hugllc.com>
 * @author     Jeff Liesmaki <jeffl@hugllc.com>
 * @copyright  2012 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    Release: 0.9.7
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class SocializerTest extends \HUGnet\ui\Daemon
{

    const HEADER_STR    = "Battery Coach Test & Program Tool";

    private $_fixtureTest;
    private $_fixtureTrbl;
    private $_fixtureTrblTester;
    private $_device;
    private $_goodDevice;
    private $_socialtestMainMenu = array(
                                0 => "Test 104603",
                                1 => "Troubleshoot 104603",
                                2 => "Troubleshoot 104607 Tester",
                                );

    public $display;
    public $sys;
    /*
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config)
    {
        parent::__construct($config);
        $this->_device = $this->system()->device();
        $this->display = \HUGnet\ui\Displays::factory($config);
    }

    /**
    * Creates the object
    *
    * @param array &$config The configuration to use
    *
    * @return null
    */
    static public function &factory(&$config = array())
    {
        $obj = new SocializerTest($config);
        return $obj;
    }

    /**
    ************************************************************
    *
    *                          M A I N 
    *
    ************************************************************
    *
    * It would be nice to have a test fixture ID test to verify
    * that the fixture matches the menu selection.
    * 
    * @return null
    */
    public function main()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_socialtestMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_test104603Main();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshoot104603Main();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_troubleshoot104607Main();
            } else {
                $exitTest = true;
                $this->out("Exit Test & Troubleshoot Tool");
            }

        } while ($exitTest == false);
    }




    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*****************************************************************************/

 
    /**
    ************************************************************
    * 104603 Main Test Routine
    * 
    * This is the main routine for testing, serializing and 
    * programming in the bootloader for HUGnet endpoints.
    *
    * @return void
    *   
    */
    private function _test104603Main()
    {
        $sys = $this->system();
        $this->_fixtureTest = E104603Test::factory($config, $sys);
        $this->_fixtureTest->run104603Test();

    }

   
    /**
    ************************************************************
    * Main Clone Routine
    *
    * This is the main routine for testing, serializing and 
    * programming the 003912 endpoint.  
    * 
    * @return void
    *
    */
    private function _troubleshoot104603Main()
    {
        $sys = $this->system();
        $this->_fixtureTrbl= E104603Troubleshoot::factory($config, $sys);
        $this->_fixtureTrbl->runTroubleshootMain();
    }

    /**
    ************************************************************
    * Main Troubleshoot Routine
    *
    * This is the main routine for testing, serializing and 
    * programming the 003912 endpoint.  
    * 
    * @return void
    *
    */
    private function _troubleshoot104607Main()
    {

        $sys = $this->system();
        $this->_fixtureTrblTester= E104607Troubleshoot::factory($config, $sys);
        $this->_fixtureTrblTester->runTrblshtTesterMain();
 
    }



}
?>
