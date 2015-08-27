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
    *   2: Power Up DUT test
    *   3: Load test firmware into DUT
    *   4: Ping DUT
    *   5: DUT to turn on switched 3V
    *   6: DUT to return Bus Voltage
    *   7: Tester measures Bus & 3V_SW
    *   8: DUT to return board thermistor values
    *   9: DUT to cycle through LED's
    *  10: Run Port 1 Load Test
    *  11: Run Port 2 Load Test
    *  12: Run Port 1 Fault Test
    *  13: Run Port 2 Fault Test
    *  14: Run External Therm 1 Test
    *  15: Run External Therm 2 Test
    *  16: Load DUT bootloader and config data
    *  17: Load DUT application code
    *  18: Power cycle DUT & verify communications
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

        $result = $this->_checkEvalBoard();
        if ($result) {
            $result = true; //$this->_powerUUTtest();
            if ($result) {
                $result = $this->_checkUUTBoard();

                $this->_readUUTVoltages();
                $this->_testUUTleds();
                /* next step is to load DUT test firmware */
                /* next test is to receive powerup packet */
                $this->display->displayPassed();
            } else {
                $this->display->displayFailed();
            }
        }

        //$result = $this->_powerUUT(self::OFF);

       $choice = readline("\n\rHit Enter to Continue: ");
    }
    

    /**
    ************************************************************
    * Power Up Battery Socializer Test
    *
    * This functions sends a command to the Eval Board to apply
    * +12 volts to the Battery Socializer VBus.  It then 
    * measures the VBus and the Vcc voltage from the socializer
    * board to verify that it powered up okay.
    *
    * @return $result  boolean, true=pass, false=Failed
    */
    private function _powerUUTtest()
    {
       $result = $this->_powerUUT(self::ON);

       /* sleep 30mS */
       usleep(30000);
       
       if ($result) {
            $volt1 = $this->_readBusVolt();
            $volt2 = $this->_readVCC();
        
	    
            if (($volt1 <= 13.0) and ($volt1 >= 11.5 )) {
                $this->out("Bus Voltage = ".$volt1." volts - Passed");
                $result1 = true;
            } else {
                $this->out ("Bus Voltage = ".$volt1." volts - Failed");
                $result1 - false;
            }
	  
            if (($volt2 <= 3.5) and ($volt2 >= 3.0)) {
                $this->out("Vcc Voltage = ".$volt2." volts - Passed");
                $result2 = true;
            } else {
                $this->out ("Vcc Voltage = ".$volt2." volts - Failed");
                $result1 - false;
            }
	  
            if (!$result1 || !$result2) {
                $result = false;
            } else { 
                $result = true;
            }
	  
       } else {
            $this->out("Battery Socializer Power Up Failed!");
       }
       
       return $result;
    }

    /**
    ***********************************************************
    * Power DUT Routine
    * 
    * This function powers up the 10460301 board, measures the 
    * bus voltage and the 3.3V to verify operation for the next
    * step which is loading the test firmware.
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
    * @return $result  
    */
    private function _testUUT()
    {


        $result = true;
        /************************************************************/
        /* list below are the test steps and functions to implement */
        /* 1.  3.3V_Switched Test                                   */
        /* 2.  On Board Thermistor tests                            */
        /* 3.  LED tests                                            */
        /* 4.  Port 1 load test                                     */
        /* 5.  Port 1 overcurrent trip test                         */
        /* 6.  Port 2 load test                                     */
        /* 7.  Port 2 overcurrent trip test                         */
        /* 8.  Vbus load test                                       */
        /* 9.  External thermistor test                             */
        /************************************************************/

        return $result;
    }

    /**
    ************************************************************
    * Switched 3.3V test routine
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
    private function _testUUTthermistors()
    {
        $busTemp = $this->_readUUTBusTemp();
        $p1Temp = $this->_readUUTP1Temp();
        $p2Temp = $this->_readUUTP2Temp();

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
        /* turn on all LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "03";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

       $choice = readline("\n\rAre all 8 LEDs on? (Y/N) ");

         /* turn on all Green LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "01";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

       $choice = readline("\n\rAre all 4 Green LEDs on? (Y/N) ");
       
         /* turn on all Red LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "02";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

       $choice = readline("\n\rAre all 4 Red LEDs on? (Y/N) ");

        /* turn off all LEDs */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
       $choice = readline("\n\rAre all 8 LEDs off? (Y/N) ");
        
        $result = true;

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
        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0303"; /* Relay K4 on */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2.  turn on Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; /* ON */
        $dataVal = "0101";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 3.  delay 100mS */
        usleep(100000);

        /* 4.  Eval Board Measure Port 1 Voltage */
        $voltsP1 = $this->_readTesterP1Volt();

        /* 5.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTP1Volts();

        /* 6.  Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTP1Current();
        /* 7.  Test voltage & current */

        /* 8.  Set the fault signal */
        /* 9.  delay 100mS */
        usleep(100000);

        /* 10. Eval Board Measure Port 1 voltage */
        $voltsP2 = $this->_readTesterP1Volt();

        /* 11. Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTP1Volts();

        /* 12. Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTP1Current();

        /* 13. Remove the fault signal */
        /* 14. delay 100mS */
        usleep(100000);

        /* 15. Turn off Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0100"; /* PORT 1 OFF */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 16.  Disconnect load resistor */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0303"; /* Relay K4 off */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

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
        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0205"; /* Relay K6 ON */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2.  turn on Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0201";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 3.  delay 100mS */
        usleep(100000);

        /* 4.  Eval Board Measure Port 2 Voltage */
        $voltsP2 = $this->_readTesterP2Volt();

        /* 5.  Get UUT Port 2 voltage */
        $p2Volts = $this->_readUUTP2Volts();

        /* 6.  Get UUT Port 2 Current */
        $p2Amps = $this->_readUUTP2Current();
        /* 7.  Test voltage & current */

        /* 8.  Set the fault signal */
        /* 9.  delay 100mS */
        usleep(100000);

        /* 10. Eval Board Measure Port 2 voltage */
        $voltsP2 = $this->_readTesterP2Volt();

        /* 11. Get UUT Port 2 voltage */
        $p2Volts = $this->_readUUTP2Volts();

        /* 12. Get UUT Port 2 Current */
        $p2Amps = $this->_readUUTP2Current();

        /* 13. Remove the fault signal */
        /* 14. delay 100mS */
        usleep(100000);

        /* 15. Turn off Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0200"; /* PORT 2 OFF */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 16.  Disconnect load resistor */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0205"; /* Relay K6 Off */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

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
        /******** test steps **********/
        /* 1.  Connect +12V to Port 1  */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0302"; /* Relay K3 ON Selects +12V */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0303"; /* Relay K4 on connects +12V to Port 1 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2.  Delay 100mS             */
        usleep(100000);

        /* 3.  Disconnect +12V from Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0301"; /* Relay K2 Off removes +12V from Vbus */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 4.  Connect 12 Ohm Load to Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0300"; /* Relay K1 Off selects 12 Ohm Load */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0301"; /* Relay K2 On connects 12 Ohm load to Vbus */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 5.  Turn on Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; /* ON */
        $dataVal = "0101";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 6.  Delay 100mS */
        usleep(100000);

        /* 7.  Eval measure Vbus voltage */
        $vbusVolts = $this->_readTesterBusVolt();

        /* 8.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTP1Volts();

        /* 9.  Get UUT Port 1 current */
        $p1Amps = $this->_readUUTP1Current();

        /* 10. Test current & voltage values. */

        /* 11. Turn off Port 1 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0100"; /* PORT 1 OFF */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 12. Delay 100mS */
        usleep(100000);

        /* 13. Eval measure Vbus Voltage */
        $vbusVolts = $this->_readTesterBusVolt();

        /* 14. Connect +12V to Port 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0204"; /* Relay K5 On selects +12V */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0205"; /* Relay K6 On connects +12V to Port 2 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 15. Disconnect +12V from Port 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0303"; /* Relay K4 Off disconnects +12V  from Port 1*/
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0302"; /* Relay K3 Off selects 12 Ohm load  */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 16. Turn on Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0201";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 17. Eval measure Vbus voltage */
        $vbusVolts = $this->_readTesterBusVolt();

        /* 18. Get UUT Port 2 voltage */
        $p2Volts = $this->_readUUTP2Volts();

        /* 19. Get UUT Port 2 Current */
        $p2Amps = $this->_readUUTP2Current();

        /* 20. Test current and voltage values */

        /* 21. Turn off Port 2 */
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWWERPORT_COMMAND; 
        $dataVal = "0200"; /* PORT 2 OFF */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 22. Delay 100mS */
        usleep(100000);

        /* 23. Eval measure Vbus voltage */
        $vbusVolts = $this->_readTesterBusVolt();

        /* 24. Disconnect load from Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0301"; /* Relay K2 Off removes load from Vbus */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 25. Connect +12V to Vbus */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0301"; /* Relay K1 On selects +12 Volts */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);


        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0301"; /* Relay K2 On connects +12V to Vbus */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 26. Disconnect +12V from Port 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0205"; /* Relay K6 Off removes +12V from Port 2*/
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0204"; /* Relay K5 Off selects 12 Ohm load */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
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
        /*********** test steps ***********/
        /* 1. connect resistor to ext therm 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0206"; /* Relay K7 On connects resistor to ext therm 1 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2. connect resistor to ext therm 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0207"; /* Relay K8 On connects resistor to ext therm 2 */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 3. read ext therm 1 value from UUT */
        $extTemp1 = $this->_readUUTExtTemp1();

        /* 4. read ext therm 2 value from UUT */
        $extTemp1 = $this->_readUUTExtTemp1();

        /* 5. Test thermistor values          */

        /* 6. disconnect resistor from ext therm 1 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0206"; /* Relay K7 Off disconnects resistor */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 7. disconnect resistor from ext therm 2 */
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self::CLR_DIGITAL_COMMAND; 
        $dataVal = "0207"; /* Relay K8 Off disconnects resistor */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

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

        
        return $rawVal;

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

        
        return $rawVal;

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
        $this->display->clearScreen();


        $this->out("Delay time = ".$difftime." seconds");
        $this->out("Not Done!");
        $choice = readline("\n\rHit Enter to Continue: ");
    }

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
        $Result = $this->_pingEndpoint(self::UUT_BOARD_ID);
        if ($Result == true) {
            $this->_system->out("UUT Board Responding!");
        } else {
            $this->_system->out("UUT Board Failed to Respond!");
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


        $Prog = "make -C ~/code/HOS 104603test-install SN=0x0000000020";
        exec($Prog, $output, $return);

        if ($return == 0) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;

    }

    /**
    ************************************************************
    * Test Socializer Routine
    *
    * This function runs the tests in the endpoint test firmware
    * and returns the results.
    *
    * @return boolean $testResult
    *
    */
    private function _testEndpoint()
    {
        $Result = true;    
   
        /*****************************/
        /* Steps in endpoint test    */
        /* 1: 3.3V Switched Test     */
        /* 2: Board Thermistors Test */
        /* 3: LED Test               */
        /* 4: Port 1 Load Test       */
        /* 5: Port 1 default Test    */
        /* 6: Port 2 Load Test       */
        /* 7: Port 2 default Test    */
        /* 8: Vbus Load Test         */
        /* 9: Ext Thermistor 1 Test  */
        /* 10: Ext Thermistor 2 Test */
        /*****************************/

        return $Result;
    }

    


    /*****************************************************************************/
    /*                                                                           */
    /*              D A T A   C O N V E R S I O N   R O U T I N E S              */
    /*                                                                           */
    /*****************************************************************************/


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
        var_dump($result);
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
