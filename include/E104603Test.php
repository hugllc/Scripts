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
/** This is needed */
require_once "HUGnetLib/devices/inputTable/DriverAVR.php";
/** Database class */
require_once "HUGnetLib/db/tables/DeviceTests.php";
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
class E104603Test
{

    const TEST_ID = 0x20;
    const EVAL_BOARD_ID = 0x30;
    const UUT_BOARD_ID = 0x20;

    const TEST_ANALOG_COMMAND    = 0x23;
    const SET_DIGITAL_COMMAND    = 0x24;
    const CLR_DIGITAL_COMMAND    = 0x25;
    const SET_LED_COMMAND        = 0x26;
    const SET_3V_SW_COMMAND      = 0x27;
    const SET_POWERPORT_COMMAND  = 0x28;
    const RESET_DIGITAL_COMMAND  = 0x29;

    const SET_ADCOFFSET_COMMAND  = 0x2A;
    const SET_ADCGAINCOR_COMMAND = 0x2B;

    const READ_ANALOG_COMMAND    = 0x2C;
    const SET_DAC_COMMAND        = 0x2D;
    const SET_DACOFFSET_COMMAND  = 0x2E;
    const SET_DACGAIN_COMMAND    = 0x2F;
    
    const READ_PRODSIG_COMMAND   = 0x35;

    const READ_USERSIG_COMMAND   = 0x36;
    const ERASE_USERSIG_COMMAND  = 0x37;
    
    const SET_POWERTABLE_COMMAND = 0x45;

    const READ_SENSORS_COMMAND   = 0x55;
    const READ_CONFIG_COMMAND    = 0x5C;

    const SETCONTROLCHAN_COMMAND = 0x64;
    const READCONTROLCHAN_COMMAND = 0x65;

    const HEADER_STR    = "Battery Coach Test & Program Tool";
    
    /*****************************************/
    /* ADC index values changed for 10460302 */
    /*****************************************/
    const TSTR_VCC_PORT     = 0;
    const TSTR_VBUS_PORT    = 1;
    const TSTR_P0_PORT      = 2;
    const TSTR_P1_PORT      = 3;
    const TSTR_SW3V_PORT    = 4;
    const TSTR_P12VBUS_PORT = 5;
    
    const UUT_P0_VOLT    = 0;
    const UUT_P1_VOLT    = 1;
    const UUT_BUS_VOLT   = 2;
    const UUT_CAL_VOLT   = 3;
    const UUT_BUS_TEMP0  = 4;
    const UUT_BUS_TEMP1  = 5;
    const UUT_P0_TEMP    = 6;
    const UUT_P1_CURRENT = 7;
    const UUT_P0_CURRENT = 8;
    const UUT_EXT_TEMP2  = 9;
    const UUT_EXT_TEMP1  = 0xa;
    const UUT_P1_TEMP    = 0xb;
    const UUT_VCC_VOLT   = 0xc;
    const UUT_DAC_VOLT   = 0xd;

    const ON = 1;
    const OFF = 0;
    
    const PASS = 1;
    const FAIL = 0;
    const HFAIL = -1;

    const DAC_OFFCAL_LEVEL = 0.825;
    const DAC_OFFCAL_START = 1024;
    const DAC_GAINCAL_LEVEL = 1.60;
    const DAC_GAINCAL_START = 1986;
    
    const HWPN = "1046030241";
    
    

    private $_fixtureTest;
    private $_system;
    private $_device;
    private $_evalDevice;
    private $_eptestMainMenu = array(
                                0 => "Test 104603 Endpoint",
                                1 => "Troubleshoot 104603 ",
                                );

                                
    public $display;

    private $_ADC_OFFSET;
    private $_ADC_GAIN;
    private $_DAC_OFFSET;
    private $_DAC_GAIN;
    private $_P1_AOFFSET = 0;
    private $_P0_AOFFSET = 0;
    private $_P1_AGAIN = 0.0532258;
    private $_P0_AGAIN = 0.0532258;
    private $_ENDPT_SN;
    private $_FAIL_FLAG;
    private $_MICRO_SN;
    
    private $_TEST_DATA = array(
                            "BusVolts"        => 0.0,
                            "Vcc"             => 0.0,
                            "ADCoffset"       => "",
                            "ADCgain"         => "",
                            "DACoffset"       => "",
                            "DACgain"         => "",
                            "P1CurrentOffset" => "",
                            "P2CurrentOffset" => "",
                            "BusTemp"         => 0.0,
                            "P1Temp"          => 0.0,
                            "P2Temp"          => 0.0,
                            "P1Volts"         => 0.0,
                            "P1Current"       => 0.0,
                            "P1Fault"         => 0.0,
                            "P2Volts"         => 0.0,
                            "P2Current"       => 0.0,
                            "P2Fault"         => 0.0,
                            );

    private $_TEST_FAIL = array();
    private $_DB_DATA = array();

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
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
    static public function &factory(&$config = array(), &$sys)
    {
        $obj = new E104603Test($config, $sys);
        return $obj;
    }

    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*                              M A I N                                      */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

 
    /**
    ************************************************************
    * Main Test Routine
    * 
    * This is the main routine for testing, serializing and 
    * programming Battery Socializer endpoints.
    * 
    * Test Steps 
    *   1: Check Eval Board
    *   2: Power Up UUT Test
    *   3: Load test firmware into UUT
    *   4: Ping UUT to test communications
    *   5: Run calibration routines & set values
    *   6: Run UUT tests
    *   7: Write user Signature bytes 
    *   8: Load UUT bootloader code
    *   9: Read UUT configuration into database
    *  10: HUGnet Load UUT application code
    *  11: Verify communications & Test Application code
    *  12: Power Down
    *  13: Display passed and log test data
    *
    * @return void
    *   
    */
    public function run104603Test()
    {
        $this->display->clearScreen();
        $this->display->displayHeader("Testing 104603 Dual Battery Coach");

        $this->_FAIL_FLAG = false;

        $result = $this->_runTestSteps();

        if (($result == self::PASS) and (!$this->_FAIL_FLAG)) {
            $this->display->displayPassed();
        } else {
            $this->display->displayFailed();
        }


        $choice = readline("\n\rHit Enter to Continue: ");
    }


    /**
    *******************************************************************
    * Run Test Steps Routine
    *
    * This function runs the test steps on the 104603 Dual Battery
    * Socializer board.  The steps increment and continue as long as 
    * the each step result passes.
    *
    * @return void
    */
    private function _runTestSteps()
    {
        $stepNum = 0;
        do {
            $stepNum++;
            switch ($stepNum) {
                case 1:
                    $stepResult = $this->_checkEvalBoard();
                    break;
                case 2:
                    $stepResult = $this->_testUUTpower();
                    break;
                case 3:
                    $stepResult = $this->_loadTestFirmware();
                    break;
                case 4:
                    $stepResult = $this->_checkUUTBoard();
                    break;
                case 5:
                    $this->_MICRO_SN = $this->_readMicroSN();
                    $stepResult = $this->_runUUTadcCalibration();
                    break;
                case 6:
                    $stepResult = $this->_runUUTdacCalibration();
                    break;
                case 7:
                    $stepResult = $this->_runCurrentCalibration();
                    break;
                case 8:
                    $this->_ENDPT_SN = $this->getSerialNumber();
                    $stepResult = $this->_testUUT();
                    break;
                case 9:
                   if (!$this->_FAIL_FLAG) {
                      $stepResult = $this->_loadUUTprograms();
                    } 
                    break;
            }
        } while (($stepResult != self::HFAIL) and ($stepNum < 9));

        if ($stepNum > 3) {
         $this->_logTestData($stepResult);
        }
        $this->_powerUUT(self::OFF);
        $this->_clearTester();

        return $stepResult;
    }
    
    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/
    
    
    /**
    ***********************************************************
    * Power UUT Test
    *
    * This function powers up the 10460302 board, measures the 
    * bus voltage and the 3.3V to verify operation for the next
    * step which is loading the test firmware.
    *
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    public function _testUUTpower()
    {
        $this->_system->out("Testing UUT Power UP");
        $this->_system->out("******************************");

        $testResult = $this->_powerUUT(self::ON);
        sleep(1);

        if ($testResult == self::PASS) {
            $voltsVB = $this->_readTesterBusVolt();
            $voltsPV = $this->_readTesterP12BusVolt();
            //$this->_TEST_DATA["BusVolts"] = $voltsVB;

            /*if (($voltsVB > 11.5) and ($voltsVB < 13.00)) {
                $VccVolts = $this->_readTesterVCC();
                $this->_TEST_DATA["Vcc"] = $VccVolts;

                if (($VccVolts > 3.1) and ($VccVolts < 3.4)) {
                    $testResult = self::PASS;
                } else {
                    $testResult = self::HFAIL;
                    $this->_TEST_FAIL[] = "Vcc Volts Failed:".$volts."V";
                    $this->_powerUUT(self::OFF);
                }

            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "Bus Volts Failed:".$voltsVB."V";
                $this->_powerUUT(self::OFF);
            } */
        }

        if ($testResult == self::HFAIL) {
                $this->_system->out("\n\rUUT power failed!\n\r");
                $this->display->displayFailed();
        } else {
            $this->_system->out("UUT Power UP - Passed\n\r");
        }
        
        return $testResult;
    }

    /**
    ***********************************************************
    * Power UUT through VBUS Routine
    * 
    * This function controls the +12V supply to the UUT through  
    * opening or closing of relay K1.
    * +12V On  = K1 closed 
    * +12V Off = K1 open 
    *
    * @return boolean result
    */
    public function _powerUUT($state)
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
        
        if ($ReplyData == "30") {
            $result = self::PASS; /* Pass */

            $dataVal = "0301";
            /* set or clear relay K2 */
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);

        } else { 
            $result = self::HFAIL;  /* Failure */
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
    *   1. UUT Supply Voltage Tests
    *   2. On Board Thermistor tests                            
    *   3. Port 1 test                                         
    *   4. Port 2 test                                          
    *   5. Vbus load test                                       
    *   6. External thermistor test                            
    *   7. LED tests   
    *
    * @return $result  
    */
    private function _testUUT()
    {
 
        $this->display->displaySMHeader(" B E G I N N I N G   U U T   T E S T I N G ");
        $testNum = 0;

        do {
            $testNum += 1;
            switch ($testNum) {
                case 1:
                    $testResult = $this->_testUUTsupplyVoltages();
                    break;
                case 2:
                    $testResult = $this->_testUUTThermistors();
                    break;
                case 3: 
                    $testResult = $this->_testUUTport1();
                    break;
                case 4: 
                    $testResult = $this->_testUUTport0();
                    break;
                case 5:
                    $testResult = $this->_testUUTvbus();
                    break;
                case 6:
                    $testResult = $this->_testUUTexttherms();
                    break;
                case 7:
                    $testResult = $this->_testUUTleds();
                    break;
            }

        } while (($testResult != self::HFAIL) and ($testNum < 7));


        return $testResult;
    }

    /**
    **************************************************************
    * UUT Supply Voltage Test Routine
    *
    * This function sends a command to the UUT to measure its 
    * Vbus and Vcc voltages to see if they are in spec before 
    * starting Port voltage tests.
    *
    * @return integer testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTsupplyVoltages()
    {
        $this->_system->out("Testing UUT Supply Voltages");
        $this->_system->out("******************************");

        $voltsVbus = $this->_readUUTBusVolts();
        sleep(1);

        if (($voltsVbus > 11.4) and ($voltsVbus < 13.0)) {
            $voltsVcc = $this->_readUUTVccVolts();

            if (($voltsVcc > 2.8) and ($voltsVcc < 3.45)) {
                $this->_system->out("UUT Supply Voltages - PASSED!");
                $testResult = self::PASS;
            } else {
                $this->_system->out("UUT Supply Voltages - FAILED!");
                $this->_TEST_FAIL[] = "UUT Vcc Volts Fail:".$voltsVcc."V";
                $testResult = self::HFAIL;
            }
        } else {
            $testResult = self::HFAIL;
            $this->_system->out("UUT Bus Voltage - FAILED!");
            $this->_TEST_FAIL[] = "UUT Bus Volts Fail:".$voltsVbus."V";
        }
        
        $this->_system->out("");

        return $testResult;

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

       /* $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_3V_SW_COMMAND;
        $dataVal = "01";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $newData = $this->_convertReplyData($ReplyData);
        $this->out("3v3 reply data :".$newData." !");*/

        $volts = $this->_readTesterSW3();
        $sw3V = number_format($volts, 2);

        $this->_system->out("3.3V Switched measures ".$sw3V." volts!");

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
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTThermistors()
    {
        $this->_system->out("Testing Thermistors");
        $this->_system->out("******************************");
        sleep(1);

        $busTemp0 = $this->_readUUTBusTemp0();
        $this->_TEST_DATA["BusTemp0"] = $busTemp0;

        if (($busTemp0 > 11.00) and ($busTemp0 < 26.00)) {
            $resultT1 = self::PASS;
        } else {
            $resultT1 = self::FAIL;
            $this->_TEST_FAIL[] = "Bus Temp0:".$busTemp0."C";
        }

        $busTemp1 = $this->_readUUTBusTemp1();
        $this->_TEST_DATA["BusTemp1"] = $busTemp1;

        if (($busTemp1 > 11.00) and ($busTemp1 < 26.00)) {
            $resultT2 = self::PASS;
        } else {
            $resultT2 = self::FAIL;
            $this->_TEST_FAIL[] = "Bus Temp1:".$busTemp1."C";
        }
        
        $p1Temp = $this->_readUUTP1Temp();
        $this->_TEST_DATA["P1Temp"] = $p1Temp;

        if (($p1Temp > 11.00) and ($p1Temp < 26.00)) {
            $resultT3 = self::PASS;
        } else {
            $resultT3 = self::FAIL;
            $this->_TEST_FAIL[] = "Port 1 Temp:".$p1Temp."C";
        }
        
        $p0Temp = $this->_readUUTP0Temp(); 
        $this->_TEST_DATA["P0Temp"] = $p0Temp;

        if (($p0Temp > 11.00) and ($p0Temp < 26.00)) {
            $resultT4 = self::PASS;
        } else {
            $resultT4 = self::FAIL;
            $this->_TEST_FAIL[] = "Port 0 Temp:".$p0Temp."C";
        }

        if (($resultT1 == self::PASS) and ($resultT2 == self::PASS)
           and ($resultT3 == self::PASS) and ($resultT4 == self::PASS)) {
            $testResult = self::PASS;
            $this->_system->out("UUT Thermistors - PASSED!");
        } else {
            $testResult = self::FAIL;
            $this->_FAIL_FLAG = true;
            $this->_system->out("UUT Thermistors - FAILED!");
        }

        $this->_system->out("");
        return $testResult;
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
    * @return integer $testResult  1=pass, 0=fail, -1=HFAIL
    */
    public function _testUUTport1()
    {
        $this->_system->out("Testing UUT Port 1");
        $this->_system->out("******************************");

        $this->_setPort1Load(self::ON);
        $this->_setPort1(self::ON);

        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $this->_TEST_DATA["P1Volts"] = $p1Volts;

        $p1Amps = $this->_readUUTPort1Current();
        $this->_TEST_DATA["P1Current"] = $p1Amps;

        if (($p1Volts > 11.40) and ($p1Volts < 13.00) and 
            ($p1Amps > 0.7)) {
            //$testResult = $this->_runP1FaultTest();
            $testResult = self::PASS;
            $this->_setPort1(self::OFF);

            if ($testResult == self::PASS) {
                $voltsP1 = $this->_readTesterP1Volt();
                $p1Volts = $this->_readUUTPort1Volts();
                $p1Amps = $this->_readUUTPort1Current();
                
                if ($p1Volts <= 0.1) {
                    $testResult = self::PASS;
                    $this->_system->out("Port 1 Load Test - PASSED!");
                } else {
                    $testResult = self::HFAIL;
                    $this->_system->out("Port 1 Load Test - FAILED!");
                    $this->_TEST_FAIL[] = "P1 Off Load:".$p1Volts."V";
                }
            } else {
                $this->_system->out("Port 1 Fault Test - FAILED!");
            }
        } else {
            $this->_setPort1(self::OFF);
            $this->_system->out("Port 1 Load Test - FAILED!");
            $this->_TEST_FAIL[] = "P1 On Load:".$p1Volts."V ".$p1Amps."A";
            $testResult = self::HFAIL;
        }
        $this->_setPort1Load(self::OFF); /* Disconnect Port 1 Load */
        $this->_system->out("");
        return $testResult;
    }

    /**
    ***************************************************************
    * Port 1 Fault Test Routine
    *
    * This function runs the fault test on the Port number that 
    * is passed to it.
    * 
    * @return $int $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _runP1FaultTest()
    {
        $this->_system->out("PORT 1 FAULT ON:");
        $this->_faultSet(1, 1); /* Set fault */
        sleep(1);

        /* Measure Port 1 voltage */
        $voltsP1 = $this->_readTesterP1Volt();
        $this->_TEST_DATA["P1Fault"] = $voltsP1;

        if ($voltsP1 < 0.1) {
            $this->_system->out("PORT 1 FAULT OFF:");
            $this->_faultSet(1, 0); /* Remove fault */
            sleep(1);

            $voltsP1 = $this->_readTesterP1Volt();
            
            if (($voltsP1 > 11.00) and ($voltsP1 < 13.00)) {
                $testResult = self::PASS;
            } else {
                $this->_TEST_FAIL[] = "P1 Fault Off:".$voltsP1."V";
                $testResult = self::HFAIL;
            }
        } else {
            $this->_system->out("PORT 1 FAULT OFF:");
            $this->_faultSet(1, 0);
            sleep(1);
            $this->_TEST_FAIL[] = "P1 Fault On:".$voltsP1."V";
            $testResult = self::HFAIL;
        }

        return $testResult;
    }


    /**
    ***************************************************************
    * Port 0 Load Test Routine
    *
    * This function connects a load to Port 0 and turns on the 
    * FET.  It then measures the port voltage and reads the 
    * Port voltage and current from the UUT.  It tests the 
    * voltage and current against expected values for each.  Then
    * it applies the fault signal to verify that the port is 
    * turned off during a fault condition.
    * 
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    public function _testUUTport0()
    {
        $this->_system->out("Testing UUT Port 0 ");
        $this->_system->out("******************************");

        $this->_setPort0Load(self::ON);
        $this->_setPort0(self::ON);

        $voltsP0 = $this->_readTesterP0Volt();
        $p0Volts = $this->_readUUTPort0Volts();
        $this->_TEST_DATA["P0Volts"] = $p0Volts;

        $p0Amps = $this->_readUUTPort0Current();
        $this->_TEST_DATA["P0Current"] = $p0Amps;

        if (($p0Volts > 11.40) and ($p0Volts < 13.00) and
            ($p0Amps > 0.7)) {
            //$testResult = $this->_runP0Faulttest();
            $testResult = self::PASS;
            $this->_setPort0(self::OFF);
            sleep(1);

            if ($testResult == self::PASS) {
                $voltsP0 = $this->_readTesterP0Volt();
                $p0Volts = $this->_readUUTPort0Volts();
                $p0Amps = $this->_readUUTPort0Current();

                if ($p0Volts <= 0.2) {
                    $testResult = self::PASS;
                    $this->_system->out("Port 0 Load Test - PASSED!");
                } else {
                    $testResult = self::HFAIL;
                    $this->_system->out("Port 0 Load Test - FAILED!");
                    $this->_TEST_FAIL[] = "P0 Off Load:".$p0Volts."V";
                }
            } else {
                $this->_system->out("Port 0 Fault Test - FAILED!");
            }
        } else {
            $this->_setPort0(self::OFF);
            $this->_system->out("Port 0 Fault Test - FAILED!");
            $this->_TEST_FAIL[] = "P0 On Load:".$p0Volts."V ".$p0Amps."A";
            $testResult = self::HFAIL;
        }
        $this->_setPort0Load(self::OFF); /* Remove 12 ohm load */
	
        $this->_system->out("");
        return $testResult;
    }

    /**
    ***************************************************************
    * Port 0 Fault Test Routine
    *
    * This function runs the fault test on Port 0
    * 
    * @return $int $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _runP0FaultTest()
    {
        $this->_system->out("PORT 2 FAULT ON:");
        $this->_faultSet(0, 1); /* Set fault */
        sleep(1);
        
        /* Measure Port 0 voltage */
        $voltsP0 = $this->_readTesterP0Volt();
        $this->_TEST_DATA["P0Fault"] = $voltsP0;

        if ($voltsP0 < 0.1) {
            $this->_system->out("PORT 0 FAULT OFF:");
            $this->_faultSet(0, 0); /* Remove fault */
            sleep(1);

            $voltsP0 = $this->_readTesterP0Volt();
            
            if (($voltsP0 > 11.00) and ($voltsP0 < 13.00)) {
                $testResult = self::PASS;
            } else {
                $this->_TEST_FAIL[] = "P0 Fault Off:".$voltsP0."V";
                $testResult = self::HFAIL;
            }
        } else {
            $this->_system->out("PORT 0 FAULT OFF:");
            $this->_faultSet(0, 0);
            sleep(1);
            $this->_TEST_FAIL[] = "P0 Fault On:".$voltsP0."V";
            $testResult = self::HFAIL;
        }

        return $testResult;
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
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTvbus()
    {
        $this->_system->out("Testing UUT VBUS");
        $this->_system->out("******************************");
        sleep(1);

        $testResult = $this->_Port1ToVbusTest();

        if ($testResult == self::PASS) {
            $testResult = $this->_Port0ToVbusTest();
        }

        if ($testResult == self::PASS) {
            $this->_system->out("VBus Load Test - PASSED!");
        } else {
            $this->_system->out("VBus Load Test - FAILED!");
        }
            

        $this->_system->out("");
        return $testResult;
    }

    /**
    *******************************************************************
    * Port 1 to VBus Test Routine
    *
    * This function tests the ability of Port 1 to supply current and
    * voltage to VBus.  It assumes that Vbus is currently connected 
    * to +12V through relay K1.
    *
    * @return int $testResult 1=pass, 0=fail, -1=hard fail
    */
    private function _Port1ToVbusTest()
    {
        $this->_setPort1_V12(self::ON); /* +12V to Port 1 */
        $voltsP1 = $this->_readTesterP1Volt();
        if (($voltsP1 > 11.50) and ($voltsP1 < 13.00)) { 
            $this->_setVBus_V12(self::OFF); /* connects 12 ohm load */
            sleep(1);
            $voltsVB = $this->_readTesterBusVolt();
            $VBvolts = $this->_readUUTBusVolts();

            if ($VBvolts < 0.2) {
                $this->_setPort1(self::ON);
                sleep(1);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $p1Volts = $this->_readUUTPort1Volts();
                $p1Amps = $this->_readUUTPort1Current();

                if (($VBvolts > 11.4) and ($VBvolts < 13.00) and
                    ($p1Amps < -0.7)) {
                    $this->_setPort1(self::OFF);
                    sleep(1);
                    $voltsVB = $this->_readTesterBusVolt();
                    $p1Amps = $this->_readUUTPort1Current();

                    if ($voltsVB < 0.3) {
                        $testResult = self::PASS;
                    } else { 
                        $this->_system->out("P1 off to Vbus:".$voltsVB."V");
                        $this->_TEST_FAIL[] = "P1 off to Vbus:".$voltsVB."V";
                        $testResult = self::HFAIL;
                    }

                } else {
                    $this->_setPort1(self::OFF);
                    $this->_setVBus_V12(self::ON);
                    $this->_system->out("P1 on to Vbus Fail :".$VBvolts."V ".$p1Amps."A");
                    $this->_TEST_FAIL[] = "P1 on to Vbus:".$VBvolts."V ".$p1Amps."A";
                    $this->_setPort1_V12(self::OFF);
                    $testResult = self::HFAIL;
                }
            } else {
                $this->_setVBus_V12(self::ON);
                $this->_TEST_FAIL[] = "Bus Voltage Off:".$VBvolts."V";
                $this->_setPort1_V12(self::OFF);
                $testResult = self::HFAIL;
            }
        } else {
            $this->_setPort1_V12(self::OFF);
            $this->_TEST_FAIL[] = "P1 Supply :".$voltsP1."V";
            $voltsP1 = $this->_readTesterP1Volt();
            $this->_system->out("Port 1  Tester = ".$voltsP1." volts");
            $testResult = self::HFAIL;
        }

        return $testResult;
    }
   
    /**
    *******************************************************************
    * Port 0 to VBus Test Routine
    *
    * This function tests the ability of Port 0 to supply current and
    * voltage to VBus.  It assumes that Vbus is currently connected 
    * to the load resistor through relay K1 and Port 0 is connected to
    * +12V through relay K4.
    *
    * @return int $testResult 1=pass, 0=fail, -1=hard fail
    */
    private function _Port0ToVbusTest()
    {
        $this->_setPort0_V12(self::ON);
        sleep(2);
        $voltsP0 = $this->_readTesterP0Volt();
        $p0Volts = $this->_readUUTPort0Volts();
        
        if (($p0Volts > 11.5) and ($p0Volts < 13.00)) {
            $this->_setPort1_V12(self::OFF);
            sleep(1);
            $voltsP1 = $this->_readTesterP1Volt();
            $this->_setPort0(self::ON);
            sleep(1);
            $voltsVB = $this->_readTesterBusVolt();
            $VBvolts = $this->_readUUTBusVolts();
            $p0Volts = $this->_readUUTPort0Volts();
            $p0Amps = $this->_readUUTPort0Current();

            if (($VBvolts > 11.5) and ($VBvolts < 13.00) and 
                ($p0Amps < -0.7)) {
                $this->_setPort0(self::OFF);
                sleep(1);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $p0Amps = $this->_readUUTPort0Current();
                
                if ($VBvolts < 0.3) {
                    $testResult = self::PASS;
                } else {
                    $testResult = self::HFAIL;
                    $this->_TEST_FAIL[] = "P0 off to Vbus:".$VBvolts."V";
                }
                $this->_setVBus_V12(self::ON);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $this->_setPort0_V12(self::OFF);
                $voltsP0 = $this->_readTesterP0Volt();

            } else {
                $this->_setPort0(self::OFF);
                $this->_setPort0_V12(self::OFF);
                $this->_system->out("P0 on to Vbus Fail :".$VBvolts."V ".$p0Amps."A");
                $this->_TEST_FAIL[] = "P0 on to Vbus:".$VBvolts."V ".$p0Amps."A";
                $this->_setVBus_V12(self::ON);
                $this->_setPort1_V12(self::OFF);
                $testResult = self::HFAIL;
            }
        } else {
            $this->_setPort0_V12(self::OFF);
            $this->_TEST_FAIL[] = "P0 Supply :".$p0Volts."V";
            $this->_setVBus_V12(self::ON);
            $this->_setPort1_V12(self::OFF);
            $testResult = self::HFAIL;
        }

        return $testResult;
    }


    /**
    *******************************************************************
    * External Thermistor Test Routine
    *
    * This function tests the external thermistor connections by 
    * connecting known resistance values to the thermistor connections
    * and then testing the UUT measurement values.
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTexttherms()
    {
        $this->_system->out("Testing UUT External Thermistor Circuits");
        $this->_system->out("****************************************");
        $this->_system->out("EXT THERM CIRCUITS OPEN:");

        $extTemp1 = $this->_readUUTExtTemp1();
        $extTemp2 = $this->_readUUTExtTemp2();

        if (($extTemp1 > 3.0) and ($extTemp1 < 3.4)) {
            if (($extTemp2 > 3.0) and ($extTemp2 < 3.4)) {
                $result1 = self::PASS;
            } else {
                $result1 = self::HFAIL;
                $this->_TEST_FAIL[] = "Ext Therm2 High:".$extTemp2."V";
            }
        } else {
            $result1 = self::HFAIL;
            $this->_TEST_FAIL[] = "Ext Therm1 High:".$extTemp1."V";
        }

        $this->_setExternalTherms(self::ON);
        $extTemp1 = $this->_readUUTExtTemp1();
        $extTemp2 = $this->_readUUTExtTemp2();

        if ($extTemp1 < 0.2) {
            if ($extTemp2 < 0.2) {
                $result2 = self::PASS;
            } else {
                $result2 = self::HFAIL;
                $this->_TEST_FAIL[] = "Ext Therm2 Low:".$extTemp2."V";
            }
        } else {
            $result2 = self::HFAIL;
            $this->_TEST_FAIL[] = "Ext Therm1 Low:".$extTemp1."V";
        }
        $this->_setExternalTherms(self::OFF);
        if (($result1 == self::PASS) and ($result2 == self::PASS)) {
            $this->_system->out("External Thermistor Test - PASSED!");
            $testResult = self::PASS;
        } else {
            $this->_system->out("External Thermistor Test - FAILED!");
            $testResult = self::FAIL;
        }

        $this->_system->out("");
        return $testResult;
    }

    /**
    **************************************************************
    * Test LEDs Routine
    *
    * This function sets the LED's in different patterns of 
    * on and off states and asks the operator to verify these 
    * states.
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTleds()
    {
        $this->_system->out(" Testing LEDs");
        $this->_system->out("**************");

        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "01"; /*turn on Green Status LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $choice = readline("\n\rAre both Green Status LEDs on? (Y/N) ");
        if (($choice == "Y") || ($choice == "y")) {
            $result1 = self::PASS; 
        } else {
            $result1 = self::FAIL;
            $this->_TEST_FAIL[] = "Green LED Fail";
        }

        $dataVal = "02"; /* Turn on Red status LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); 

        $choice = readline("\n\rAre both Red Status LEDs on? (Y/N) ");
        if (($choice == "Y") || ($choice == "y")) {
            $result2 = self::PASS; 
        } else {
            $result2 = self::FAIL;
            $this->_TEST_FAIL[] = "Red LED Fail";
        }

        $dataVal = "00";  /* Turn off all LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
       
        if (($result1 == self::PASS) and ($result2 == self::PASS)) {
            $this->_system->out("LED Test - PASSED!");
            $testResult = self::PASS;
        } else {
            $this->_system->out("LED Test - FAILED!");
            $this->_FAIL_FLAG = true;
            $testResult = self::FAIL;
        }
        
        $this->_system->out("");
        return $testResult;
    }
    
    /**
    *****************************************************************
    * Load Programs into UUT Routine
    *
    * This routine programs the user signature bytes, loads the 
    * the bootloader code, loads the application code and performs
    * a simple test to verify the functionality of the application.
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _loadUUTprograms()
    {

        $this->display->displaySMHeader(" L O A D I N G   U U T   P R O G R A M S ");
        $loadNum = 0;

        do {
            $loadNum += 1;
            switch ($loadNum) {
                case 1:
                    $testResult = $this->_writeUserSigFile();
                    break;
                case 2:
                    $testResult = $this->_eraseUserSig();
                    break;
                case 3: 
                    $testResult = $this->_writeUserSig();
                    break;
                case 4: 
                    $testResult = $this->_loadBootloaderFirmware();
                    break;
                case 5:
                    $testResult = $this->_runReadConfig();
                    break;
                case 6:
                    $testResult = $this->_loadApplicationFirmware();
                    break;
                case 7:
                    $testResult = $this->_setPowerTable();
                    break;
                case 8:
                    $testResult = $this->_runApplicationTest();
                    break;
            }

        } while (($testResult == self::PASS) and ($loadNum < 8));

        return $testResult;
    }
    
    /**
    *****************************************************************
    * Test Application code Routine
    *
    * This routine runs a couple of quick tests to verify that
    * the application code is indeed up and running.
    *
    * @return integer $testResult  1=passed, 0=failed, -1=hard fail
    */
    private function _runApplicationTest()
    {
        $this->display->displaySMHeader(" Testing Application Program ");
        sleep(3);

        $this->_system->out("Checking UUT Communication");
        $decVal = hexdec($this->_ENDPT_SN);
        $this->_system->out("Pinging Serial Number ".$decVal);
        $replyData = $this->_pingEndpoint($decVal);
        $replyData = true;
        if ($replyData == true) {
            $this->_system->out("UUT Board Responding!");
            $testResult = $this->_runPort1AppTest($decVal);
            
            if ($testResult == self::PASS) {
                $testResult = $this->_runPort0AppTest($decVal);
                if ($testResult == self::PASS) {
                    $this->_system->out("Application Test - PASSED!");
                } else {
                    $this->_system->out("Application Test - FAILED!");
                }
                
            } else {
                $this->_system->out("Application Test - FAILED!");
            }
        } else {
            $this->_system->out("UUT Board Failed to Respond!\n\r");
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "UUT with App Code Failed Com";
        }
        
        return $testResult;
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
    private function _runPort1AppTest($SNVal)
    {
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
            sleep(2);

            $voltsP1 = $this->_readTesterP1Volt();
            $this->_setPort1Load(self::OFF); /* Remove 12 ohm load */

            if ($voltsP1 < 0.3) {
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
        }

        return $testResult;
    }



    /**
    *************************************************************
    * Run Port 0 Application Test Routine
    *
    * This function runs the port 0 test on the application code
    * to verify that the application code is running properly.
    *
    * @param integer $SNval device serial number
    *
    * @return integer $testResults  1=pass, 0=fail, -1=hard fail
    */
    private function _runPort0AppTest($SNval)
    {
        $chan = 0;
        $this->_setPort0Load(self::ON);
        sleep(1);
        $voltsP0 = $this->_readTesterP0Volt();

        $this->_system->out("Turning on Port 0");
        $this->_setControlChan($SNval, $chan, self::ON);
        sleep(1);
    
        $voltsP0 = $this->_readTesterP0Volt();
        
        if (($voltsP0 > 11.00) and ($voltsP0 < 13.00)) {
            $this->_system->out("Turning off Port 0");
            $this->_setControlChan($SNval, $chan, self::OFF);
            sleep(2);

            $voltsP0 = $this->_readTesterP0Volt();
            $this->_setPort0Load(self::OFF); /* Remove 12 ohm load */

            if ($voltsP0 < 0.3) {
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "App Code P0 off:".$voltsP0."V";
            }
        } else {
            $this->_system->out("Application Test - FAILED!");
            $this->_setControlChan($SNval, $chan, self::OFF);
            sleep(1);
            $this->_setPort0Load(self::OFF);
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "App Code P0 on:".$voltsP0."V";
        }
        
        return $testResult;
    }


    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*               T E S T E R   A N A L O G   R O U T I N E S                 */
    /*                                                                           */
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
    public function _readTesterVCC()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VCC_PORT);
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 6.6;
        $vccv = number_format($volts, 2);
	
        $this->_system->out("Tester Vcc Volts = ".$vccv." volts");
        return $vccv;
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
    public function _readTesterBusVolt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VBUS_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsVB = number_format($volts, 2);

       $this->_system->out("Tester Bus Volts = ".$voltsVB." volts");
        return $voltsVB;
    }
    
    /**
    ************************************************************
    * Read Port 0 Voltage
    * 
    * This function reads the Battery Socializer Port 0 
    * voltage and returns the value.
    * 
    * @return $volts  a floating point value for Bus voltage 
    */
    public function _readTesterP0Volt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_P0_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsP0 = number_format($volts, 2);
	
        $this->_system->out("Port 0 Tester    = ".$voltsP0." volts");
        return $voltsP0;
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
    public function _readTesterP1Volt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_P1_PORT);

        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsP1 = number_format($volts, 2);
	
        $this->_system->out("Port 1 Tester    = ".$voltsP1." volts");
        return $voltsP1;
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
    * Read +12V Bus Voltage
    * 
    * This function reads the Battery Socializer +12V Bus
    * voltage and returns the value.
    * 
    * @return $volts  a floating point value for Bus voltage 
    */
    public function _readTesterP12BusVolt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_P12VBUS_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsVB = number_format($volts, 2);

       $this->_system->out("Tester +12V Bus Volts = ".$voltsVB." volts");
        return $voltsVB;
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
    /*                                                                           */
    /*                 U U T   A N A L O G   R O U T I N E S                     */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/
    

    /**
    **************************************************************
    * Read UUT Port 0 Voltage
    *
    * This function gets the averaged adc reading for the Port 2
    * adc channel input.  It sends a command to the UUT to return
    * the DataChan value and then converts the adc steps into 
    * voltage values.  Index = 0
    *
    * @return $volts  Port 0 voltage value.
    */
    public function _readUUTPort0Volts()
    {
        
        $rawVal = $this->_readUUT_ADCval(self::UUT_P0_VOLT);
       
        if ($rawVal > 0x7ff) {
            $newVal= dechex($rawVal);
            $len = strlen($newVal);
            $hexVal = substr($newVal, $len-4, 4);
            $rawVal = $this->_twosComplement_to_negInt($hexVal);
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        
        $p2Volts = number_format($volts, 2);
        $this->_system->out("Port 0 UUT       = ".$p2Volts." volts");

        return $p2Volts;
    }


     /**
    ************************************************************
    * Read UUT Bus Temperature 0 Routine
    * 
    * This function reads the Bus temperature internally measured 
    * by the Unit Under Test (UUT). Index 4
    *
    * @return $rawVal 
    */
    public function _readUUTBusTemp0()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_BUS_TEMP0);

        $bTemp = $this->_convertTempData($rawVal);
        $busTemp = number_format($bTemp, 2);
        
        $this->_system->out("Bus Temp 0  : ".$busTemp." C");
        return $busTemp;

    }

     /**
    ************************************************************
    * Read UUT Bus Temperature 1 Routine
    * 
    * This function reads the Bus temperature internally measured 
    * by the Unit Under Test (UUT). Index 5
    *
    * @return $rawVal 
    */
    public function _readUUTBusTemp1()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_BUS_TEMP1);

        $bTemp = $this->_convertTempData($rawVal);
        $busTemp = number_format($bTemp, 2);
        
        $this->_system->out("Bus Temp 1  : ".$busTemp." C");
        return $busTemp;

    }
     /**
    *****************************************************************
    * Read UUT Port 0 Temperature Routine
    * 
    * This function reads the Port 0 temperature internally measured 
    * by the Unit Under Test (UUT). Index 6
    *
    * @return $rawVal 
    */
    public function _readUUTP0Temp()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P0_TEMP);

        $p2Temp = $this->_convertTempData($rawVal);
        $port2Temp = number_format($p2Temp, 2);
        
        $this->_system->out("Port 0 Temp : ".$port2Temp." C");
        return $port2Temp;

    }


     /**
    ************************************************************
    * Read UUT Port 1 Current Routine
    * 
    * This function reads the Port 1 Current flow measured 
    * by the Unit Under Test (UUT).  Index 7
    *
    * @return $volts 
    */
    public function _readUUTPort1Current()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_CURRENT);
        $rawVal += $this->_P1_AOFFSET;
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / $this->_P1_AGAIN;  /* 0.0532258;  */ 
        $p1Amps = number_format($current, 2);

        $this->_system->out("Port 1 Current   = ".$p1Amps." amps");
        return $p1Amps;
    }


     /**
    ************************************************************
    * Read UUT Port 2 Current Routine
    * 
    * This function reads the Port 2 Current flow measured 
    * by the Unit Under Test (UUT).  Index 8
    *
    * slope for the current to voltage output from the 
    * current sensor IC is:
    *       y2 - y1       3.3 - 1.65     1.65
    *  m = ---------  =  ------------ = ------ = 0.0532258
    *       X2 - X1        31 - 0         31
    *
    * @return $volts 
    */
    public function _readUUTPort0Current()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P0_CURRENT);
        $rawVal += $this->_P0_AOFFSET;
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / $this->_P0_AGAIN;  /* 0.0532258;  */ 

        $p2Amps = number_format($current, 2);
        $this->_system->out("Port 0 Current   = ".$p2Amps." amps");

        return $p2Amps;
    }

 
    /**
    ************************************************************
    * Read UUT Bus Voltage Routine
    * 
    * This function reads the Bus Voltage internally measured 
    * by the Unit Under Test (UUT). Index 2
    *
    * @return $volts 
    */
    public function _readUUTBusVolts()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_BUS_VOLT);
        if ($rawVal > 0x7ff) {
            $newVal= dechex($rawVal);
            $len = strlen($newVal);
            $hexVal = substr($newVal, $len-4, 4);
            $rawVal = $this->_twosComplement_to_negInt($hexVal);
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsVB = number_format($volts, 2);
        
        $this->_system->out("UUT Bus Volts    = ".$voltsVB." volts");
        return $voltsVB;

    }

     /**
    *****************************************************************
    * Read UUT External Temperature 2 Routine
    * 
    * This function reads the external temperature 2 measured 
    * by the Unit Under Test (UUT). Index 9
    *
    * @return $rawVal 
    */
    public function _readUUTExtTemp2()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_EXT_TEMP2);
        if ($rawVal > 0x7ff) {
            $rawVal = 0xffff - $rawVal;
        }

        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts *= 2;
        
        $extTemp2 = number_format($volts, 2);
        $this->_system->out("ExtTemp 2 Voltage = ".$extTemp2." volts");

        return $extTemp2;
    }

     /**
    *****************************************************************
    * Read UUT External Temperature 1 Routine
    * 
    * This function reads the external temperature 1 measured 
    * by the Unit Under Test (UUT). Index 10
    *
    * @return $rawVal 
    */
    public function _readUUTExtTemp1()
    {
        $rawVal = $this->_readUUT_ADCval("a");
        if ($rawVal > 0x7ff) {
            $rawVal = 0xffff - $rawVal;
        }

        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts *= 2;
        
        $extTemp1 = number_format($volts, 2);
        $this->_system->out("ExtTemp 1 Voltage = ".$extTemp1." volts");

        return $extTemp1;
    }

     /**
    *****************************************************************
    * Read UUT Port 1 Temperature Routine
    * 
    * This function reads the Port 1 temperature internally measured 
    * by the Unit Under Test (UUT). Index 11
    *
    * @return $rawVal 
    */
    public function _readUUTP1Temp()
    {
        $rawVal = $this->_readUUT_ADCval("b");

        $p1Temp = $this->_convertTempData($rawVal);
        $port1Temp = number_format($p1Temp, 2);
        
        $this->_system->out("Port 1 Temp : ".$port1Temp." C");
        return $port1Temp;

    }

    /**
    **************************************************************
    * Read UUT Port 1 Voltage
    *
    * This function gets the averaged adc reading for the Port 1
    * adc channel input.  It sends a command to the UUT to return
    * the DataChan value and then converts the adc steps into 
    * voltage values. Index = 1
    *
    * @return $volts  Port 1 voltage value.
    */
    public function _readUUTPort1Volts()
    {
        
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_VOLT);
        if ($rawVal > 0x7ff) {
            $newVal= dechex($rawVal);
            $len = strlen($newVal);
            $hexVal = substr($newVal, $len-4, 4);
            $rawVal = $this->_twosComplement_to_negInt($hexVal);
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        
        $p1Volts = number_format($volts, 2);
        $this->_system->out("Port 1 UUT       = ".$p1Volts." volts");

        return $p1Volts;
    }

    /**
    ************************************************************
    * Read UUT Calibration Voltage Routine
    *
    * This function reads the Calibration voltage that has been
    * applied by the tester to the UUT ADC 11 input.
    *
    * @return $volts
    */
    public function _readUUTCalVolts()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_CAL_VOLT);
        
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;
   
        $voltsVCal = number_format($volts, 3);
        
        $this->_system->out("UUT Calibration Volts = ".$voltsVCal." volts");
        return $voltsVCal;
    
    }
    
    /**
    ************************************************************
    * Read UUT Vcc Voltage Routine
    * 
    * This function reads the Vcc Voltage internally measured 
    * by the Unit Under Test (UUT). Index 12
    *
    * @return $volts 
    */
    public function _readUUTVccVolts()
    {
        $rawVal = $this->_readUUT_ADCval("c");

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 10;
        
        $VccVolts = number_format($volts, 2);
        $this->_system->out("UUT Vcc Volts    = ".$VccVolts." volts");
        return $VccVolts;

    }

    /**
    ************************************************************
    * Read UUT DAC Voltage Routine
    * 
    * This function reads the DAC output voltage internally 
    * measured by the Unit Under Test (UUT). Index 13
    *
    * @return $volts 
    */
    private function _readUUTdacVolts()
    {
        $rawVal = $this->_readUUT_ADCval("d");

        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;
        
        return $volts;

    }

    /**
    *********************************************************
    * Read UUT ADC DataChan Value
    * 
    * This functin sends a command to the UUT to return the 
    * DataChan value for the ADC channel passed in to it.
    *
    * @return $newData a hex value representing adc reading
    */
    private function _readUUT_ADCval($inputNum)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::READ_ANALOG_COMMAND;
        $dataVal = sprintf("0%s",$inputNum);

        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $newData = $this->_convertReplyData($ReplyData);


        return $newData;
    }
    

    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*               T E S T   U T I L I T I E S   R O U T I N E S               */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

    /**
    ***********************************************************
    * Display Test Data Routine
    *
    * This function displays the test data collected in the 
    * test data array.  The array will be json encoded for 
    * adding to DeviceTests database.
    *
    */
    private function _displayTestData()
    {
        $testData = json_encode($this->_TEST_DATA);

        $this->_system->out("TEST DATA: ".$testData);
    }

    /**
    ***********************************************************
    * Display Test Results Routine
    *
    * This function displays the test results collected in the 
    * test results array.  The array will be json encoded for 
    * adding to DeviceTests database.
    *
    */
    private function _displayTestFailures()
    {
        $testFail = json_encode($this->_TEST_FAIL);

        $this->_system->out("TEST FAIL: ".$testFail);
    }


    /**
    ************************************************************
    * Set Port 1 Load Routine
    *
    * This function connects the 12 ohm load resistor to port
    * port 1 and delays for 1 second before returning.  It 
    * assumes that relay K3 is open so that the load is 
    * selected.
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    public function _setPort1Load($state)
    {
        switch ($state) {
            case 0: 
                /* make sure K3 relay is open to select 12 ohm load */
                $this->_setRelay(3,0); 
                /* open relay K4 to remove 12 ohm load from port 1 */
                $this->_setRelay(4,0);
                break;
            case 1:
                /* make sure K3 relay is open to select 12 ohm load */
                $this->_setRelay(3,0); 
                /* close relay K4 to connect 12 ohm load to Port 1 */
                $this->_setRelay(4, 1);
                break;
        }
        sleep(1);

    }

    /**
    ***********************************************************
    * Set Port 1 State Routine
    *
    * This function turns Port 1 FET's on or off for the load
    * or supply tests and delays 1 second.
    *
    * @param int $state  1=on, 0= off
    * @return void
    */
    public function _setPort1($state)
    {
        switch ($state) {
            case 0:
                $this->_setPort(1,0); /* Port 1 off */
                $this->_system->out("PORT 1 OFF:");
                break;
            case 1:
                $this->_setPort(1, 1); /* Port 1 On */
                $this->_system->out("PORT 1 ON:");
                break;
        }

        sleep(1);
    }
    
    /**
    ************************************************************
    * Set Port 1 +12V Routine
    *
    * This function connects the +12V supply to port 1
    * and delays for 1 second before returning.  
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    public function _setPort1_V12($state)
    {
        switch ($state) {
            case 0: 
                $this->_setRelay(4, 0);  /* open K4 to remove +12V */
                $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                $this->_system->out("Port 1 +12V OFF:");
                break;
            case 1:
                $this->_setRelay(3, 1);  /* close K3 to select +12V  */
                $this->_setRelay(4, 1);  /* close K4 to connect +12V */
                $this->_system->out("PORT 1 +12V CONNECTED:");
                break;
        }
        sleep(1);

    }

    /**
    ************************************************************
    * Set Port 0 Load Routine
    *
    * This function connects the 12 ohm load resistor to
    * port 0 and delays for 1 second before returning. It 
    * assumes that relay K5 is open so that the load is 
    * selected.
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    public function _setPort0Load($state)
    {
        switch ($state) {
            case 0: 
                /* make sure K5 is open to select 12 ohm load */
                $this->_setRelay(5,0); 
                /* open relay K6 to disconnect 12 ohm load from Port 0 */
                $this->_setRelay(6,0);
                break;
            case 1:
                /* make sure K5 is open to select 12 ohm load */
                $this->_setRelay(5,0); 
                /* close relay K6 to connect 12 ohm load to Port 0 */
                $this->_setRelay(6, 1);
                break;
        }
        sleep(1);

    }

    /**
    ***********************************************************
    * Set Port 0 State Routine
    *
    * This function turns Port 0 FET's on or off for the load
    * or supply tests and delays 1 second.
    *
    * @param int $state  1=on, 0= off
    * @return void
    */
    public function _setPort0($state)
    {
        switch ($state) {
            case 0:
                $this->_setPort(0,0); /* Port 0 off */
                $this->_system->out("PORT 0 OFF:");
                break;
            case 1:
                $this->_setPort(0, 1); /* Port 0 On */
                $this->_system->out("PORT 0 ON:");
                break;
        }

        sleep(1);
    }
    
    /**
    ************************************************************
    * Set Port 0 +12V Routine
    *
    * This function connects the +12V supply to port 0
    * and delays for 1 second before returning.  
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    public function _setPort0_V12($state)
    {
        switch ($state) {
            case 0: 
                $this->_setRelay(6, 0);  /* open K6 to remove +12V from Port 0 */
                $this->_setRelay(5, 0);  /* open K5 to select 12 Ohm load */
                $this->_system->out("Port 0 +12V OFF:");
                break;
            case 1:
                $this->_setRelay(5, 1);  /* close K5 to select +12V */
                $this->_setRelay(6, 1);  /* close K6 to connect +12V to Port 0 */
                $this->_system->out("PORT 0 +12V CONNECTED:");
                break;
        }
        sleep(1);

    }

    /**
    ************************************************************
    * Set VBus Routine
    *
    * This function sets the connection to VBus for either +12V
    * on state or the 12 ohm load in the off state.
    *
    * @param int $state  1=On, 0=Off
    *
    * @return void
    */
    public function _setVBus_V12($state)
    {
        switch ($state) {
            case 0:
                $this->_setRelay(2, 0); /* Open K2 to disconnect Vbus */
                sleep(1);
                $this->_setRelay(1, 0); /* open K1 to select load */
                $this->_setRelay(2, 1); /* close K2 to connect load to Vbus */
                $this->_system->out("VBUS 12 OHM LOAD CONNECTED:");
                break;
            case 1:
                $this->_setRelay(1, 1);  /* close K1 to select +12V */
                $this->_setRelay(2, 1);  /* close K2 to connect to Vbus */
                $this->_system->out("Bus Voltage ON:");
                break;
        }
        sleep(1);
    }


    /**
    ************************************************************
    * Set External Thermistor Inputs Routine
    *
    * This function sets the relays for the inputs to the 
    * external thermistors either open or closed depending
    * on the value passed in the state parameter for the 
    * purpose of testing the high and low ranges.
    *
    * @param int $state   1=closed, 0=open
    *
    * @return void
    */
    public function _setExternalTherms($state)
    {
        switch ($state) {
            case 0:
                $this->_setRelay(7, 0);
                $this->_setRelay(8, 0);
                $this->_system->out("EXT THERM CIRCUITS OPEN:");
                sleep(1);
                break;
            case 1:
                $this->_setRelay(7, 1);
                $this->_setRelay(8, 1);
                $this->_system->out("EXT THERM CIRCUITS CLOSED:");
                sleep(1);
                break;
        }
    }

     /**
    ************************************************************
    * Set Relay Routine
    *
    * This function sets the given relay either closed or open.
    * 
    * @param $relay relay number 1-8
    * @param $state 0 = open or 1 = closed
    *
    * @return $result true = success; false = failure
    *
    */
    public function _setRelay($relay, $state)
    {
        $idNum = self::EVAL_BOARD_ID;
        
        if ($state == 1) {
	    $cmdNum = self::SET_DIGITAL_COMMAND;
	} else {
	    $cmdNum = self::CLR_DIGITAL_COMMAND;
	}
	
	switch ($relay) {
	  case 1:
	    $dataVal = "0300";  /* V+ or Load to VBus */
	    break;
	  case 2:
	    $dataVal = "0301";  /* Connect to VBus */
	    break;
	  case 3:
	    $dataVal = "0302";  /* V+ or Load to Port 1 */
	    break;
	  case 4:
	    $dataVal = "0303";  /* Connect to Port 1 */
	    break;
	  case 5:
	    $dataVal = "0204";  /* V+ or Load to Port 2 */
	    break;
	  case 6:
	    $dataVal = "0205";  /* Connect to Port 2 */
	    break;
	  case 7:
	    $dataVal = "0206";  /* External Therm 1 */
	    break;
	  case 8:
	    $dataVal = "0207";  /* External Therm 2 */
	    break;
	}
	
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        if ($ReplyData == "00") {
            $result == false; 
        } else {
            $result = true;
        }
        
        
	return $result;
    }
    
    /**
    ************************************************************
    * Fault Set Routine
    *
    * This function sets or clears the fault current condition
    * on the Power Ports to test the circuits response.
    *
    * @param $portNum  Power port 1 or 0
    * @param $state    0= clear, 1=set 
    *
    * @return integer $testResult
    */
    public function _faultSet($portNum, $state)
    {
        $idNum = self::EVAL_BOARD_ID;
        
        if ($state == 1) {
            $cmdNum = self::SET_DIGITAL_COMMAND;
        } else {
            $cmdNum = self::CLR_DIGITAL_COMMAND;
        }
    
    
        if ($portNum == 1) {
            $dataVal = "0200";  /* PC0 */
        } else {
            $dataVal = "0201";  /* PC1 */
        }
        
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        if ($portNum == 1) {
            if ($ReplyData == "20") {
                $testResult = self::PASS;
            } else {
                $testResult = self::FAIL;
            }
        } else {
            if ($ReplyData == "21") {
                $testResult = self::PASS;
            } else {
                $testResult = self::FAIL;
            }
        }
        
        return $testResult;
    }

    /**
    ************************************************************
    * Set UUT Port Routine
    *
    * This function takes the port number and state inputs and
    * sets the appropriate port on or off.
    *
    * @return $result
    */
    private function _setPort($portNum, $state)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_POWERPORT_COMMAND; 

        if ($portNum == 1) {
            if ($state == 0) {
                $dataVal = "0100";
            } else if ($state == 1) {
                $dataVal = "0101";
            } else {
                $dataVal = "0202";
            }
        } else if ($portNum == 0) {
            if ($state == 0) {
                $dataVal = "0000";
            } else if ($state == 1) {
                $dataVal = "0001";
            } else {
                $dataVal = "0202";
            }
        } else {
            $dataVal = "0202";
        }

        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "02") {
            $result = false;
        } else {
            $result = true;
        }

        return $result;
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
            $dataVal = "01";
        } else {
            $dataVal = "00";
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
                    $dataVal = "01204E0000";
                } else {
                    $dataVal = "0100000000";
                }
                break;
            case 0:
                if ($state == self::ON) {
                    $dataVal = "00204E0000";
                } else {
                    $dataVal = "0000000000";
                }
                break;
        }
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Set Control Channel Reply = ".$ReplyData);

    }

    /**
    ************************************************************
    * Clear Ports Routine
    * 
    * This function removes the port connections and sets 
    * the default 12 ohm load selection.
    *
    */
    public function _clearPorts()
    {
        $this->_setPort1(self::OFF);
        $this->_setRelay(3,0);
        $this->_setRelay(4,0);
        $this->_setPort0(self::OFF);
        $this->_setRelay(5,0);
        $this->_setRelay(6,0);
    }

    /**
    ************************************************************
    * Clear Tests Routine
    *
    * This function clears the testers digital outputs in the 
    * event of a test failure.
    *
    */
    public function _clearTester()
    {
        $idNum = self::EVAL_BOARD_ID;
        $cmdNum = self:: RESET_DIGITAL_COMMAND;
        $dataVal = "00";

        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

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
            $testResult = self::PASS;
        } else {
            $testResult = self::HFAIL;
            $this->_system->out("\n\rEval Board Communications Failed!\n\r");
            $this->_TEST_FAIL[] = "Eval Board Comm Fail";
        }

        return $testResult;
    }

     /**
    ************************************************************
    * Check UUT Board Routine
    *
    * This function pings the 104603 Unit Under Test (UUT) board
    * and returns the results.
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    public function _checkUUTBoard()
    {
        $this->_system->out("Testing UUT Communications");
        $this->_system->out("******************************");
        $Result = $this->_pingEndpoint(self::UUT_BOARD_ID);
        if ($Result == true) {
            $this->_system->out("UUT Board Responding!\n\r");
            $testResult = self::PASS;
        } else {
            $this->_system->out("UUT Board Failed to Respond!\n\r");
            $testResult = self::HFAIL;
            $this->_powerUUT(self::OFF);                    
            $this->_system->out("\n\rUUT Communications Failed!\n\r");
            $this->_TEST_FAIL[] = "UUT Com Fail";
        }

        return $testResult;
    }

    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*                  F I R M W A R E    R O U T I N E S                       */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/
    

    

    /**
    **************************************************************
    * Write User Signature Bytes
    *
    * This function sends a command to the UUT to write the 
    * hardware part number, serial number, adc offset and adc
    * gain error correction bytes to the user signature memory
    * page.
    *
    */
    private function _writeUserSig()
    {
        $this->_system->out("Writing User Signature Bytes!");

        $Avrdude = "sudo avrdude -px32e5 -c avrisp2 -P usb -B 10 -i 100 ";
        $usig  = "-U usersig:w:104603test.usersig:r ";

        $Prog = $Avrdude.$usig;
        exec($Prog, $output, $return); 

        if ($return == 0) {
            $this->_system->out("Writing User Signature Bytes - PASSED");
            $result = self::PASS;
        } else {
            $this->_system->out("Writing User Signature Bytes - FAILED");
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Fail to write User Sig Bytes";
        }
        
        return $result;
    }

    /**
    **************************************************************
    * Erase User Signature Routine
    *
    * This function sends a command to the UUT to erase the user
    * signature bytes.
    *
    */
    private function _eraseUserSig()
    {

        $this->_system->out("Sending Erase User Signature Command!");
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::ERASE_USERSIG_COMMAND;
        $dataVal = "00";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        if ($ReplyData == "80") {
            $this->_system->out("Erase User Signature - PASSED");
            $result = self::PASS;
        } else {
            $this->_system->out("Erase User Signature - FAILED");
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Failed to Erase User Signature Memory";
        }
        
        
        return $result;
    }

    /**
    * Write User Signature File Routine
    *
    * This function collects the information needed for 
    * the user signature bytes and writes them out into 
    * a raw hex file that can be used to program the 
    * user signature memory.
    *
    */
    private function _writeUserSigFile()
    {
     
        $this->display->displaySMHeader("Creating UserSig Data File");

        $SNdata = $this->_ENDPT_SN;
        $HWPNdata = self::HWPN;
        $CALdata = $this->_ADC_OFFSET;
        $CALdata .= $this->_ADC_GAIN;
        $DACcal = $this->_DAC_OFFSET;
        $DACcal .= $this->_DAC_GAIN;

        $this->_system->out("SNdata   = ".$SNdata);
        $this->_system->out("HWPNdata = ".$HWPNdata);
        $this->_system->out("CALdata  = ".$CALdata);
        $this->_system->out("DACcal   = ".$DACcal);
        
        $Sdata = $SNdata.$HWPNdata.$CALdata.$DACcal;

        $SIGdata = pack("H*",$Sdata);
        $fp = fopen("104603test.usersig","wb");
        if ($fp != NULL) {
            fwrite($fp, $SIGdata);
            $this->_system->out("User Signature Bytes File Written!");
            $result = self::PASS;
        } else {
            $this->_system->out("Failed to write Signature bytes file!");
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Failed to Write Sig Bytes File";
        }
        fclose($fp);

 
        return $result;
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
    public function _loadTestFirmware()
    {
        $output = array();
        $this->display->displayHeader("Loading Test Firmware");
        

        $FUSE1 = 0x00;
        $FUSE2 = 0xFE;  /* changed to FE to use 0x0000000 as reset vector */
        $FUSE3 = 0xFF;
        $FUSE4 = 0xFF;
        $FUSE5 = 0xE1;
        $FUSE6 = 0xFF;


        $Avrdude = "sudo avrdude -px32e5 -c avrisp2 -P usb -e -B 10 -i 100 ";
        $flash = "-U flash:w:104603test.ihex ";
        $fuse1 = "-U fuse1:w:".$FUSE1.":m ";
        $fuse2 = "-U fuse2:w:".$FUSE2.":m ";
        $fuse4 = "-U fuse4:w:".$FUSE4.":m ";
        $fuse5 = "-U fuse5:w:".$FUSE5.":m ";

        $Prog = $Avrdude.$flash.$fuse1.$fuse2.$fuse4.$fuse5;
        exec($Prog, $output, $return); 

        if ($return == 0) {
            $result = self::PASS;
        } else {
            $result = self::HFAIL;
            $this->_TEST_FAIL[] = "Load Test Firmware Failed";
        }

        return $result;

    }

    /**
    ************************************************************
    * Load bootloader routine
    *
    * This function loads the 10460301 endpoint with the  
    * bootloader firmware.  The firmware is load through the 
    * AVR2 serial programmer.
    *
    * @return int $result  
    */
    public function _loadBootloaderFirmware()
    {
        $output = array();
        $this->display->displayHeader("Loading Bootloader Firmware");
        

        $FUSE1 = 0x00;
        $FUSE2 = 0xBE;  
        $FUSE3 = 0xFF;
        $FUSE4 = 0xFF;
        $FUSE5 = 0xE1;
        $FUSE6 = 0xFF;


        $Avrdude = "sudo avrdude -px32e5 -c avrisp2 -P usb -e -B 10 -i 100 ";
        $flash = "-U flash:w:104603boot.ihex ";  /* changed to .srec from .ihex */
        $eeprm = "-U eeprom:w:104603boot.eep ";
        $fuse1 = "-U fuse1:w:".$FUSE1.":m ";
        $fuse2 = "-U fuse2:w:".$FUSE2.":m ";
        $fuse4 = "-U fuse4:w:".$FUSE4.":m ";
        $fuse5 = "-U fuse5:w:".$FUSE5.":m ";

        $Prog = $Avrdude.$flash.$eeprm.$fuse1.$fuse2.$fuse4.$fuse5;
        exec($Prog, $output, $return); 

        if ($return == 0) {
            $this->_system->out("Loading Bootloader - SUCCESSFUL");
            $result = self::PASS;
        } else {
            $this->_system->out("Loading Bootloader - FAILED");
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Failed to load Bootloader";
        }

        sleep(2);
        return $result;

    }
    
    
     
    /**
    ************************************************************
    * Set Power Table Routine
    *
    * This routine sets up the power table in a UUT that already
    * has the application code loaded.  It sets both power ports
    * to a null power driver so they can be controlled with 
    * a set control chan command.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    */
    public function _setPowerTable()
    {
        $this->_system->out("");
        $this->_system->out("Setting Power Table");
        $this->_system->out("*******************");
        
        $decVal = hexdec($this->_ENDPT_SN);
        $idNum = $decVal;
        $cmdNum = self::SET_POWERTABLE_COMMAND;
        $portData = "00";
        $driverData ="A0000001";  /* Driver, Subdriver, Priority and mode */
        $driverName = "4C6F616420310000000000000000000000000000000000";
        $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
        $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
        $dataVal = $portData.$driverData.$driverName.
                    $fillData.$fillData2;
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $ReplyData = substr($ReplyData, 0, 14);
        $this->_system->out("Port 1 Reply = ".$ReplyData);
        
        $testReply = substr($ReplyData, 0, 4);
        if ($testReply == "A000") {
            $portData = "01";
            $dataVal = $portData.$driverData.$driverName.
                        $fillData.$fillData2;
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $ReplyData = substr($ReplyData, 0, 14);
            $this->_system->out("Port 2 Reply = ".$ReplyData);
            
            $testReply = substr($ReplyData, 0, 4);
            if ($testReply == "A000") {
                $this->_system->out("Setting Power Table - PASSED!");
                $testResult = self::PASS;
            } else {
                $this->_system->out("Setting Power Table - FAILED!");
                $testResult = self::FAIL;
                $this->_TEST_FAIL[] = "Fail Setting P2 Power Table";
            }
        } else {
            $this->_system->out("Setting Power Table - FAILED!");
            $testResult = self::FAIL;
            $this->_TEST_FAIL[] = "Fail Setting P1 Power Table";
        }
        
        $this->_system->out("");
        return $testResult;
    
    }
    
    
    /**
    ************************************************************
    * Read Endpoint Configuration Routine
    *
    * This function call the hugnet_readconfig script to 
    * read the endpoing configuration into the database which
    * allows the application firmware to be loaded.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    *
    */
    private function _runReadConfig()
    {
        $this->_system->out("Reading Battery Socializer Configuration");
        
        $hugnetReadConfig = " ../misc/./hugnet_readconfig -i ";
        
        $Prog = $hugnetReadConfig.$this->_ENDPT_SN;
        
    
        system($Prog, $return);
        
        if ($return == 0) {
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Failed ReadConfig";
        }
        
        return $result;
    
    }

    /**
    ************************************************************
    * Load Application Firmware Routine
    *
    * This function loads the battery socializer application 
    * firmware into the UUT.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    *
    */
    private function _loadApplicationFirmware()
    {
        $this->display->displayHeader("Loading Application Firmware");

        $hugnetLoad = "../bin/./hugnet_load";
        $firmwarepath = "~/code/HOS/packages/104603-00393801C-0.4.0-B63.gz";

        $Prog = $hugnetLoad." -i ".$this->_ENDPT_SN." -D ".$firmwarepath;

        system($Prog, $return);

        if ($return == 0) {
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Fail to load Application";
        } 

        sleep(2);
        return $result;
    
    }


    /**
    ************************************************************
    * Get Starting Serial Number Routine
    *
    * This function asks user to input a starting serial number
    * in the correct format.  The program then uses the serial
    * number for the first board and is able to increment the 
    * value for additional boards.
    *
    * @return $SN the serial number in integer form.
    */
    public function getSerialNumber()
    {
        do {
            $this->display->displaySMHeader("   OBTAINING SERIAL NUMBER   ");
            $this->_system->out("Enter a hex value for the serial number");
            $SNresponse = readline("in the following format- hhhh: ");
            $this->_system->out("\n\r");
            $this->_system->out("Your serial number is: ".$SNresponse);
            $response = readline("Is this correct?(Y/N): ");
        } while (($response <> 'Y') && ($response <> 'y'));

        /* now pad it out with zeros so total is 10 bytes */
        $len = strlen($SNresponse);
        $counter = 0;

        if ($len < 10) {
            do {
                $SNresponse = "0".$SNresponse;
                $len = strlen($SNresponse);
                $counter++;

            } while (($len < 10) and ($counter < 9));
        };

        $this->_system->out("SN response is ".$SNresponse);
        $this->_system->out("");

        return $SNresponse;
    }
    
    /**
    *********************************************************
    * Read the Microcontroller Serial Number Routine
    *
    * This routine sends a command to the UUT to read
    * the serial number out of the production signature
    * memory space.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    */
    public function _readMicroSN()
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::READ_PRODSIG_COMMAND;
        $dataVal = "00";
    
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        /* new conversion routine */
        $len = strlen($ReplyData);
        /* copy lot number so it reads Lotnum 5 .... Lotnum0 */
        for ($i=0; $i < 6; $i++) {
            $start = 0 + (2*$i);
            $newData = substr($ReplyData, $start, 2) . $newData;
        }
        /* copy wafer number */
        $newData .= substr($ReplyData, 12, 2);

        /* next copy X1 and X0 */
        $newData .= substr($ReplyData, 16, 2);
        $newData .= substr($ReplyData, 14, 2);

        /* Finally add Y1 and Y0 */
        $newData .= substr($ReplyData, 20, 2);
        $newData .= substr($ReplyData, 18, 2);
        
        $this->_system->out("Serial Number is : ".$newData);
        $this->_system->out("");
        
        return $newData;
    }


    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*               C A L I B R A T I O N     R O U T I N E S                   */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

    /***********************************************************/
    /*               ADC  CALIBRATION  ROUTINES                */
    /***********************************************************/


    /**
    ************************************************************
    * Run UUT ADC Calibration Routine
    *
    * This function runs the calibration routines that determine
    * the adc offset correction value and gain error correction 
    * value.  Those values are set in the UUT for testing and 
    * saved to write into the user signature memory along with 
    * the serial number and hardware part number.
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    public function _runUUTadcCalibration()
    {
        $this->display->displaySMHeader("  Entering ADC Calibration Routine  ");

        $testResult = $this->_setupP1_ADCoffset();

        if ($testResult == self::PASS) {
            $offsetHexVal = $this->_runAdcOffsetCalibration();
            $testResult = $this->_setupReferenceVoltage();
        
            if ($testResult == self::PASS) {
                $gainErrorValue = $this->_runAdcGainCorr($offsetIntVal);
                $this->_TEST_DATA["ADCgain"] = $gainErrorValue;
                sleep(1);
                
                $this->_system->out("\n\r** Setting ADC Gain Correction **");
                $retVal = $this->_setAdcGainCorr($gainErrorValue);
                $this->_ADC_GAIN = $retVal;
                
                $voltsCal1 = $this->_readUUTCalVolts();
                $this->display->displaySMHeader(" ADC CALIBRATION COMPLETE! ");
                $testResult = self::PASS;
            } else {
                $this->_system->out("Failed to set up 1.235V reference.");
            } 
        }

        return $testResult;
    }

    /**
    ***********************************************************
    * Set Up Port 1 for ADC Calibration Routine
    *
    * This function sets up Port 1 for the ADC offset
    * calibration.  It connects the load resistor, turns on
    * the port, measures and tests the voltage, turns off the 
    * port and measures and tests the voltage for offset 
    * calibration.
    *
    * @return int $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _setupP1_ADCoffset()
    {
        $this->_setPort1Load(self::ON);
        sleep(1);
        $this->_setPort1(self::ON);
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();
        sleep(1);

        if (($p1Volts > 11.00) and ($p1Volts < 13.00)) {
            if ($p1Amps > 0.35) {
                $this->_setPort1(self::OFF);
                sleep(2);
                $p1Volts = $this->_readUUTPort1Volts();
            
                if ($p1Volts <= 0.2) {
                    $testResult = self::PASS;
                } else {
                    $this->_system->out("Port 1 fail, unable to calibrate ADC!");
                    $this->_setPort1Load(self::OFF);
                    $this->_TEST_FAIL[] = "P1 Off in ADC Cal:".$p1Volts."V";
                    $testResult = self::HFAIL;
                }
            } else {
                $this->_system->out("Port 1 current fail unable to calibrate ADC!");
                $this->_setPort1(self::OFF);
                $this->_setPort1Load(self::OFF);
                $this->_TEST_FAIL[] = "P1 current in ADC Cal:".$p1Amps."A";
                $testResult = self::HFAIL;
            }
        } else {
            $this->_system->out("Port 1 fail unable to calibrate ADC!");
            $this->_setPort1(self::OFF);
            $this->_setPort1Load(self::OFF);
            $this->_TEST_FAIL[] = "P1 On in ADC Cal:".$p1Volts."V";
            $testResult = self::HFAIL;
        }

        return $testResult;
    }


   
    /**
    ***********************************************************
    * Run ADC offset Calibration Routine
    *
    * This function performs the adc offset calibration by 
    * measuring port 1 voltage which is set at zero volts.  It
    * then calculates the offset error, converts the value to a 
    * two byte hex value which is written to the adc offset 
    * correction register.
    *
    *
    */
    private function _runAdcOffsetCalibration()
    {
 
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_VOLT);
        if ($rawVal > 0x7ff) {
            $newVal= dechex($rawVal);
            $len = strlen($newVal);
            $hexVal = substr($newVal, $len-4, 4);
            $rawVal = $this->_twosComplement_to_negInt($hexVal);
        }
    

        $offsetIntVal = number_format($rawVal, 0, "", "");
        $hexVal = dechex($offsetIntVal);
        $len = strlen($hexVal);
    
        if ($len < 4) {
            do {
                $hexVal = "0".$hexVal;
                $len = strlen($hexVal);
            } while ($len < 4);
        } else {
            $hexVal = substr($hexVal, len-4, 4);
        }
        $this->_system->out("Hex Value for the offset ".$hexVal);
      
        $this->_system->out("*** Setting ADC Offset ****");
        $offsetHexVal = $this->_setAdcOffset($hexVal);

        $this->_ADC_OFFSET = $offsetHexVal;
        $this->_TEST_DATA["ADCoffset"] = $offsetHexVal;
        $this->_setPort1Load(self::OFF);

        $this->_system->out("");
        return $offsetHexVal;
    
    }

    /**
    ************************************************************
    * Set Up Reference Voltage Routine
    *
    * This function removes the load resistor from Port 1, 
    * reads the 1.235 volt reference voltage to ADC input and 
    * tests the voltage.
    */
    private function _setupReferenceVoltage()
    {
        $this->_system->out("Reading 1.235 Calibration Reference");
        $voltsCal1 = $this->_readUUTCalVolts();
        
        if (($voltsCal1 > 1.0) and ($voltsCal1 < 1.5)) {
            $result = self::PASS;
        } else {
            $result = self::HFAIL;
            $this->_TEST_FAIL[] = "1.235V Ref Failed :".$voltsCal1."V";
        }

        return $result;
    }

    
    /**
    ************************************************************
    * Run ADC Gain Error Correction Routine
    * 
    * This function performs the gain error correction by 
    * measuring the reference voltage input which is set at
    * 1.235V.  It then uses the following formula
    * to calculate the gain error correction value:
    *
    *                         expected value
    *  GAIN CORR = 2048 X -----------------------
    *                      (MeasuredValue - OffsetCor)
    *
    */
    private function _runAdcGainCorr($offsetIntValue)
    {

        $rawAvg = $this->_readUUT_ADCval(self::UUT_CAL_VOLT);;
        
        $this->_system->out("\n\r");
        $this->display->displaySMHeader("  Calculating Gain Error Value  ");
        
        $gainIntVal = number_format($rawAvg, 0, "", "");
        $this->_system->out("Integer Value = ".$gainIntVal);
        
        $tempVal = $gainIntVal - $offsetIntValue;
        //$this->_system->out("Gain Int - Offset Int = ".$tempVal);
        
        $gainRatio = 1533/ $tempVal;
        //$this->_system->out("Gain Ratio = ".$gainRatio);
        
        $gainVal = 2048 * $gainRatio;
    
    
        $gainIntValue = number_format($gainVal, 0, "", "");
        $this->_system->out("Gain Value    = ".$gainVal);
    
        $hexVal = dechex($gainIntValue);
    
        $len = strlen($hexVal);
        if ($len < 4) {
            while ($len < 4) {
                $hexVal = "0".$hexVal;
                $len = strlen($hexVal);
            }
        } else if ($len > 4) {
            $hexVal = substr($hexVal, $len-4, 4);
        }

        return $hexVal;
    }
    
    /**
    ************************************************************
    * Set ADC Offset Routine
    *
    * This function sends a command and data to the UUT to 
    * set the ADC offset correction register and enable
    * ADC error correction.
    *
    * @param $dataVal a two byte hex string
    *
    * @return $offVal  a two byte string read from offsetCorr
    */
    private function _setAdcOffset($dataVal)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_ADCOFFSET_COMMAND; 

        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        $this->_system->out("Offset Value   = ".$ReplyData);
        
        return $ReplyData;
    
    }
    
    /**
    ************************************************************
    * Set ADC Gain Error Correction Routine
    *
    * This function sends a command and the data to the UUT to
    * set the ADC gain error correction register and enable 
    * ADC error correction
    *
    * @param $dataVal a two byte hex string
    *
    * @return $retVal a two byte string read from gainCorr
    */
    private function _setAdcGainCorr($dataVal)
    {
         $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_ADCGAINCOR_COMMAND; 
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        $this->_system->out("Gain Set Value = ".$ReplyData);
        
        return $ReplyData;
    }
   

    /**********************************************************/
    /*              DAC  CALIBRATION  ROUTINES                */
    /**********************************************************/


    /**
    ************************************************************
    * Run UUT DAC Calibration Routine
    *
    * This function runs the calibration routines that determine
    * the DAC offset and gain error correction values. 
    * Those values are saved to write into the user signature 
    * memory along with the serial number, hardware part number
    * and adc offset and gain correction values.
    */
    private function _runUUTdacCalibration()
    {
        $this->display->displaySMHeader(" Entering DAC Calibration Routine ");
        $result = $this->_runDacOffsetCalibration();
        
        if ($result == self::PASS) {
            $this->_system->out("Setting DAC offset to :".$this->_DAC_OFFSET);
            $this->_setDacOffset($this->_DAC_OFFSET);
            $this->_TEST_DATA["DACoffset"] = $this->_DAC_OFFSET;
            sleep(1);

            $dacVal = "0400";
            $this->_setDAC($dacVal);
            $dacVolts = $this->_readUUTdacVolts();
            $this->_system->out("Adjusted DAC volts : ".$dacVolts);
        } else {
            $this->_system->out("Offset Calibration Failed!");
            $this->_FAIL_FLAG = true;
        }
        
        if ($result == self::PASS) {
            $result = $this->_runDacGainCalibration();
            
            if ($result == self::PASS) {
                $this->_system->out("Setting DAC Gain to :".$this->_DAC_GAIN);
                $this->_setDacGain($this->_DAC_GAIN);
                $this->_TEST_DATA["DACgain"] = $this->_DAC_GAIN;
                sleep(1);

                $dacVal = "07C2";
                $this->_setDAC($dacVal);
                $dacVolts = $this->_readUUTdacVolts();
                $this->_system->out("Adjusted DAC volts : ".$dacVolts);
                $this->_system->out("");
            } else {
                $this->_system->out("Gain Calibration Failed!");
                $this->_FAIL_FLAG = true;
            }
        }
        $this->display->displaySMHeader("  DAC CALIBRATION COMPLETE!  ");

        return $result;
   }

    /**
    *****************************************************************
    * Set DAC Routine
    *
    * This function sets the output of the DAC to the value 
    * passed in to it.
    */
    private function _setDAC($dacVal)
    {

        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_DAC_COMMAND; 
        $dataVal = $dacVal;

        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if(!is_null($ReplyData)) {

            $newData = $this->_convertReplyData($ReplyData);
        } else {
            $newData = $ReplyData;
        }

        return $newData;
    }

    /**
    *****************************************************************
    * Run DAC offset Calibration Routine
    *
    * This function runs the DAC offset calibration.  It does so by
    * setting the DAC output to 0x400 or 1/4 of full scale and then
    * measuring the resultant output.  If it is not at 0.825 volts,
    * the DAC setting is increased or decreased until the output
    * measures correctly.  Then the amount of change necessary to 
    * produce the proper output becomes the offset correction value.
    *
    * **** IMPORTANT NOTE ****************************************
    * There is an error in the documentation for the formula for the
    * offset error calculation.
    * It should be as follows:
    *
    * Vocal = VREF x (2 x OCAL[7] - 1) + (OCAL[6]/64 + OCAL[5]/128 +
    *         OCAL[4]/256 + OCAL[3]/512 + OCAL[2]/1024 + OCAL[1]/2048
    *         + OCAL[0]/4096).
    *
    * This forumula clearly makes bit 7 a sign bit for the offset value
    * with a one being positive and a zero being negative.
    *****************************************************************
    * @param $dacStart integer value for DAC setting
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    */
    private function _runDacOffsetCalibration( )
    {
        $this->_system->out("Starting DAC offset calibration");
        $this->_system->out("Setting DAC output to ".self::DAC_OFFCAL_LEVEL." volts");

        $dacVal = self::DAC_OFFCAL_START;
        $dataVal = dechex($dacVal);
        $dataVal = "0".$dataVal;
        $replyData= $this->_setDAC($dataVal);

        $dacVolts = $this->_readUUTdacVolts();
        $this->_system->out("DAC volts = ".$dacVolts);
        
        $this->_system->out("Obtaining DAC offset ");
        
        if ($dacVolts < self::DAC_OFFCAL_LEVEL) {
            $result = $this->_runDACplusOffset($dacVolts, $dacVal);
        } else {
            $result = $this->_runDACnegOffset($dacVolts, $dacVal);
        }

        return $result;

    }
    
    /**
    ******************************************************************
    * Run DAC Plus Offset Routine
    *
    * This function increases the DAC output slowly until the output
    * voltage reaches the desired set point of 0.825 volts.  The 
    * positive offset is then determined by how many steps were 
    * necessary to meet the desired output.  
    *
    ********************************************************************
    * Explaination of offset calculation:   
    * ($dacVal - self::DAC_OFFCAL_START) represents the number of steps 
    * above the start value necessary to get the DAC output to the 
    * desired voltage level.  However, it appears that the MSB of the 
    * the offset byte is a sign bit with a 1 being positive and a zero
    * being negative.  So, in order to get the offset steps added to 
    * the DAC output, it is necessary to set the MSB or add 128 to the 
    * the offset value.
    *
    * @param float $dacVolts  DAC output voltage measurement
    * @param int $dacVal      Starting dac value for calibration
    *
    * @return int $result  1=pass, 0=fail, -1= hard fail
    *
    */
    private function _runDACplusOffset($dacVolts, $dacVal)
    {
        $error = false;
        $diffVolts = self::DAC_OFFCAL_LEVEL - $dacVolts;
        $steps = 3.3/pow(2,12);
        $numSteps = $diffVolts/$steps;
        $numSteps = round($numSteps/2);
        $dacVal += $numSteps;
        
        do {
            $dacVal++;
            $dataVal = dechex($dacVal);
            $dataVal = "0".$dataVal;
            $replyData = $this->_setDAC($dataVal);

            if(is_null($replyData)) {
                $error = true;
            } else {
                $dacVolts = $this->_readUUTdacVolts();
            }
            print "*";
        } while (($dacVolts < self::DAC_OFFCAL_LEVEL) and (!$error));

        if (!$error) {
            $offset = $dacVal - self::DAC_OFFCAL_START + 128;
            $hexOffset = dechex($offset);

            $hexOffset = $this->_oneHexByteStr($hexOffset);

            $this->_DAC_OFFSET = $hexOffset;
            $this->_system->out("");
            $this->_system->out("Offset Value = ".$hexOffset);
            $result = self::PASS;
        } else {
            $this->_TEST_FAIL[] = "Com Fail Setting DAC";
            $result = self::FAIL;
        }
        return $result;
    }


    /**
    *******************************************************************
    * Run DAC Negative Offset Routine
    *
    * this function decreases the DAC output slowly until the output
    * voltage reaches the desired set point of 0.825 volts.  The 
    * negative offset is then determined by how many steps were
    * necessary to meet the desired output.
    *
    * @param float $dacVolts  DAC output voltage measurement
    * @param int $dacVal      Starting dac value for calibration
    *
    * @return int $result  1=pass, 0=fail, -1= hard fail
    */
    private function _runDACnegOffset($dacVolts, $dacVal)
    {
        $error = false;
        do {
            $dacVal--;
            $dataVal = dechex($dacVal);
            $dataVal = "0".$dataVal;
            $replyData = $this->_setDAC($dataVal);

            if(is_null($replyData)) {
                $error = true;
            } else {
                $dacVolts = $this->_readUUTdacVolts();
            }
            print "*";
        } while (($dacVolts > self::DAC_OFFCAL_LEVEL) and (!$error));

        if (!$error) {
            $offset = self::DAC_OFFCAL_START - $dacVal;
            
            $hexOffset = dechex($offset);
            /* make sure we have one hex byte */
            $hexOffset = $this->_oneHexByteStr($hexOffset);

            $this->_DAC_OFFSET = $hexOffset;
            $this->_system->out("");
            $this->_system->out("Offset Value : ".$hexOffset);
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Com Fail Setting DAC";
        }
        return $result;
    }


    /**
    *******************************************************************
    * Run DAC Gain Calibration Routine
    *
    * This function runs the gain error calibration routine.  It does 
    * so by setting the DAC output to 1/2 of the reference voltage
    * which would be 0x800.  It then measures the DAC output and 
    * adjusts the setting until the desired output voltage is reached.
    * This then becomes the gain error correction value.
    *
    */
    private function _runDacGainCalibration()
    {
        $this->_system->out("\n\r******************************");
        $this->_system->out("Starting DAC gain calibration");
        $this->_system->out("Setting DAC output to ".self::DAC_GAINCAL_LEVEL." volts");

        $dacVal = self::DAC_GAINCAL_START;
        $dataVal = dechex($dacVal);
        $dataVal = "0".$dataVal;
        $replyData= $this->_setDAC($dataVal);

        $dacVolts = $this->_readUUTdacVolts();
        $this->_system->out("DAC volts = ".$dacVolts);
        
        $this->_system->out("Obtaining DAC gain value ");

        if ($dacVolts < self::DAC_GAINCAL_LEVEL) {
            $result = $this->_runDACplusGain($dacVolts);
       } else {
            $result = $this->_runDACnegGain($dacVolts);
       }
    
        return $result;
    }

    /**
    ************************************************************
    * Run DAC Postive Gain Routine
    *
    * This function increments the DAC gain correction value 
    * until the DAC output reaches desired set point of 1.60 volts.
    *
    * @param float $dacVolts  DAC output voltage measurement
    *
    * @return int $result  1=pass, 0=fail, -1=hard fail
    */
    private function _runDACplusGain($dacVolts)
    {
        $diffVolts = self::DAC_GAINCAL_LEVEL - $dacVolts;
        $steps = 3.3/ pow(2,12);
        $numSteps = round($diffVolts/$steps);
        $hexGainCor = dechex($numSteps);
        $len = strlen($hexGainCor);

        if ($len < 2) {
            $hexGainCor = "0".$hexGainCor;
        } else if ($len > 2) {
            $hexGainCor = substr($hexGainCor, $len-2, 2);
        }

        $replyData= $this->_setDacGain($hexGainCor);
        $dacVolts = $this->_readUUTdacVolts();
        $this->_DAC_GAIN = $hexGainCor;
        
        do {
            $error = false;
            $numSteps++;
            $hexGainCor = dechex($numSteps);
            $hexGainCor = $this->_oneHexByteStr($hexGainCor);

            $replyData = $this->_setDacGain($hexGainCor);
            if(is_null($replyData)) {
                $error = true;
            } else {
                $dacVolts = $this->_readUUTdacVolts();
            }
            print "*";
        } while (($dacVolts < self::DAC_GAINCAL_LEVEL) and (!$error));
        
        if (!$error) {
            $this->_DAC_GAIN = $hexGainCor;
            $this->_system->out("\n\rGain Error Value = ".$hexGainCor);
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Com Error Setting DAC Gain";
        } 
        return $result;
    }

    /**
    ************************************************************
    * Run DAC Negative Gain Routine
    *
    * This function decrements the DAC gain correction value 
    * until the DAC output reaches desired set point of 1.60 volts.
    *
    * @param float $dacVolts  DAC output voltage measurement
    *
    * @return int $result  1=pass, 0=fail, -1=hard fail
    */
    private function _runDACnegGain($dacVolts)
    {
        $diffVolts = $dacVolts - self::DAC_GAINCAL_LEVEL;
        $steps = 3.3/ pow(2,12);
        $numSteps = round($diffVolts/$steps);
        
        /* set the sign bit */
        $numSteps += 128;
        
        $hexGainCor = dechex($numSteps);
        $hexGainCor = $this->_oneHexByteStr($hexGainCor);
        
        $replyData= $this->_setDacGain($hexGainCor);
        $dacVolts = $this->_readUUTdacVolts();
        $this->_DAC_GAIN = $hexGainCor;
        
        do {
            $error = false;
            $numSteps++;
            $hexGainCor = dechex($numSteps);
            $hexGainCor = $this->_oneHexByteStr($hexGainCor);
            $replyData = $this->_setDacGain($hexGainCor);
            
            if(is_null($replyData)) {
                $error = true;
            } else {
                $dacVolts = $this->_readUUTdacVolts();
            }
            print "*";
        } while (($dacVolts > self::DAC_GAINCAL_LEVEL) and (!$error));
        
        if (!$error) {
            $this->_DAC_GAIN = $hexGainCor;
            $this->_system->out("\n\rGain Error Value = ".$hexGainCor);
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Com Error Setting DAC Gain";
        }
        return $result;
    }


    /**
    ************************************************************
    * Set DAC Offset Routine
    *
    * This function sends a command and data to the UUT to 
    * set the DAC offset correction register.
    *
    * @param $dataVal a one byte hex string
    *
    * @return $ReplyData  a one byte string read from offset register
    */
    private function _setDacOffset($dataVal)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_DACOFFSET_COMMAND; 
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        //$this->out("DAC Offset Set Reply Value   = ".$ReplyData);
        
        return $ReplyData;
    
    }
    
    
    /**
    ************************************************************
    * Set DAC Gain Routine
    *
    * This function sends a command and data to the UUT to 
    * set the DAC Gain error correction register.
    *
    * @param $dataVal a one byte hex string
    *
    * @return $ReplyData  a one byte string read from gain register
    */
    private function _setDacGain($dataVal)
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_DACGAIN_COMMAND; 
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        //$this->out("DAC Gain Set Reply Value   = ".$ReplyData);
        
        return $ReplyData;
    
    }


    /**********************************************************/
    /*         CURRENT INPUT CALIBRATION  ROUTINES            */
    /**********************************************************/

    /**
    ***********************************************************
    * Run Current Input Calibration Routine
    *
    * This function runs the calibration routines on the 
    * current inputs for ports 1 and 2.  It then saves the 
    * offset value for storing in the user signature memory
    * area.
    *
    * @return int $testResult 1=pass, 0=fail, -1=hard fail
    */
    public function _runCurrentCalibration()
    {
        $this->display->displaySMHeader(" Entering Current Calibration Routine ");
        
        $testResult = $this->_runPort1CurrentOffsetCal();
        
        if ($testResult == self::PASS) {
            $testResult = $this->_runPort0CurrentOffsetCal();
            if ($testResult == self::PASS) {
                $testResult = $this->_runPort1CurrentGain();
                if ($testResult == self::PASS) {
                    $testResult = $this->_runPort0CurrentGain();
                }
            }
        }
        
        $this->display->displaySMHeader("  CURRENT CALIBRATION COMPLETE!  ");
       
        return $testResult;
    }
    
    /**
    ************************************************************
    * Run Port 1 Current Offset Calibration Test
    *
    * This function runs the current calibration test on Port 1.
    * The offset counts are not set but simply recorded in the 
    * test data.
    *
    */
    private function _runPort1CurrentOffsetCal()
    {
        $this->_system->out("Running Port 1 Offset Current Calibration");
        $this->_system->out("*****************************************");
        
        $this->_setPort1Load(self::ON);
        $this->_setPort1(self::ON);
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();
        
        if (($p1Volts > 11.0) and ($p1Volts < 13.00)) {
            $this->_setPort1(self::OFF);
            sleep(1);
            $p1Volts = $this->_readUUTPort1Volts();
            $p1Amps = $this->_readUUTPort1Current();
            
            if ($p1Volts <= 0.2) {
                $expectedAmps = 0.00;
                if ($expectedAmps > $p1Amps) {
                    $p1AmpsOffset = $expectedAmps - $p1Amps;
                    $offsetNegative = false;
                } else {
                    $p1AmpsOffset = $p1Amps - $expectedAmps;
                    $offsetNegative = true;
                }
                
                $p1Aoffset = number_format($p1AmpsOffset, 4);
                $this->_system->out("Port 1 current offset: ".$p1Aoffset);
                
                $adcOffsetCounts = $this->_currentToADCcounts($p1AmpsOffset);
                
                if ($offsetNegative) {
                    $adcOffsetCounts *= -1;
                }
                
                $this->_TEST_DATA["P1CurrentOffset"] = $adcOffsetCounts;
                $this->_system->out("Port 1 current adc offset counts: ".$adcOffsetCounts);
                $this->_P1_AOFFSET = $adcOffsetCounts;
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "P1 volts fail in current cal:".$p1Volts;
            }
        } else {
            $testResult = self::HFAIL;
            $this->_setPort1(self::OFF);
            $this->_TEST_FAIL[] = "P1 volts fail in current cal:".$p1Volts;
        }
        
        sleep(1);
        $this->_setPort1Load(self::OFF);
        $this->_system->out("");
    
        return $testResult;
    }
    

    /**
    ************************************************************
    * Run Port 0 Current Offset Calibration Test
    *
    * This function runs the current calibration test on Port 0.
    * The offset counts are not set but simply recorded in the 
    * test data.
    *
    */
    private function _runPort0CurrentOffsetCal()
    {
        $this->_system->out("Running Port 0 Offset Current Calibration");
        $this->_system->out("*****************************************");
        
        $this->_setPort0Load(self::ON);
        $this->_setPort0(self::ON);
        $p0Volts = $this->_readUUTPort0Volts();
        $p0Amps = $this->_readUUTPort0Current();
        
        if (($p0Volts > 11.0) and ($p0Volts < 13.00)) {
            $this->_setPort0(self::OFF);
            sleep(1);
            $p0Volts = $this->_readUUTPort0Volts();
            $p0Amps = $this->_readUUTPort0Current();
            
            if ($p0Volts <= 0.2) {
                $expectedAmps = 0.00;
                if ($expectedAmps > $p0Amps) {
                    $p0AmpsOffset = $expectedAmps - $p0Amps;
                    $offsetNegative = false;
                } else {
                    $p0AmpsOffset = $p0Amps - $expectedAmps;
                    $offsetNegative = true;
                }
                
                $p0Aoffset = number_format($p0AmpsOffset, 4);
                $this->_system->out("Port 0 current offset: ".$p0AOffset);
                
                $adcOffsetCounts = $this->_currentToADCcounts($p0AmpsOffset);
                
                if ($offsetNegative) {
                    $adcOffsetCounts *= -1;
                }
                
                $this->_TEST_DATA["P0CurrentOffset"] = $adcOffsetCounts;
                $this->_system->out("Port 0 current adc offset counts: ".$adcOffsetCounts);
                $this->_P0_AOFFSET = $adcOffsetCounts;
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_setPort0(self::OFF);
                $this->_TEST_FAIL[] = "P0 volts fail in current cal:".$p0Volts;
            }
        } else {
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "P0 volts fail in current cal:".$p0Volts;
        }
        
        sleep(1);
        $this->_setPort0Load(self::OFF);
        $this->_system->out("");
    
        return $testResult;
    }

    /**
    ************************************************************
    * Run Port 1 Current Gain Calibration Test
    * 
    * This function measures the current value on Port 1 at 
    * full load and compares it to the calculated current value.
    * A gain factor is determined from the difference in values
    * and applied to the current measurement.
    */
    private function _runPort1CurrentGain()
    {
        $this->_system->out("Running Port 1 Current Gain Calibration");
        $this->_system->out("***************************************");
        
        $this->_setPort1Load(self::ON);
        $this->_setPort1(self::ON);
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();
    
        if (($p1Volts > 11.0) and ($p1Volts < 13.00)) {
            $expectedCurrent = $p1Volts/12;
            
            $rawVal = $this->_readUUT_ADCval(self::UUT_P1_CURRENT);
            $rawVal += $this->_P1_AOFFSET;
            $steps = 1.65/ pow(2,11);
            $volts = $steps * $rawVal;
            $volts *= 2;
        
            $this->_system->out("Y2: ".number_format($volts,4)." - Y1: 1.65 ");
            $this->_system->out("X2: ".number_format($expectedCurrent,4)." - X1: 0.0 ");
            $this->_system->out("");
            $currentGain = ($volts - 1.65)/$expectedCurrent;
            $curGain = number_format($currentGain, 6);
            $this->_system->out("Calculated Current Gain Ratio = ".$curGain);
            
            if ($curGain > 0.0) {
                $this->_P1_AGAIN = $curGain;
            }
            
            $this->_setPort1(self::OFF);
            $testResult = self::PASS;
        } else {
            $testResult = self::HFAIL;
            $this->_setPort1(self::OFF);
            $this->_TEST_FAIL[] = "P1 volts fail in current gain cal:".$p1Volts;
        }
        
        sleep(1);
        $this->_setPort1Load(self::OFF);
        $this->_system->out("");
    
        return $testResult;
    }
    
    /**
    ************************************************************
    * Run Port 0 Current Gain Calibration Test
    * 
    * This function measures the current value on Port 0 at 
    * full load and compares it to the calculated current value.
    * A gain factor is determined from the difference in values
    * and applied to the current measurement.
    */
    private function _runPort0CurrentGain()
    {
        $this->_system->out("Running Port 0 Current Gain Calibration");
        $this->_system->out("***************************************");
        
        $this->_setPort0Load(self::ON);
        $this->_setPort0(self::ON);
        $p0Volts = $this->_readUUTPort0Volts();
        $p0Amps = $this->_readUUTPort0Current();
    
        if (($p0Volts > 11.0) and ($p0Volts < 13.00)) {
            $expectedCurrent = $p0Volts/12;
            
            $rawVal = $this->_readUUT_ADCval(self::UUT_P0_CURRENT);
            $rawVal += $this->_P0_AOFFSET;
            $steps = 1.65/ pow(2,11);
            $volts = $steps * $rawVal;
            $volts *= 2;
        
            $this->_system->out("Y2: ".number_format($volts,4)." - Y1: 1.65 ");
            $this->_system->out("X2: ".number_format($expectedCurrent,4)." - X1: 0.0 ");
            $this->_system->out("");
            $currentGain = ($volts - 1.65)/$expectedCurrent;
            $curGain = number_format($currentGain, 6);
            $this->_system->out("Calculated Current Gain Ratio = ".$curGain);
            
            if ($curGain > 0.0) {
                $this->_P0_AGAIN = $curGain;
            }
            
            $this->_setPort0(self::OFF);
            $testResult = self::PASS;
        } else {
            $testResult = self::HFAIL;
            $this->_setPort0(self::OFF);
            $this->_TEST_FAIL[] = "P0 volts fail in current gain cal:".$p1Volts;
        }
        
        sleep(1);
        $this->_setPort0Load(self::OFF);
        $this->_system->out("");
    
        return $testResult;
    }
    
    
    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*              D A T A   C O N V E R S I O N   R O U T I N E S              */
    /*                                                                           */
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

    /**
    ****************************************************
    * One Hex Byte String Routine
    *
    * This function takes in a hex value string and 
    * makes sure there are 2 hex characters in the 
    * string.  
    *
    * @param string $inHexStr  input hex string
    *
    * @return string $outHexStr  output one byte string.
    */
    private function _oneHexByteStr($inHexStr)
    {
        $len = strlen($inHexStr);
        if ($len < 2) {
            $outHexStr = "0".$inHexStr;
        } else if ($len > 2) {
            $outHexStr = substr($inHexStr, $len-2, 2);
        } else {
            $outHexStr = $inHexStr;
        }

        return $outHexStr;
    }

    /**
    ****************************************************
    * Negative Integer to Two's Complement Routine
    *
    * This function takes a negative integer value and 
    * returns a two-byte twos complement value.
    *
    * @param string   $value negative integer.
    *
    * @return string A twos complement hex value
    */
    public function _negInt_to_twosComplement($value)
    {
        $maxNeg = "-32767";

        if ($value > $maxNeg) {
            if ($value <> 0 ) {
                $abVal = abs($value);
            } else {
                $abVal = 0;
            }

            /* convert to inverted hex value */
            $twosVal = dechex(~$abVal);
            $len = strlen($twosVal);
            $hexVal = substr($twosVal, $len-4, 4);
            $tempVal = hexdec($hexVal);
            $tempVal += 1;
            $retVal = dechex($tempVal);
            
           

            /* now get the correct number of hex characters */
        } else {
            $retVal = "0000";
        }

        return $retVal;
    }

    /**
    ***************************************************
    * Twos Complement to Negative Integer
    *
    * This function takes a 2 byte twos complement
    * number and returns the negative integer values.
    *
    * @param $hexVal  input 2 byte hex string
    *
    * @return $retVal a signed integer number.
    */
    public function _twosComplement_to_negInt($hexVal)
    {
        $bits = 16;

        $value = (hexdec($hexVal));
        
        $topBit = pow(2, ($bits-1));
        
        if (($value & $topBit) == $topBit) {
            $value = -(pow(2, $bits) - $value);
        }

        return $value;
    }
    
    /**
    ***************************************************
    * Current to AtoD Counts Routine
    *  
    * This function converts the current offset given
    * in amps to the offset in ADC counts.
    *
    * @param float $offsetAmps
    *
    * @return integer $adcCounts
    */
    private function _currentToADCcounts($offsetAmps)
    {
    
        $newVal = $offsetAmps * 0.0532258;
        $volts = $newVal + 1.65;
        $volts /=2;
        
        $steps = (1.65 / pow(2,11));
        $rawVal = round($volts/$steps);
        
        $this->_system->out("rawVal : ".$rawVal);
        
        $adcCounts = $rawVal - 1024;
        
        return $adcCounts;
    }

    /**
    ***************************************************
    * Fill DB Data Array Routine
    *
    * This function fills the DB_DATA array with the 
    * values accumulated from the test.
    *
    * @return void
    */
    public function _fillDBarray($result)
    {
        $this->_DB_DATA["id"] = hexdec($this->_ENDPT_SN);
        $this->_DB_DATA["HWPartNum"] = "10460301A";
        $this->_DB_DATA["FWPartNum"] = "00393801C";
        $this->_DB_DATA["FWVersion"] = "0.3.0";
        $this->_DB_DATA["BtldrVersion"] = "0.3.0";
        $this->_DB_DATA["MicroSN"] = $this->_MICRO_SN;
        $this->_DB_DATA["TestDate"] = time();
        $this->_DB_DATA["TestResult"] = $result;
        $this->_DB_DATA["TestData"] = $this->_TEST_DATA;
        $this->_DB_DATA["TestsFailed"] = $this->_TEST_FAIL;

    }

    /**
    ****************************************************
    * Log Test Data Routine
    * 
    * This function writes the test data for each 
    * board tested to the deviceTests database table.
    *
    */
    private function _logTestData($testResult)
    {
        $this->_system->out("");
        $this->display->displaySMHeader("  Logging Test Data   ");
        sleep(1);
        $this->_fillDBarray($testResult);
        $db = $this->_system->table("DeviceTests");
        $db->fromArray($this->_DB_DATA);
        $db->insertRow();
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
3