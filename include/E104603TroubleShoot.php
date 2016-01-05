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
 * @copyright  2015 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */


/** This is the HUGnet namespace */
namespace HUGnet\processes;
use \HUGnetLib as HUGnetLib;

/** This is our base class */
require_once "HUGnetLib/ui/Daemon.php";
/** This is our units class */
require_once "HUGnetLib/devices/inputTable/Driver.php";
/** This is needed */
require_once "HUGnetLib/devices/inputTable/DriverAVR.php";
/** Displays class */
require_once "HUGnetLib/ui/Displays.php";
/** Test Class */
require_once "E104603Test.php";

/**
 * This code tests, serializes and programs battery socializer endpoints 
 *       with bootloader and application firmware.
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
 * @copyright  2015 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    Release: 0.9.7
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class E104603TroubleShoot extends E104603Test
{

    private $_fixtureTest;
    private $_system;
    private $_device;
    private $_evalDevice;

    const HEADER_STR    = "Battery Coach Troubleshoot & Program Tool";

    private $_eptroubleMainMenu = array(
                                0 => "Troubleshoot with Test Firmware",
                                1 => "Troubleshoot with Application Firmware",
                                );
    
    private $_eptroubleTestMenu = array(
                                0  => "UUT Power Up",
                                1  => "Load Test Firmware",
                                2  => "Port 1",
                                3  => "Port 2",
                                4  => "Port 1 Fault",
                                5  => "Port 2 Fault",
                                6  => "Port 1 to VBus",
                                7  => "Port 2 to VBus",
                                8  => "External Thermistor Connections",
                                9  => "Read Micro SN",
                                10 => "Read User Signature",
                                );

    private $_eptroubleAppMenu = array(
                                0 => "UUT Power Up",
                                1 => "Read Calibration Values",
                                2 => "Port 1",
                                3 => "Port 2",
                                );
                                
    public $display;

    

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        parent::__construct($config, $sys);
        $this->_system = &$sys; 
        $this->_device = $this->_system->device();
        $this->_evalDevice = $this->_system->device();
        $this->_evalDevice->set("id", self:: EVAL_BOARD_ID);
        $this->_evalDevice->set("Role","TesterEvalBoard");

        $this->display = \HUGnet\ui\Displays::factory($config);
    }

    /**
    * Creates the object
    *
    * @param array &$config The configuration to use
    *
    * @return null
    */
    static public function &factory(&$config = array(), &$sys)
    {
        $obj = new E104603TroubleShoot($config, $sys);
        return $obj;
    }

    /**
    *****************************************************************************
    *
    *                  T R O U B L E S H O O T    M A I N 
    *
    *****************************************************************************
    *
    * It would be nice to have a test fixture ID test to verify
    * that the fixture matches the menu selection.
    * 
    * @return null
    */
    public function runTroubleshootMain()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_runTroubleshootTest();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_runTroubleshootApp();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting Tool");
            }

        } while ($exitTest == false);

        $choice = readline("\n\rHit Enter to Continue: ");

    }




    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*          T R O U B L E S H O O T   T E S T   R O U T I N E S              */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

    /**
    *************************************************************************
    * Troubleshoot with Test Firmware Routine
    * 
    * This function runs the menu and calls functions for troubleshooting
    * an endpoint that either has test firmware loaded or will have the 
    * test firmware loaded for troubleshooting.
    */
    private function _runTroubleshootTest()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleTestMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_trblshtPwrUp();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_trblshtLoadFirmware();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_trblshtPort1();
            } else if (($selection == "D") || ($selection == "d")){
                $this->_trblshtPort2();
            } else if (($selection == "E") || ($selection == "e")){
                $this->_trblshtP1Fault();
            } else if (($selection == "F") || ($selection == "f")){
                $this->_trblshtP2Fault();
            } else if (($selection == "G") || ($selection == "g")){
                $this->_trblshtVBusP1();
            } else if (($selection == "H") || ($selection == "h")){
                $this->_trblshtVBusP2();
            } else if (($selection == "I") || ($selection == "i")){
                $this->_trblshtExtTherms();
            } else if (($selection == "J") || ($selection == "j")){
                $this->_trblshtReadMicroSN();
            } else if (($selection == "K") || ($selection == "k")){
                $this->_trblshtReadUserSig();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting Test");
            }

        } while ($exitTest == false);
    }
 


    /**
    ************************************************************
    * Troubleshoot Power Up Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPwrUp()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        
        $this->_powerUUT(self::ON);
        
        $choice = readline("\n\rIs test firmware loaded (Y/N): ");

        if (($choice == 'Y') || ($choice == 'y')) {
            $voltsVB = $this->_readTesterBusVolt();
            $voltsVcc = $this->_readTesterVCC();
            $this->_system->out("");
            
            If (($voltsVB > 11.5) and ($voltsVB < 13.00)) {
                $this->_system->out("Bus Voltage is within range");
                
                if (($voltsVcc > 3.0) and ($voltsVcc < 3.4)) {
                    $this->_system->out("Vcc is within range");
                } else {
                    $this->_system->out("Vcc is out of range");
                    $this->_system->out("Scope out power supply circuit");
                }
            } else {
                $this->_system->out("Bus Voltage out of range");
                $this->_system->out("Scope out Bus Voltage Circuit");
            }
        } else {
            $this->_system->out("Use Load Test Firmware item to make ");
            $this->_system->out("Bus and VCC Voltage Measurements available");
        }

        
        
        $choice = readline("\n\rTake Measurements and Hit Enter to Exit: ");
        

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }


    
    /**
    ************************************************************
    * Troubleshoot Load Firmware Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtLoadFirmware()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);
        
        $result = $this->_loadTestFirmware();
        
        if ($result == self::PASS) {
            $this->_system->out("Test Firmware Loaded");
        } else {
            $this->_system->out("If load firmware fails, first verify the programmer connections.");
            $this->_system->out("If connections are good, check signal to microcontroller.");
            $this->_system->out("If signal is good, it may be a bad microcontroller.");
        }
   
        $choice = readline("\n\rHit Enter to Exit: ");
        
        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
        
        
    }

    /**
    ************************************************************
    * Troubleshoot Port 1 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPort1()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort1Load(self::ON);

        $this->_system->out("Port 1 Load connected!");
        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();

        if ($p1Volts < 0.2) {
            $this->_system->out("Port 1 off test pass!");
        } else {
            $this->_system->out("Port 1 off test fail!");
            $this->_system->out("Check upper FET for Short");
        }

        $this->_setPort1(self::ON);
        $this->_system->out("Port 1 turned on.\n\r");

        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();

        $this->_system->out("\n\rScope PWM output and measure voltages.");
        $this->_system->out("");

        $choice = readline("Hit Enter to Continue");

        
        $this->_setPort1(self::OFF);
        $this->_setPort1Load(self::OFF); /* Disconnect Port 1 Load */


        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ************************************************************
    * Troubleshoot Port 2 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPort2()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort2Load(self::ON);

        $this->_system->out("Port 2 Load connected!");
        $voltsP2 = $this->_readTesterP2Volt(); 
        $p2Volts = $this->_readUUTPort2Volts();
        $p2Amps = $this->_readUUTPort2Current();

        if ($p2Volts < 0.2) {
            $this->_system->out("Port 2 off test pass!");
        } else {
            $this->_system->out("Port 2 off test fail!");
            $this->_system->out("Check upper FET for Short");
        }

        $this->_setPort2(self::ON);
        $this->_system->out("Port 2 turned on.\n\r");

        $voltsP2 = $this->_readTesterP2Volt(); 
        $p2Volts = $this->_readUUTPort2Volts();
        $p2Amps = $this->_readUUTPort2Current();

        $this->_system->out("\n\rScope PWM output and measure voltages.");
        $this->_system->out("");

        $choice = readline("Hit Enter to Continue");

        
        $this->_setPort2(self::OFF);
        $this->_setPort2Load(self::OFF); /* Disconnect Port 1 Load */


        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }
    
    /**
    ************************************************************
    * Troubleshoot Port 1 Fault Routine
    *
    * This function connects the load to Port 1, turns on the
    * port, verifies that there is voltage on the port and 
    * allows the user to set and remove the fault signal to 
    * troubleshoot the fault circuit.
    */
    private function _trblshtP1Fault()
    {
        $this->_system->out("");
        $this->display->displaySMHeader("Troubleshooting Port 1 Fault");
        
        $this->_system->out("  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort1Load(self::ON);

        $this->_system->out("Port 1 Load connected!");
        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();

        if ($p1Volts < 0.2) {
        
            $this->_setPort1(self::ON);
            $this->_system->out("Port 1 turned on.\n\r");

            $voltsP1 = $this->_readTesterP1Volt(); 
            $p1Volts = $this->_readUUTPort1Volts();
            $p1Amps = $this->_readUUTPort1Current();
            
            if (($p1Volts > 11.00) and ($p1Volts < 13.00)) {

                $this->_system->out("PORT 1 FAULT ON:");
                $this->_faultSet(1, 1); /* Set fault */
                sleep(1);

                /* Measure Port 1 voltage */
                $voltsP1 = $this->_readTesterP1Volt();
                $this->_system->out("");
                $this->_system->out("Port 1 voltage should be < 0.1 volts");
                $this->_system->out("Scope out circuit to verify.");
                $choice = readline("Hit Enter to Continue:");

                $this->_system->out("");
                $this->_system->out("PORT 1 FAULT OFF:");
                $this->_faultSet(1, 0); /* Remove fault */
                sleep(1);

                $voltsP1 = $this->_readTesterP1Volt();
                $this->_system->out("");
                $this->_system->out("Port 1 voltage should be > 11.00 volts");
                $this->_system->out("Scope out circuit to verify.");
                $choice = readline("Hit Enter to Continue:");
                $this->_system->out("");
            } else {
                $this->_system->out("Port 1 on fail!");
            }
            
            $this->_setPort1(self::OFF);
            $this->_system->out("Port 1 turned off");
        
         } else {
            $this->_system->out("Port 1 off fail!");
            $this->_system->out("Check upper FET for Short");
        }
        
        $this->_setPort1Load(self::OFF);
        $this->_system->out("Port 1 Load Disconnected!");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    
    }

    /**
    ************************************************************
    * Troubleshoot Port 2 Fault Routine
    *
    * This function connects the load to Port 2, turns on the
    * port, verifies that there is voltage on the port and 
    * allows the user to set and remove the fault signal to 
    * troubleshoot the fault circuit.
    */
    private function _trblshtP2Fault()
    {
        $this->_system->out("");
        $this->display->displaySMHeader("Troubleshooting Port 2 Fault");
        
        $this->_system->out("  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort2Load(self::ON);

        $this->_system->out("Port 2 Load connected!");
        $voltsP2 = $this->_readTesterP2Volt(); 
        $p2Volts = $this->_readUUTPort2Volts();
        $p2Amps = $this->_readUUTPort2Current();

        if ($p2Volts < 0.2) {
        
            $this->_setPort2(self::ON);
            $this->_system->out("Port 2 turned on.\n\r");

            $voltsP2 = $this->_readTesterP2Volt(); 
            $p2Volts = $this->_readUUTPort2Volts();
            $p2Amps = $this->_readUUTPort2Current();
            
            if (($p2Volts > 11.00) and ($p2Volts < 13.00)) {

                $this->_system->out("PORT 2 FAULT ON:");
                $this->_faultSet(2, 1); /* Set fault */
                sleep(1);

                /* Measure Port 2 voltage */
                $voltsP2 = $this->_readTesterP2Volt();
                $this->_system->out("");
                $this->_system->out("Port 2 voltage should be < 0.1 volts");
                $this->_system->out("Scope out circuit to verify.");
                $choice = readline("Hit Enter to Continue:");

                $this->_system->out("");
                $this->_system->out("PORT 2 FAULT OFF:");
                $this->_faultSet(2, 0); /* Remove fault */
                sleep(1);

                $voltsP2 = $this->_readTesterP2Volt();
                $this->_system->out("");
                $this->_system->out("Port 2 voltage should be > 11.00 volts");
                $this->_system->out("Scope out circuit to verify.");
                $choice = readline("Hit Enter to Continue:");
                $this->_system->out("");
            } else {
                $this->_system->out("Port 2 on fail!");
            }
            
            $this->_setPort2(self::OFF);
            $this->_system->out("Port 2 turned off");
        
         } else {
            $this->_system->out("Port 2 off fail!");
            $this->_system->out("Check upper FET for Short");
        }
        
        $this->_setPort2Load(self::OFF);
        $this->_system->out("Port 2 Load Disconnected!");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    
    }
    
    
    /**
    ************************************************************
    * Troubleshoot Port 1 to VBus Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtVBusP1()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_system->out("Troubleshoot Port 1 to VBUS");
        
        $this->_setPort1_V12(self::ON); /* +12V to Port 1 */
        $voltsP1 = $this->_readTesterP1Volt();

        if (($voltsP1 > 11.50) and ($voltsP1 < 13.00)) { 
            $this->_setVBus_V12(self::OFF); /* connects 12 ohm load */
            $voltsVB = $this->_readTesterBusVolt();
            $VBvolts = $this->_readUUTBusVolts();
            $tVolts = $this->_readTesterP1Volt();

            $choice = readline("\n\rHit Enter to Continue: ");

            if ($VBvolts < 0.2) {
                $this->_setPort1(self::ON);
                sleep(1);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $p1Volts = $this->_readUUTPort1Volts();
                $p1Amps = $this->_readUUTPort1Current();
                $choice = readline("\n\rHit Enter to Continue: ");


                $this->_setPort1(self::OFF);
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Port 1 to Vbus Troubleshoot Complete");
                $this->_setPort1_V12(self::OFF);
            } else {
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Bus Voltage Off:".$VBvolts."V");
                $this->_setPort1_V12(self::OFF);
            }

        } else {
            $this->_setPort1_V12(self::OFF);
            $voltsP1 = $this->_readTesterP1Volt();
            $this->_system->out("Port 1 Supply Failed!");
            $this->_system->out("Port 1  Tester = ".$voltsP1." volts");
        }
            
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

     /**
    ************************************************************
    * Troubleshoot Port 2 to VBus Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtVBusP2()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_system->out("Troubleshoot Port 2 to VBUS");

        $this->_setPort2_V12(self::ON); /* +12V to Port 2 */
        $voltsP2 = $this->_readTesterP2Volt();

        if (($voltsP2 > 11.50) and ($voltsP2 < 13.00)) { 
            $this->_setVBus_V12(self::OFF); /* connects 12 ohm load */
            $voltsVB = $this->_readTesterBusVolt();
            $VBvolts = $this->_readUUTBusVolts();
            $tVolts = $this->_readTesterP2Volt();

            $choice = readline("\n\rHit Enter to Continue: ");

            if ($VBvolts < 0.2) {
                $this->_setPort2(self::ON);
                sleep(1);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $p2volts = $this->_readUUTPort2Volts();
                $p2Amps = $this->_readUUTPort2Current();
                $choice = readline("\n\rHit Enter to Continue: ");


                $this->_setPort2(self::OFF);
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Port 2 to VBus Troubleshoot Complete");
                $this->_setPort2_V12(self::OFF);
            } else {
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Bus Voltage Off:".$VBvolts."V");
                $this->_setPort2_V12(self::OFF);
            }

        } else {
            $this->_setPort2_V12(self::OFF);
            $voltsP2 = $this->_readTesterP2Volt();
            $this->_system->out("Port 2 Supply Failed!");
            $this->_system->out("Port 2  Tester = ".$voltsP2." volts");
        }
            
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

   /**
    ************************************************************
    * Troubleshoot Read Micro Serial Number Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtReadMicroSN()
    {
    
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $MicroSN = $this->_readMicroSN();
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ************************************************************
    * Troubleshoot 7 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtExtTherms()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);
        
        $this->_system->out("Testing UUT External Thermistor Circuits");
        $this->_system->out("****************************************");
        $this->_system->out("EXT THERM CIRCUITS OPEN:");

        $extTemp1 = $this->_readUUTExtTemp1();
        $extTemp2 = $this->_readUUTExtTemp2();

        $choice = readline("\n\rTake measurements and hit enter to continue!");
        
        $this->_setExternalTherms(self::ON);
        $extTemp1 = $this->_readUUTExtTemp1();
        $extTemp2 = $this->_readUUTExtTemp2();
        
        $choice = readline("\n\rTake measurements and hit enter to continue!");
        
        $this->_setExternalTherms(self::OFF);

        $extTemp1 = $this->_readUUTExtTemp1();
        $extTemp2 = $this->_readUUTExtTemp2();
        
        
        $choice = readline("\n\rHit Enter to Continue: ");
        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }


    
    /**
    **********************************************************
    * Program UUT Routine
    *
    * This function loads the bootloader program and writes 
    * the usersignature bytes.  It then allows the user to 
    * load the current application code through a hugnet_load
    * command.
    */
    private function _testProgramUUT()
    {
        $output = array();

        $this->display->displayHeader("Testing Programmed UUT");
        $this->out("\n\r");
        $this->_powerUUT(self::ON);
        $this->out("Power Up Delay");
        sleep(5);
        $this->_ENDPT_SN = "8012";
        
        $choice = readline("\n\rHit Enter to Continue: ");
        $result = $this->_setPowerTable();
        
        if ($result == self::PASS) {
            $this->_runApplicationTest();
        } else { 
            $this->out("Unable to run App Test!");
        }
        
        $this->_powerUUT(self::OFF);
        $choice = readline("\n\rHit Enter to Continue: ");

    }


     /**
    ************************************************************
    * Read User Signature Routine
    *
    * This function will send packet commands to the UUT test
    * firmware to read the user signature bytes and display 
    * them.
    *
    */
    private function _trblshtReadUserSig()
    {
        $this->_system->out("Powering up UUT");
        $result = $this->_testUUTpower();
        sleep(1);
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Sending Read User Signature Command!");
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::READ_USERSIG_COMMAND;
        $dataVal = "00";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Reply Data = ".$ReplyData);
        
        $this->_system->out("\n\r*********************************");
        $SerialNumber = substr($ReplyData, 0, 10);
        $this->_system->out("Serial Number: ".$SerialNumber);
        
        $HardwarePartNum = substr($ReplyData, 10, 10);
        $this->_system->out("Hardware Part Number: ".$HardwarePartNum);
        
        $AdcOffset = substr($ReplyData,20, 4);
        $this->_system->out("ADC Calibration Offset: ".$AdcOffset);
        
        $AdcGain = substr($ReplyData, 24,4);
        $this->_system->out("ADC Calibration Gain Error Correction : ".$AdcGain);
 
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT");
        $this->_powerUUT(self::OFF);
        $this->_clearTester();
        $choice = readline("\n\rHit Enter to Continue: ");
    }
    
   /**
    ************************************************************
    * Relay Test Routine
    *
    * This function steps through the relays K1-K8, closing and
    * opening each one.
    *
    */
    private function _relayTest()
    {
        $idNum = self::EVAL_BOARD_ID;

        /* close K1 - +12V to VBus */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0300";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K1  - 12 Ohm Load to VBUS */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0300";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* close K2 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0301";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K2 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0301";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K3 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0302";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K3 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0302";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K4 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0303";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K4 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0303";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K5 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0204";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K5 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0204";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K6 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0205";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K6 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0205";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K7 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0206";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K7 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0206";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

         sleep(1);
       /* close K8 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0207";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K8 */
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0207";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
    }


    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*     T R O U B L E S H O O T   A P P L I C A T I O N   R O U T I N E S     */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

    /**
    *************************************************************************
    * Troubleshoot with Application Firmware Routine
    * 
    * This function runs the menu and calls functions for troubleshooting
    * an endpoint that either has application firmware loaded.
    */
    private function _runTroubleshootApp()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleAppMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_trblshtAppPwrUp();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_trblshtUserCalibration();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_trblshtAppPort1();
            } else if (($selection == "D") || ($selection == "d")){
                $this->_trblshtAppPort2();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting Application");
            }

        } while ($exitTest == false);

    }
 
     /**
    ************************************************************
    * Troubleshoot Application Power Up Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtAppPwrUp()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        
        $this->_powerUUT(self::ON);
        
        $choice = readline("\n\rTake Measurements and Hit Enter to Exit: ");
        

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ***********************************************************
    * Troubleshoot User Calibration Routine
    *
    * This function reads the user signature calibration values
    * for the ADC and the DAC.  It will display the offset and 
    * gain values and indicate whether or not they are within
    * the proper range for the correction.
    */
    private function _trblshtUserCalibration()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        
        $this->_powerUUT(self::ON);
        sleep(5);

        $this->_system->out("Sending Read Config Command");
        $this->_system->out("");

         //$this->_system->out("Not Done!");
        $idNum = 0x8012;
        $cmdNum = self::READ_CONFIG_COMMAND;
        $dataVal = "";

        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal); 
        
        $length = strlen($ReplyData);

        if ($length >= 62) {
            $serialNum = substr($ReplyData, 0, 10);
            $hwPartNum = substr($ReplyData,10, 10);
            $fwPartNum = substr($ReplyData,20, 16);
            $spacers   = substr($ReplyData, 36, 8);
            $signature = substr($ReplyData, 44, 6);
            $userCal   = substr($ReplyData, 50, 12);
            
            $this->_system->out("Serial Number        = ".$serialNum);
            $this->_system->out("Hardware Part Number = ".$hwPartNum);
            $this->_system->out("Firmware Part Number = ".$fwPartNum);
            $this->_system->out("Signature Bytes      = ".$signature);
            $this->_system->out("");

            $this->_formatUserCalBytes($userCal);
        } else {

            $this->_system->out("Not enough reply data to contain user");
            $this->_system->out("    calibration bytes.");
            $this->_system->out("");
            $this->_system->out("Reply Data = ".$ReplyData);
        }

        $choice = readline("\n\rHit Enter to Continue");


        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    *************************************************************
    * Format User Calibration Bytes Routine
    *
    * This function separates the User Calibration bytes into
    * the ADC offset, ADC gain, DAC offset and DAC gain values
    * and displays them.
    */
    private function _formatUserCalBytes($usrCal)
    {
        $length = strlen($usrCal);
        if ($length == 12) {
            $adcOffset = substr($usrCal, 2, 2);
            $adcOffset .= substr($usrCal, 0, 2);
            
            $this->_system->out("ADC Offset: ".$adcOffset);

            $adcGain = substr($usrCal, 6,2);
            $adcGain .= substr($usrCal, 4, 2);
            $this->_system->out("ADC Gain  : ".$adcGain);

            $dacOffset = substr($usrCal, 8, 2);
            $this->_system->out("DAC Offset: ".$dacOffset);

            $dacGain = substr($usrCal, 10, 2);
            $this->_system->out("DAC Gain  : ".$dacGain);
        } else {
            $this->_system->out("Not enough characters for proper formatting.");
            $this->_system->out("User Cal Bytes: ".$usrCal);
        }

    }


    /**
    *************************************************************
    * Run Port 1 Application Test Routine
    *
    * This function runs the port 1 test on the application code
    * to verify that the application code is running properly.
    *
    * @param integer $SNVal device serial number
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _trblshtAppPort1()
    {

        $this->_system->out("Not done!");
        /*****************************************************
        $this->_system->out("Read Port 1 Control Channel");
        $chan = 1;
        $this->_readControlChan($SNVal, $chan);

        $this->_setPort1Load(self::ON);
        $voltsP1 = $this->_readTesterP1Volt();

        $this->_system->out("Turning on Port 1");
        $this->_setControlChan($SNVal, $chan, self::ON);
        sleep(1);
    
        $voltsP1 = $this->_readTesterP1Volt();

        if (($voltsP1 > 11.00) and ($voltsP1 < 13.00)) {
            $this->_system->out("Turning off Port 1");
            $this->_setControlChan($SNVal, $chan, self::OFF);
            sleep(1);

            $voltsP1 = $this->_readTesterP1Volt();
            $this->_setPort1Load(self::OFF); 

            if ($voltsP1 < 0.2) {
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "App Code P1 off:".$voltsP1."V";
            }
        } else {
            $this->_setControlChan($SNVal, $chan, self::OFF);
            sleep(2);
            $this->_setPort1Load(self::OFF);
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "App Code P1 on:".$voltsP1."V";
        } **********************************************************/

        $choice = readline("\n\rHit Enter to Continue");
    }



    /**
    *************************************************************
    * Run Port 2 Application Test Routine
    *
    * This function runs the port 2 test on the application code
    * to verify that the application code is running properly.
    *
    * @param integer $SNval device serial number
    *
    * @return integer $testResults  1=pass, 0=fail, -1=hard fail
    */
    private function _trblshtAppPort2()
    {
        $this->_system->out("Not Done!");
        /*******************************************************
        $chan = 2;
        $this->_setPort2Load(self::ON);
        
        $voltsP2 = $this->_readTesterP2Volt();

        $this->_system->out("Turning on Port 2");
        $this->_setControlChan($SNval, $chan, self::ON);
        sleep(1);
    
        $voltsP2 = $this->_readTesterP2Volt();
        
        if (($voltsP2 > 11.00) and ($voltsP2 < 13.00)) {
            $this->_system->out("Turning off Port 2");
            $this->_setControlChan($SNval, $chan, self::OFF);
            sleep(1);

            $voltsP2 = $this->_readTesterP2Volt();
            $this->_setPort2Load(self::OFF); 

            if ($voltsP2 < 0.2) {
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "App Code P2 off:".$voltsP2."V";
            }
        } else {
            $this->_system->out("Application Test - FAILED!");
            $this->_setControlChan($SNval, $chan, self::OFF);
            sleep(1);
            $this->_setPort2Load(self::OFF);
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "App Code P2 on:".$voltsP2."V";
        } **********************************************************/
        
        $choice = readline("\n\rHit Enter to Continue");
    }



    /**
    ************************************************************
    * Read Control Channel Routine
    *
    * This function reads the control channel value for the 
    * channel passed in to it.
    *
    * @param int $snNum  serial number for endpoint
    * @param int $chanNum  channel number
    *
    * @return void
    */
    private function _readControlChan($snNum, $chanNum)
    {
        $idNum = $snNum;
        $cmdNum = self::READCONTROLCHAN_COMMAND;
        if ($chanNum == 1) {
            $dataVal = "00";
        } else {
            $dataVal = "01";
        }

        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Port ".$chanNum." Control Channel Reply = ".$ReplyData);
    }

    /**
    ************************************************************
    * Set Control Channel Routine
    *
    * This function sets the control channel of the channel number 
    * passed in to it either on or off depending on the state 
    * parameter.
    *
    * @param int $snNum    device serial number
    * @param int $chanNum  channel number
    * @param int $state    On or Off
    * 
    */
    private function  _setControlChan($snNum, $chanNum, $state)
    {
        $idNum = $snNum;
        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        switch ($chanNum) {
            case 1:
                if ($state == self::ON) {
                    $dataVal = "00204E0000";
                } else {
                    $dataVal = "0000000000";
                }
                break;
            case 2:
                if ($state == self::ON) {
                    $dataVal = "01204E0000";
                } else {
                    $dataVal = "0100000000";
                }
                break;
        }
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Set Control Channel Reply = ".$ReplyData);

    }
   
    /*****************************************************************************/
    /*                                                                           */
    /*                    H U G N ET   R O U T I N E S                           */
    /*                                                                           */
    /*****************************************************************************/
    
    /**
    ***********************************************************
    * Send a Ping routine
    *
    * This function pings the endpoint
    * with the test serial number and 
    * checks to see that the endpoint
    * responds.
    *
    * @param int $Sn Endpoint Serial Numberd

    *
    * @return true, if endpoint responds
    *               otherwise false
    *
    */

    private function _pingEndpoint($Sn)
    {
        $dev = $this->_system->device($Sn);
        $result = $dev->action()->ping();
        //var_dump($result);
        return $result;
    }
    

    /**
    **********************************************************
    * Send a Packet routine
    *
    * This function will send a packet
    * to an endpoint with a command 
    * and data.
    *
    * @param int    $Sn      endpoint serial
    * @param int    $Cmd     hex command number
    * @param string $DataVal ascii hex data string  
    *
    * @return reply data from endpoint
    *
    */
    public function _sendPacket($Sn, $Cmd, $DataVal)
    {



        $dev = $this->_system->device($Sn);
        $pkt = $dev->action()->send(
            array(
                "Command" => $Cmd,
                "Data" => $DataVal,
            ),
            null,
            array(
                "timeout" => $dev->get("packetTimeout")
            )
        );

        if (is_object($pkt)) {
            $this->_system->out(
                "From: ".$pkt->From()
                ." -> To: ".$pkt->To()
                ."  Command: ".$pkt->Command()
                ."  Type: ".$pkt->Type(),
                2
            );
           
            $data = $pkt->Data();
            if (!empty($data)) {
                $this->_system->out("Data: ".$data, 2);
            }

            $data = $pkt->Reply();
            if (is_null($data)) {
                $this->_system->out("No Reply", 2);
            } else if (!empty($data)) {
                $this->_system->out("Reply Data: ".$data, 2);
            } else {
                $this->_system->out("Empty Reply", 2);
            }
        }

        return $data;
    }




}
?>
