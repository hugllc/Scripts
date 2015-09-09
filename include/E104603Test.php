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

/** This is our base class */
require_once "HUGnetLib/ui/Daemon.php";
/** This is our units class */
require_once "HUGnetLib/devices/inputTable/Driver.php";
/** Displays class */
require_once "HUGnetLib/ui/Displays.php";

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
class E104603Test extends \HUGnet\ui\Daemon
{

    const TEST_ID = 0x20;
    const EVAL_BOARD_ID = 0x30;
    const UUT_BOARD_ID = 0x20;

    const TEST_ANALOG_COMMAND   = 0x23;
    const SET_DIGITAL_COMMAND   = 0x24;
    const CLR_DIGITAL_COMMAND   = 0x25;
    const SET_LED_COMMAND       = 0x26;
    const SET_3V_SW_COMMAND     = 0x27;
    const SET_POWERPORT_COMMAND = 0x28;

    const HEADER_STR    = "Battery Socializer Test & Program Tool";
    
    const TSTR_VCC_PORT  = 0;
    const TSTR_VBUS_PORT = 1;
    const TSTR_P2_PORT   = 2;
    const TSTR_P1_PORT   = 3;
    const TSTR_SW3V_PORT = 4;
    
    const UUT_P2_VOLT    = 0;
    const UUT_BUS_TEMP   = 1;
    const UUT_P2_TEMP    = 2;
    const UUT_P1_CURRENT = 3;
    const UUT_P2_CURRENT = 4;
    const UUT_BUS_VOLT   = 5;
    const UUT_EXT_TEMP2  = 6;
    const UUT_EXT_TEMP1  = 7;
    const UUT_P1_TEMP    = 8;
    const UUT_P1_VOLT    = 9;
    const UUT_VCC_VOLT   = 0xA;
    
    const ON = 1;
    const OFF = 0;
    
    

    private $_fixtureTest;
    private $_system;
    private $_device;
    private $_evalDevice;
    private $_eptestMainMenu = array(
                                0 => "Test 104603 Endpoint",
                                1 => "Troubleshoot 104603 ",
                                );

    public $display;
    /*
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config)
    {
        parent::__construct($config);

        $sys = $this->system();
        $this->_system = &$sys;
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
    static public function &factory(&$config = array())
    {
        $obj = new E104603Test($config);
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
                            $this->_eptestMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_test104603Main();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshoot104603Main();
            } else {
                $exitTest = true;
                $this->out("Exit 104603 Tool");
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
    * Main Test Routine
    * 
    * This is the main routine for testing, serializing and 
    * programming in the bootloader for HUGnet endpoints.
    * 
    * Test Steps 
    *   1: Check Eval Board
    *   2: Power Up UUT Test
    *   3: Load test firmware into UUT
    *   4: Ping UUT to test communications
    *   5: Run UUT tests
    *  16: Load UUT bootloader and config data
    *  17: Load UUT application code
    *  18: Power cycle UUT & verify communications
    *  19: Power Down
    *  20: Display passed and log data
    *
    * @return void
    *   
    */
    private function _test104603Main()
    {
        $exitTest = false;
        $this->display->clearScreen();
        $this->display->displayHeader("Testing 104603 Dual Battery Socializer");

        $result = $this->_checkEvalBoard();
        if ($result) {
            $result = $this->_testUUTpower();
            if ($result) {
                $this->out("UUT Power UP - Passed\n\r");
                sleep(1);
                //$this->_loadTestFirmware();
                $result = $this->_checkUUTBoard();
                if ($result) {
                    
                    $result = $this->_testUUT();
                    $this->_readUUTVoltages();
                    if ($result) {
                        $this->display->displayPassed();
                    } else {
                        $this->display->displayFailed();
                    }
                    $result = $this->_powerUUT(self::OFF);                    
                } else {
                    $result = $this->_powerUUT(self::OFF);                    
                    $this->out("\n\rUUT Communications Failed!\n\r");
                    $this->display->displayFailed();
                }
            } else {
                $this->out("\n\rUUT power failed!\n\r");
                $this->display->displayFailed();
            }
        } else {
            $this->out("\n\rEval Board Communications Failed!\n\r");
            $this->displayFailed();
        }


       $choice = readline("\n\rHit Enter to Continue: ");
    }
    
    /**
    ***********************************************************
    * Power UUT Test
    *
    * This function powers up the 10460301 board, measures the 
    * bus voltage and the 3.3V to verify operation for the next
    * step which is loading the test firmware.
    *
    *
    * @return $result
    */
    private function _testUUTpower()
    {
        $this->out("Testing UUT Power UP");
        $this->out("******************************");

        $result = $this->_powerUUT(self::ON);
        sleep(1);
        if ($result) {
            $volts = $this->_readTesterBusVolt();
            $busv = number_format($volts, 2);
            $this->out("Bus Voltage = ".$busv." volts");
            if (($volts > 11.5) and ($volts < 13.00)) {
                $volts = $this->_readTesterVCC();
                $vccv = number_format($volts, 2);
                $this->out("Vcc = ".$vccv." volts");
                if (($volts > 3.1) and ($volts < 3.4)) {
                    $result = true;
                } else {
                    $result = false;
                    $this->_powerUUT(self::OFF);
                }
            } else {
                $result = false;
                $this->_powerUUT(self::OFF);
            }
        }
        
        return $result;
    }

    /**
    ***********************************************************
    * Power UUT through VBUS Routine
    * 
    * This function controls the +12V supply to the UUT through  
    * opening or closing of relays K1 & K2.
    * +12V On  = K1 closed & K2 closed
    * +12V Off = K1 open & K2 open
    *
    * @return boolean result
    */
    private function _powerUUT($state)
    {
        $idNum = self::EVAL_BOARD_ID;
        
        if ($state == self::ON) {
            $cmdNum = self::SET_DIGITAL_COMMAND; /* ON */
        } else {
            $cmdNum = self::CLR_DIGITAL_COMMAND; /* OFF */
        }
	
        $dataVal = "0300";
        /* set or clear relay K1 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        if ($ReplyData == "1E") {
            $result1 = true;
        } else { 
            $result1 = false;
        }
        sleep(1);
        $dataVal = "0301";
        /* set or clear relay K2 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "1F") {
            $result2 = true;
        } else {
            $result2 = false;
        }

        if (($result1 == true) and ($result2 == true)) {
            $result = true;
        } else {
            $result = false;
        }


        return $result;
    }

    /**
    ************************************************************
    * Test UUT Routine
    *
    * This function steps through the tests for the Unit Under
    * Test (UUT) after the test firmware has been loaded.
    *
    * list below are the test steps and functions to implement
    *   1. On Board Thermistor tests                            
    *   2. Port 1 test                                         
    *   3. Port 2 test                                          
    *   4. Vbus load test                                       
    *   5. External thermistor test                            
    *   6. LED tests   
    *
    * @return $result  
    */
    private function _testUUT()
    {
 
        $testNum = 0;

        do {
            $testNum += 1;
            switch ($testNum) {
                case 1:
                    $result = $this->_testUUTThermistors();
                    break;
                case 2: 
                    $result = $this->_testUUTport1();
                    break;
                case 3: 
                    $result = $this->_testUUTport2();
                    break;
                case 4:
                    $result = $this->_testUUTvbus();
                    break;
                case 5:
                    $result = $this->_testUUTexttherms();
                    break;
                case 6:
                    $result = $this->_testUUTleds();
                    break;
            }

        } while ($result and ($testNum < 6));


        return $result;
    }

    /**
    **************************************************************
    * Switched 3.3V test routine
    *
    * ***** NOTE ****** Further testing must be done to see if 
    * this test is even viable.  Pulling the 3V3_SW line appears
    * to stop all serial communications to the one wire interface. 
    *
    * This function sends a command to the UUT to turn on the 
    * switched 3.3V and then measures the 3.3V Switched 
    * voltage to verify the response.
    *
    * @return $result
    */
    private function _testUUTswitched3V3()
    {

        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_3V_SW_COMMAND;
        $dataVal = "01";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $newData = $this->_convertReplyData($ReplyData);
        $this->out("3v3 reply data :".$newData." !");

        $volts = $this->_readTesterSW3();
        $this->out("3.3V measures ".$volts." volts!");

        return true;

    }

    /**
    *****************************************************************
    * Test On Board Thermistors Routine
    *
    * This function reads the temperatures for the on board
    * thermistors located at the Vbus, at Port 1 and at Port 2.
    * Tests will be made shortly after power up and before load 
    * testing so they will be near ambient temperature.
    *
    * @return $result
    */
    private function _testUUTThermistors()
    {
        $this->out("Testing Thermistors");
        $this->out("******************************");

        sleep(2);

        $busData = $this->_readUUTBusTemp();
        $busTemp = $this->_convertTempData($busData);
        $busTemp = number_format($busTemp, 2);
        $this->out("Bus Temp : ".$busTemp." C");

        if (($busTemp > 17.00) and ($busTemp < 24.00)) {
            $resultT1 = true;
        } else {
            $resultT1 = false;
        }

        $p1Data = $this->_readUUTP1Temp();
        $p1Temp = $this->_convertTempData($p1Data);
        $p1Temp = number_format($p1Temp, 2);
        $this->out("Port 1 Temp : ".$p1Temp." C");

        if (($p1Temp > 17.00) and ($p1Temp < 24.00)) {
            $resultT2 = true;
        } else {
            $resultT2 = false;
        }
        

        $p2Data = $this->_readUUTP2Temp(); 
        $p2Temp = $this->_convertTempData($p2Data);
        $p2Temp = number_format($p2Temp, 2);
        $this->out("Port 2 Temp : ".$p2Temp." C");

        if (($p2Temp > 17.00) and ($p2Temp < 24.00)) {
            $resultT3 = true;
        } else {
            $resultT3 = false;
        }

        if ($resultT1 and $resultT2 and $resultT3) {
            $result = true;
        } else {
            $result = false;
        }

        $this->out("");

        return $result;
    }

    /**
    ***************************************************************
    * Port 1 Load Test Routine
    *
    * This function connects a load to Port 1 and turns on the 
    * FET.  It then measures the port voltage and reads the 
    * Port voltage and current from the UUT.  It tests the 
    * voltage and current against expected values for each.  Then
    * it applies the fault signal to verify that the port is 
    * turned off during a fault condition.
    * 
    * @return $result
    */
    private function _testUUTport1()
    {
        $this->out("Testing UUT Port 1");
        $this->out("******************************");

        sleep(1);
        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0303"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); 

        /* 2.  turn on Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0101";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 1 ON:");
        /* 3.  delay 1 Second */
        sleep(1);

        /* 4.  Eval Board Measure Port 1 Voltage */
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1  Tester = ".$p1v." volts");

        /* 5.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTP1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT = ".$pv1." volts!");

        /* 6.  Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTP1Current();
        $p1A = number_format($p1Amps, 2);
        $this->out("Port 1 current = ".$p1A." A");
        /* 7.  Test voltage & current */
        sleep(1);
        /* 8.  Set the fault signal */
        /* 9.  delay 100mS */
       // usleep(100000);

        /* 10. Eval Board Measure Port 1 voltage */
        //$voltsP2 = $this->_readTesterP1Volt();

        /* 11. Get UUT Port 1 voltage */
        //$p1Volts = $this->_readUUTP1Volts();

        /* 12. Get UUT Port 1 Current */
        //$p1Amps = $this->_readUUTP1Current();

        /* 13. Remove the fault signal */
        /* 14. delay 100mS */
        //usleep(100000);

        /* 15. Turn off Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0100"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 1 OFF:");

        usleep(100000);
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 Tester = ".$p1v." volts");

        $p1Volts = $this->_readUUTP1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT = ".$pv1." volts!");

        /* 16.  Disconnect load resistor */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0303"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $result = true;
        $this->out("");
        return $result;

    }

    /**
    ***************************************************************
    * Port 2 Load Test Routine
    *
    * This function connects a load to Port 2 and turns on the 
    * FET.  It then measures the port voltage and reads the 
    * Port voltage and current from the UUT.  It tests the 
    * voltage and current against expected values for each.  Then
    * it applies the fault signal to verify that the port is 
    * turned off during a fault condition.
    * 
    * @return $result
    */
    private function _testUUTport2()
    {
        $this->out("Testing UUT Port 2 ");
        $this->out("******************************");

        sleep(1);
        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0205"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2.  turn on Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0201";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 2 ON:");
        /* 3.  delay 1 Second */
        sleep(1);

        /* 4.  Eval Board Measure Port 2 Voltage */
        $voltsP2 = $this->_readTesterP2Volt();
        $p2v = number_format($voltsP2, 2);
        $this->out("Port 2  Tester = ".$p2v." volts");

        /* 5.  Get UUT Port 2 voltage */
        $p2Volts = $this->_readUUTP2Volts();
        $pv2 = number_format($p2Volts, 2);
        $this->out("Port 2 UUT = ".$pv2." volts!");

        /* 6.  Get UUT Port 2 Current */
        $p2Amps = $this->_readUUTP2Current();
        $p2A = number_format($p2Amps, 2);
        $this->out("Port 2 current = ".$p2A." A");


        /* 7.  Test voltage & current */

        /* 8.  Set the fault signal */
        /* 9.  delay 100mS */
        usleep(100000);

        /* 10. Eval Board Measure Port 2 voltage */
        //$voltsP2 = $this->_readTesterP2Volt();

        /* 11. Get UUT Port 2 voltage */
        //$p2Volts = $this->_readUUTP2Volts();

        /* 12. Get UUT Port 2 Current */
        //$p2Amps = $this->_readUUTP2Current();

        /* 13. Remove the fault signal */
        /* 14. delay 100mS */
        /* 15. Turn off Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0200"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 2 OFF:");
        usleep(100000);

        $voltsP2 = $this->_readTesterP2Volt();
        $p2v = number_format($voltsP2, 2);
        $this->out("Port 2 Tester = ".$p2v." volts");

        $p2Volts = $this->_readUUTP2Volts();
        $pv2 = number_format($p2Volts, 2);
        $this->out("Port 2 UUT = ".$pv2." volts!");

        /* 16.  Disconnect load resistor */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0205"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $result = true;
        $this->out("");
        return $result;

    }


    /**
    ***************************************************************
    * VBus Load Test Routine
    *
    * This function connects +12V to Port1, disconnects +12V from
    * VBus, turns on Port 1 and connects a load to VBus.  It then
    * measures the VBus voltage and reads the Port voltage and 
    * current from the UUT.  It tests the voltage and current 
    * against expected values for each.  
    * 
    * @return $result
    */
    private function _testUUTvbus()
    {
        $this->out("Testing UUT VBUS");
        $this->out("******************************");

        sleep(1);
        /******** test steps **********/
        /* 1.  Connect +12V to Port 1  */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0302"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0303"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 1 +12V ON:");
        /* 2.  Delay 100mS             */
        usleep(100000);
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1  Tester = ".$p1v." volts");

        /* 3.  Disconnect +12V from Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0301"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("Bus Voltage OFF:");
        sleep(1);

        $voltsVB = $this->_readTesterBusVolt();
        $Bv = number_format($voltsVB, 2);
        $this->out("Bus Volts Tester = ".$Bv." volts");


        /* 4.  Connect 12 Ohm Load to Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; /* Select 12 Ohm Load */
        $dataVal = "0300"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; /* Connect to Vbus */
        $dataVal = "0301"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 5.  Turn on Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0101";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); 

        $this->out("PORT 1 ON:");
        /* 6.  Delay 100mS */
        usleep(100000);

        /* 7.  Eval measure Vbus voltage */
        $voltsVB = $this->_readTesterBusVolt();
        $Bv = number_format($voltsVB, 2);
        $this->out("Bus Volts Tester = ".$Bv." volts");

        /* 8.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTP1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT = ".$pv1." volts!");

        /* 9.  Get UUT Port 1 current */
        $p1Amps = $this->_readUUTP1Current();
        $p1A = number_format($p1Amps, 2);
        $this->out("Port 1 current = ".$p1A." A");

        /* 10. Test current & voltage values. */

        /* 11. Turn off Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 
        $dataVal = "0100"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("PORT 1 OFF:");
        /* 12. Delay 100mS */
        usleep(100000);

        /* 13. Eval measure Vbus Voltage */
        $voltsVB = $this->_readTesterBusVolt();
        $Bv = number_format($voltsVB, 2);
        $this->out("Bus Volts Tester = ".$Bv." volts");

        $p1Amps = $this->_readUUTP1Current();
        $p1A = number_format($p1Amps, 2);
        $this->out("Port 1 current = ".$p1A." A");

        /* 14. Connect +12V to Port 2 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0204"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0205"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 15. Disconnect +12V from Port 1 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0303"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0302"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 16. Turn on Port 2 */
        /* $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0201";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 17. Eval measure Vbus voltage */
        //$vbusVolts = $this->_readTesterBusVolt();

        /* 18. Get UUT Port 2 voltage */
        //$p2Volts = $this->_readUUTP2Volts();

        /* 19. Get UUT Port 2 Current */
        //$p2Amps = $this->_readUUTP2Current();

        /* 20. Test current and voltage values */

        /* 21. Turn off Port 2 */
        /* $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0200"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 22. Delay 100mS */
        usleep(100000);

        /* 23. Eval measure Vbus voltage */
        //$vbusVolts = $this->_readTesterBusVolt();

        /* 24. Disconnect load from Vbus */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0301"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 25. Connect +12V to Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; /* open connection to Vbus */
        $dataVal = "0301";                   /* to remove 12 Ohm load   */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; /* select +12V */
        $dataVal = "0300"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND;  /* connect to Vbus */
        $dataVal = "0301"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("Bus Voltage ON:");
        sleep(1);

        $voltsVB = $this->_readTesterBusVolt();
        $Bv = number_format($voltsVB, 2);
        $this->out("Bus Volts Tester = ".$Bv." volts");

        /* 26. Disconnect +12V from Port 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; /* Open connection to Port 1 */
        $dataVal = "0303"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; /* set to 12 Ohm Load */
        $dataVal = "0302"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $this->out("Port 1 +12V OFF:");

        usleep(100000);
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1  Tester = ".$p1v." volts");

        $result = true;

        $this->out("");
        return $result;
   }

    /**
    *******************************************************************
    * External Thermistor Test Routine
    *
    * This function tests the external thermistor connections by 
    * connecting known resistance values to the thermistor connections
    * and then testing the UUT measurement values.
    *
    * @return $result
    */
    private function _testUUTexttherms()
    {
        $this->out("Testing UUT External Thermistors");
        $this->out("******************************");

        sleep(2);

        /*********** test steps ***********/
        /* 1. connect resistor to ext therm 1 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0206"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 2. connect resistor to ext therm 2 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0207"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 3. read ext therm 1 value from UUT */
        //$extTemp1 = $this->_readUUTExtTemp1();

        /* 4. read ext therm 2 value from UUT */
        //$extTemp1 = $this->_readUUTExtTemp1();

        /* 5. Test thermistor values          */

        /* 6. disconnect resistor from ext therm 1 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0206"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/

        /* 7. disconnect resistor from ext therm 2 */
        /* $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0207"; 
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);*/
        
        $result = true;
        $this->out("");
        return $result;

    }

    /**
    **************************************************************
    * Test LEDs Routine
    *
    * This function sets the LED's in different patterns of 
    * on and off states and asks the operator to verify these 
    * states.
    *
    * @return $result
    */
    private function _testUUTleds()
    {
        $this->out("Testing LEDs");

        /* turn on all LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "03";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

       $choice = readline("\n\rAre all 8 LEDs on? (Y/N) ");

         /* turn on all Green LEDs */
        /* $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "01";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

       $choice = readline("\n\rAre all 4 Green LEDs on? (Y/N) ");*/
       
         /* turn on all Red LEDs */
        /* $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "02";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); 

       $choice = readline("\n\rAre all 4 Red LEDs on? (Y/N) ");*/

        /* turn off all LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
       $choice = readline("\n\rAre all 8 LEDs off? (Y/N) ");
        
        $result = true;

        return $result;

    }


    /*****************************************************************************/
    /*                                                                           */
    /*               T E S T E R   A N A L O G   R O U T I N E S                 */
    /*                                                                           */
    /*****************************************************************************/
   

    /**
    ************************************************************
    * Read Board Vcc Voltage
    * 
    * This function reads the Battery Socializer +3.3VDC supply
    * voltage and returns the value.
    * 
    * @return $volts a floating point value for Vcc 
    */
    private function _readTesterVCC()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VCC_PORT);
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 6.6;
	
        return $volts;
    }

     /**
    ************************************************************
    * Read BusVoltage
    * 
    * This function reads the Battery Socializer Bus
    * voltage and returns the value.
    * 
    * @return $volts  a floating point value for Bus voltage 
    */
    private function _readTesterBusVolt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VBUS_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
	
        return $volts;
    }
    
    /**
    ************************************************************
    * Read Port 2 Voltage
    * 
    * This function reads the Battery Socializer Port 2 
    * voltage and returns the value.
    * 
    * @return $volts  a floating point value for Bus voltage 
    */
    private function _readTesterP2Volt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_P2_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
	
        return $volts;
    }
  
    /**
    ************************************************************
    * Read Port 1 Voltage
    * 
    * This function reads the Battery Socializer Port 1 
    * voltage and returns the value.
    * 
    * @return $volts  a floating point value for Bus voltage 
    */
    private function _readTesterP1Volt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_P1_PORT);

        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
	
        return $volts;
    }
    
    /**
    ************************************************************
    * Read Switch 3V Voltage
    * 
    * This function reads the Battery Socializer switched supply
    * voltage and returns the value.
    * 
    * @return $volts a floating point value for Vcc 
    */
    private function _readTesterSW3()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_SW3V_PORT);
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 6.6;
	
        return $volts;
    }

    /**
    ************************************************************
    * Read Eval Board ADC Input Routine
    *
    * This function reads the Eval board analog input
    * specified by the input parameter and returns the reply 
    * data.
    * 
    * @return $newData hex value representing adc reading.
    *
    */
    private function _readTesterADCinput($inputNum)
    {
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = sprintf("0%s",$inputNum);
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $newData = $this->_convertReplyData($ReplyData);


        return $newData;
    }

    /*****************************************************************************/
    /*                                                                           */
    /*                 U U T   A N A L O G   R O U T I N E S                     */
    /*                                                                           */
    /*****************************************************************************/
    
    /**
    ************************************************************
    * Read UUT Voltages Routine
    *
    * This routine reads the voltages measured internally by the
    * UUT and displays those values.
    *
    * @return void
    */
    private function _readUUTVoltages()
    {
        $p2Volts = $this->_readUUTP2Volts();
        $this->out("Port 2 voltage is ".$p2Volts." volts!");

        $busTemp = $this->_readUUTBusTemp();
        $this->out("Bus temp data is ".$busTemp." !");

        $p2Temp = $this->_readUUTP2Temp();
        $this->out("Port 2 temp data is ".$p2Temp." !");

        $p1Current = $this->_readUUTP1Current();
        $this->out("Port 1 current data is ".$p1Current." !");

        $p2Current = $this->_readUUTP2Current();
        $this->out("Port 2 current data is ".$p2Current." !");

        $BusVolts = $this->_readUUTBusVolts();
        $this->out("Bus Voltage is ".$BusVolts." volts!");

        $extTemp2 = $this->_readUUTExtTemp2();
        $this->out("External temp 2 data is ".$extTemp2." !");

        $extTemp1 = $this->_readUUTExtTemp1();
        $this->out("External temp 1 data is ".$extTemp1." !");

        $p1Temp = $this->_readUUTP1Temp();
        $this->out("Port 1 temp data is ".$p1Temp." !");

        $p1Volts = $this->_readUUTP1Volts();
        $this->out("Port 1 voltage is ".$p1Volts." volts!");

        $vccVolts = $this->_readUUTVccVolts();
        $this->out("Vcc Voltage is ".$vccVolts." volts!");


    }

    /**
    ************************************************************
    * Read UUT Port 2 Voltage Routine
    * 
    * This function reads the Port 2 Voltage internally measured 
    * by the Unit Under Test (UUT).  Index 0
    *
    * @return $volts 
    */
    private function _readUUTP2Volts()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P2_VOLT);

        if ($rawVal > 0x7FFF) {
            $rawVal = 0x0000;
        }
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        
        return $volts;

    }

     /**
    ************************************************************
    * Read UUT Bus Temperature Routine
    * 
    * This function reads the Bus temperature internally measured 
    * by the Unit Under Test (UUT). Index 1
    *
    * @return $rawVal 
    */
    private function _readUUTBusTemp()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_BUS_TEMP);

        
        return $rawVal;

    }

     /**
    *****************************************************************
    * Read UUT Port 2 Temperature Routine
    * 
    * This function reads the Port 2 temperature internally measured 
    * by the Unit Under Test (UUT). Index 2
    *
    * @return $rawVal 
    */
    private function _readUUTP2Temp()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P2_TEMP);

        
        return $rawVal;

    }

     /**
    ************************************************************
    * Read UUT Port 1 Current Routine
    * 
    * This function reads the Port 1 Current flow measured 
    * by the Unit Under Test (UUT).  Index 3
    *
    * @return $volts 
    */
    private function _readUUTP1Current()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P1_CURRENT);
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / 0.053;
        return $current;

    }

     /**
    ************************************************************
    * Read UUT Port 2 Current Routine
    * 
    * This function reads the Port 2 Current flow measured 
    * by the Unit Under Test (UUT).  Index 4
    *
    * @return $rawVal
    */
    private function _readUUTP2Current()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P2_CURRENT);
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / 0.053;

        
        return $current;

    }
   /**
    ************************************************************
    * Read UUT Bus Voltage Routine
    * 
    * This function reads the Bus Voltage internally measured 
    * by the Unit Under Test (UUT). Index 5
    *
    * @return $volts 
    */
    private function _readUUTBusVolts()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_BUS_VOLT);

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        
        return $volts;

    }

     /**
    *****************************************************************
    * Read UUT External Temperature 2 Routine
    * 
    * This function reads the external temperature 2 measured 
    * by the Unit Under Test (UUT). Index 6
    *
    * @return $rawVal 
    */
    private function _readUUTExtTemp2()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_EXT_TEMP2);

        
        return $rawVal;

    }
     /**
    *****************************************************************
    * Read UUT External Temperature 1 Routine
    * 
    * This function reads the external temperature 1 measured 
    * by the Unit Under Test (UUT). Index 7
    *
    * @return $rawVal 
    */
    private function _readUUTExtTemp1()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_EXT_TEMP1);

        
        return $rawVal;

    }

     /**
    *****************************************************************
    * Read UUT Port 1 Temperature Routine
    * 
    * This function reads the Port 1 temperature internally measured 
    * by the Unit Under Test (UUT). Index 8
    *
    * @return $rawVal 
    */
    private function _readUUTP1Temp()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P1_TEMP);

        
        return $rawVal;

    }


    /**
    ************************************************************
    * Read UUT Port 1 Voltage Routine
    * 
    * This function reads the Port 1 Voltage internally measured 
    * by the Unit Under Test (UUT).  Index 9
    *
    * @return $volts 
    */
    private function _readUUTP1Volts()
    {
        $rawVal = $this->_readUUT_ADCinput(self::UUT_P1_VOLT);

        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        
        return $volts;

    }


    /**
    ************************************************************
    * Read UUT Vcc Voltage Routine
    * 
    * This function reads the Vcc Voltage internally measured 
    * by the Unit Under Test (UUT). Index A
    *
    * @return $volts 
    */
    private function _readUUTVccVolts()
    {
        $rawVal = $this->_readUUT_ADCinput("a");

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 10;
        
        return $volts;

    }


     /**
    ************************************************************
    * Read Unit Under Test ADC Input Routine
    *
    * This function reads the UUT board analog inputs
    * specified by the input parameter and returns the reply 
    * data.
    * 
    * @return $newData hex value representing adc reading.
    *
    */
    private function _readUUT_ADCinput($inputNum)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = sprintf("0%s",$inputNum);
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $newData = $this->_convertReplyData($ReplyData);


        return $newData;
    }
   
    
    
    

    /*****************************************************************************/
    /*                                                                           */
    /*             T R O U B L E S H O O T I N G   R O U T I N E S               */
    /*                                                                           */
    /*****************************************************************************/
   
    /**
    ************************************************************
    *Troubleshoot Main Routine
    *
    * This is the main routine for troubleshooting the tester 
    * or the 10460301 Dual Battery Socializer.  
    * 
    * @return void
    *
    */
    private function _troubleshoot104603Main()
    {
        $this->display->clearScreen();
        $this->_relayTest();

        $this->out("Not Done!");
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

        /* close K1 */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0300";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K1 */
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
    /*               T E S T   U T I L I T I E S   R O U T I N E S               */
    /*                                                                           */
    /*****************************************************************************/

     /**
    ************************************************************
    * Check Eval Board Routine
    *
    * This function pings the Xmega-E5 Eval board
    * and returns the results.
    *
    * @return boolean $testResult   
    */
    private function _checkEvalBoard()
    {
        $Result = $this->_pingEndpoint(self::EVAL_BOARD_ID);
        if ($Result == true) {
            $this->_system->out("Eval Board Responding!");
        } else {
            $this->_system->out("Eval Board Failed to Respond!");
        }


        return $Result;
    }

     /**
    ************************************************************
    * Check UUT Board Routine
    *
    * This function pings the 104603 Unit Under Test (UUT) board
    * and returns the results.
    *
    * @return boolean $testResult   
    */
    private function _checkUUTBoard()
    {
        $this->out("Testing UUT Communications");
        $this->out("******************************");
        $Result = $this->_pingEndpoint(self::UUT_BOARD_ID);
        if ($Result == true) {
            $this->_system->out("UUT Board Responding!\n\r");
        } else {
            $this->_system->out("UUT Board Failed to Respond!\n\r");
        }


        return $Result;
    }

 
    /**
    ************************************************************
    * Load test firmware routine
    *
    * This function loads the 10460301 endpoint with the test 
    * firmware.  It programs the device with a test serial 
    * number which will eventually be replaced after testing within
    * the original board serial number entered by the user.  The 
    * firmware is load through the AVR2 serial programmer.
    *
    * @return int $result  
    */
    private function _loadTestFirmware()
    {
        $output = array();
        $this->display->displayHeader("Loading Test Firmware");
        $choice = readline("\n\rConnect Programmer and Hit Enter to Continue: ");
        


        $Prog = "make -C ~/code/HOS 104603test-install SN=0x0000000020";
        exec($Prog, $output, $return);

        if ($return == 0) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;

    }



    /*****************************************************************************/
    /*                                                                           */
    /*              D A T A   C O N V E R S I O N   R O U T I N E S              */
    /*                                                                           */
    /*****************************************************************************/

    /**
    ************************************************************
    * Convert UUT Temperature Values
    *
    * This function takes in raw data from UUT adc for on board
    * temperature sensors and converts the value into degrees.
    *
    * @param $dataVal
    *
    * @return $degreesC
    */
    private function _convertTempData($dataVal)
    {
        $adcTable = array(
            2252,
            1968,
            1698,
            1448,
            1224,
            1028,
            859,
            716,
            595,
            495,
            412,
            344,
            287,
            241,
            202,
            170,
            144,
            122,
            104,
            88,
            76,
            65,
            56,
            49,
            42,
            37,
            32,
            28,
            25,
            22,
            19,
            17,
            15,
            14,
        );

        $tempTable = array(
            -40000,
            -35000,
            -30000,
            -25000,
            -20000,
            -15000,
            -10000,
            -5000,
                0,
            5000,
            10000,
            15000,
            20000,
            25000,
            30000,
            35000,
            40000,
            45000,
            50000,
            55000,
            60000,
            65000,
            70000,
            75000,
            80000,
            85000,
            90000,
            95000,
            100000,
            105000,
            110000,
            115000,
            120000,
            125000,
        );

        $multTable = array(
            -17, /* 0 */
            -18,
            -20,
            -22,
            -25,
            -29,
            -34,
            -41,
            -50,
            -60,
            -73, /* 10 */
            -87,
            -108,
            -128,
            -156,
            -192,
            -227,
            -277,
            -312,
            -416,
            -454, /* 20 */
            -555, 
            -714,
            -714,
            -1000,
            -1000,
            -1250,
            -1666,
            -1666,
            -1666,
            -2500, /* 30 */
            -2500,
            -5000, 
        );
         
        $tableLength = 34;
        $tempC = 0.0;
        for ($i=0; $i<$tableLength; $i++) {
            if ($dataVal == $adcTable[$i]) {
                $myIndex = $i;
                $tempC = $tempTable[$i];
                break;
            } else if ($dataVal > $adcTable[$i]) {
                if ($i > 0) {
                    $myIndex = $i;
                    $tabVal = $adcTable[$i];
                    $inputDiff = $dataVal - $tabVal;
                    $multVal = $multTable[$i-1];
                    $tempVal = $tempTable[$i];
                    $tempC = $tempVal + ($inputDiff * $multVal);
                }
                break;
            }
        }

        $tempC /= 1000;

        return $tempC;
    }




    /**
    ************************************************************
    * Convert Reply Data String
    *
    * This function changes the bytes in the input string
    * from little endian to big endian so the hex string
    * can be converted to an integer.
    *
    * @return int $result of conversion
    *
    */
    private function _convertReplyData(&$inString)
    {
        $size = strlen($inString);
        $newString = "00";
        $newString = $newString.substr($inString, 4, 2);
        $newString = $newString.substr($inString, 2, 2);
        $newString = $newString.substr($inString, 0, 2);
        $newString = "0x".$newString;
        $newVal = 0 + $newString;
        
        return $newVal;

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
    private function _sendPacket($Sn, $Cmd, $DataVal)
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
