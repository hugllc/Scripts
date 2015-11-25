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
class E104603Test extends \HUGnet\ui\Daemon
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

    const READ_CONFIG_COMMAND    = 0x5C;

    const SETCONTROLCHAN_COMMAND = 0x64;
    const READCONTROLCHAN_COMMAND = 0x65;

    const HEADER_STR    = "Battery Socializer Test & Program Tool";
    
    const TSTR_VCC_PORT  = 0;
    const TSTR_VBUS_PORT = 1;
    const TSTR_P2_PORT   = 2;
    const TSTR_P1_PORT   = 3;
    const TSTR_SW3V_PORT = 4;
    
    const UUT_P2_VOLT    = 0;
    const UUT_P1_VOLT    = 1;
    const UUT_BUS_VOLT   = 2;
    const UUT_BUS_TEMP   = 3;
    const UUT_P2_TEMP    = 4;
    const UUT_P1_CURRENT = 5;
    const UUT_P2_CURRENT = 6;
    const UUT_EXT_TEMP2  = 7;
    const UUT_EXT_TEMP1  = 8;
    const UUT_P1_TEMP    = 9;
    const UUT_VCC_VOLT   = 0xA;
    const UUT_DAC_VOLT   = 0xB;
    
    const ON = 1;
    const OFF = 0;

    const PASS = 1;
    const FAIL = 0;
    const HFAIL = -1;

    const DAC_OFFCAL_LEVEL = 0.825;
    const DAC_OFFCAL_START = 1024;
    const DAC_GAINCAL_LEVEL = 1.60;
    const DAC_GAINCAL_START = 1986;
    
    const HWPN = "1046030141";
    
    

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
    private $_ENDPT_SN;
    private $_FAIL_FLAG;
    private $_MICRO_SN;
    
    private $_TEST_DATA = array(
                            "BusVolts"  => 0.0,
                            "Vcc"       => 0.0,
                            "ADCoffset" => "",
                            "ADCgain"   => "",
                            "DACoffset" => "",
                            "DACgain"   => "",
                            "BusTemp"   => 0.0,
                            "P1Temp"    => 0.0,
                            "P2Temp"    => 0.0,
                            "P1Volts"   => 0.0,
                            "P1Current" => 0.0,
                            "P1Fault"   => 0.0,
                            "P2Volts"   => 0.0,
                            "P2Current" => 0.0,
                            "P2Fault"   => 0.0,
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
        $this->display->displayHeader("Testing 104603 Dual Battery Socializer");

        $this->_FAIL_FLAG = false;
        $result = $this->_checkEvalBoard();
        if ($result == self::PASS) {
            $result = $this->_testUUTpower();
            if ($result == self::PASS) {
                $this->out("UUT Power UP - Passed\n\r");
                sleep(1);
                $this->_loadTestFirmware();
                $result = $this->_checkUUTBoard();
                $this->_MICRO_SN = $this->_readMicroSN();
                if ($result == self::PASS) {
                    $result = $this->_runUUTadcCalibration();
                    if ($result == self::PASS) {
                        $this->_runUUTdacCalibration();
                    } 
                    $this->_ENDPT_SN = $this->_getSerialNumber();
                    $result = $this->_testUUT();
                    if (($result == self::PASS) and (!$this->_FAIL_FLAG)) {
                       $result = $this->_loadUUTprograms();
                        
                        if ($result == self::PASS) {
                            $this->display->displayPassed();
                        } else {
                            $this->display->displayFailed();
                        }
                        
                    } else {
                        $this->display->displayFailed();
                        $result = self::FAIL;
                    }
                    $this->_powerUUT(self::OFF);   
                    $this->_clearTester();
                } else {
                    $this->_powerUUT(self::OFF);                    
                    $this->out("\n\rUUT Communications Failed!\n\r");
                    $this->_TEST_FAIL[] = "UUT Com Fail";
                    $this->display->displayFailed();
                }
                $this->_logTestData($result);

            } else {
                $this->out("\n\rUUT power failed!\n\r");
                $this->display->displayFailed();
            }
        } else {
            $this->out("\n\rEval Board Communications Failed!\n\r");
            $this->_TEST_FAIL[] = "Eval Board Comm Fail";
            $this->display->displayFailed();
        }

        $choice = readline("\n\rHit Enter to Continue: ");
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
    * This function powers up the 10460301 board, measures the 
    * bus voltage and the 3.3V to verify operation for the next
    * step which is loading the test firmware.
    *
    *
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTpower()
    {
        $this->out("Testing UUT Power UP");
        $this->out("******************************");

        $result = $this->_powerUUT(self::ON);
        sleep(1);

        if ($result) {
            $voltsVB = $this->_readTesterBusVolt();
            $this->_TEST_DATA["BusVolts"] = $voltsVB;

            if (($voltsVB > 11.5) and ($voltsVB < 13.00)) {
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
            }
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
            $result = 1; /* Pass */
        } else { 
            $result = 0;  /* Failure */
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
 
        $this->out("**********************************************");
        $this->out("* B E G I N N I N G   U U T   T E S T I N G  *");
        $this->out("**********************************************");
        $this->out("");

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
                    $testResult = $this->_testUUTport2();
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
        $this->out("Testing UUT Supply Voltages");
        $this->out("******************************");

        $voltsVbus = $this->_readUUTBusVolts();

        if (($voltsVbus > 11.5) and ($voltsVbus < 13.0)) {
            $voltsVcc = $this->_readUUTVccVolts();

            if (($voltsVcc > 3.1) and ($voltsVcc < 3.4)) {
                $this->out("UUT Supply Voltages - PASSED!");
                $testResult = self::PASS;
            } else {
                $this->out("UUT Supply Voltages - FAILED!");
                $this->_TEST_FAIL[] = "UUT Vcc Volts Fail:".$voltsVcc."V";
                $testResult = self::HFAIL;
            }
        } else {
            $testResult = self::HFAIL;
            $this->out("UUT Bus Voltage - FAILED!");
            $this->_TEST_FAIL[] = "UUT Bus Volts Fail:".$voltsVbus."V";
        }
        
        $this->out("");

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

        $this->out("3.3V Switched measures ".$sw3V." volts!");

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
        $this->out("Testing Thermistors");
        $this->out("******************************");
        sleep(2);

        $busTemp = $this->_readUUTBusTemp();
        $this->_TEST_DATA["BusTemp"] = $busTemp;

        if (($busTemp > 11.00) and ($busTemp < 26.00)) {
            $resultT1 = self::PASS;
        } else {
            $resultT1 = self::FAIL;
            $this->_TEST_FAIL[] = "Bus Temp:".$busTemp."C";
        }

        $p1Temp = $this->_readUUTP1Temp();
        $this->_TEST_DATA["P1Temp"] = $p1Temp;

        if (($p1Temp > 11.00) and ($p1Temp < 26.00)) {
            $resultT2 = self::PASS;
        } else {
            $resultT2 = self::FAIL;
            $this->_TEST_FAIL[] = "Port 1 Temp:".$p1Temp."C";
        }
        
        $p2Temp = $this->_readUUTP2Temp(); 
        $this->_TEST_DATA["P2Temp"] = $p2Temp;

        if (($p2Temp > 11.00) and ($p2Temp < 26.00)) {
            $resultT3 = self::PASS;
        } else {
            $resultT3 = self::FAIL;
            $this->_TEST_FAIL[] = "Port 2 Temp:".$p2Temp."C";
        }

        if (($resultT1 == self::PASS) and ($resultT2 == self::PASS)
                            and ($resultT3 == self::PASS)) {
            $testResult = self::PASS;
            $this->out("UUT Thermistors - PASSED!");
        } else {
            $testResult = self::FAIL;
            $this->_FAIL_FLAG = true;
            $this->out("UUT Thermistors - FAILED!");
        }

        $this->out("");
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
    private function _testUUTport1()
    {
        $this->out("Testing UUT Port 1");
        $this->out("******************************");

        $this->_setPort1Load(self::ON);
        $this->_setPort1(self::ON);

        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $this->_TEST_DATA["P1Volts"] = $p1Volts;

        $p1Amps = $this->_readUUTPort1Current();
        $this->out("Port 1 Current = ".$p1Amps." amps");
        $this->_TEST_DATA["P1Current"] = $p1Amps;

        if (($p1Volts > 11.00) and ($p1Volts < 13.00)) {
            $testResult = $this->_runP1FaultTest();
            $this->_setPort1(self::OFF);

            if ($testResult == self::PASS) {
                $voltsP1 = $this->_readTesterP1Volt();
                $p1Volts = $this->_readUUTPort1Volts();
                
                if ($p1Volts <= 0.1) {
                    $testResult = self::PASS;
                    $this->out("Port 1 Load Test - PASSED!");
                } else {
                    $testResult = self::HFAIL;
                    $this->out("Port 1 Load Test - FAILED!");
                    $this->_TEST_FAIL[] = "P1 Off Load:".$p1Volts."V";
                }
            } else {
                $this->out("Port 1 Fault Test - FAILED!");
            }
        } else {
            $this->_setPort1(self::OFF);
            $this->out("Port 1 Load Test - FAILED!");
            $this->_TEST_FAIL[] = "P1 On Load:".$p1Volts."V";
            $testResult = self::HFAIL;
        }
        $this->_setPort1Load(self::OFF); /* Disconnect Port 1 Load */
        $this->out("");
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
        $this->out("PORT 1 FAULT ON:");
        $this->_faultSet(1, 1); /* Set fault */
        sleep(1);

        /* Measure Port 1 voltage */
        $voltsP1 = $this->_readTesterP1Volt();
        $this->_TEST_DATA["P1Fault"] = $voltsP1;

        if ($voltsP1 < 0.1) {
            $this->out("PORT 1 FAULT OFF:");
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
            $this->out("PORT 1 FAULT OFF:");
            $this->_faultSet(1, 0);
            sleep(1);
            $this->_TEST_FAIL[] = "P1 Fault On:".$voltsP1."V";
            $testResult = self::HFAIL;
        }
        $testResult = self::PASS;
        return $testResult;
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
    * @return integer $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _testUUTport2()
    {
        $this->out("Testing UUT Port 2 ");
        $this->out("******************************");

        $this->_setPort2Load(self::ON);
        $this->_setPort2(self::ON);

        $voltsP2 = $this->_readTesterP2Volt();

        $p2Volts = $this->_readUUTPort2Volts();
        $this->out("Port 2 UUT     = ".$p2Volts." volts");
        $this->_TEST_DATA["P2Volts"] = $p2Volts;

        $p2Amps = $this->_readUUTPort2Current();
        $this->out("Port 2 Current = ".$p2Amps." amps");
        $this->_TEST_DATA["P2Current"] = $p2Amps;

        if (($p2Volts > 11.50) and ($p2Volts < 13.00)) {
            $testResult = $this->_runP2Faulttest();
            $this->_setPort2(self::OFF);

            if ($testResult == self::PASS) {
                $voltsP2 = $this->_readTesterP2Volt();
                $p2Volts = $this->_readUUTPort2Volts();
                $this->out("Port 2 UUT     = ".$p2Volts." volts");

                if ($p2Volts <= 0.1) {
                    $testResult = self::PASS;
                    $this->out("Port 2 Load Test - PASSED!");
                } else {
                    $testResult = self::HFAIL;
                    $this->out("Port 2 Load Test - FAILED!");
                    $this->_TEST_FAIL[] = "P2 Off Load:".$p2Volts."V";
                }
            } else {
                $this->out("Port 2 Fault Test - FAILED!");
            }
        } else {
            $this->_setPort2(self::OFF);
            $this->out("Port 2 Fault Test - FAILED!");
            $this->_TEST_FAIL[] = "P2 On Load:".$p2Volts."V";
            $testResult = self::HFAIL;
        }
        $this->_setPort2Load(self::OFF); /* Remove 12 ohm load */
	
        $this->out("");
        return $testResult;
    }

    /**
    ***************************************************************
    * Port 2 Fault Test Routine
    *
    * This function runs the fault test on Port 2
    * 
    * @return $int $testResult  1=pass, 0=fail, -1=hard fail
    */
    private function _runP2FaultTest()
    {
        $this->out("PORT 2 FAULT ON:");
        $this->_faultSet(2, 1); /* Set fault */
        sleep(1);
        
        /* Measure Port 1 voltage */
        $voltsP2 = $this->_readTesterP2Volt();
        $this->_TEST_DATA["P2Fault"] = $voltsP2;

        if ($voltsP2 < 0.1) {
            $this->out("PORT 2 FAULT OFF:");
            $this->_faultSet(2, 0); /* Remove fault */
            sleep(1);

            $voltsP2 = $this->_readTesterP2Volt();
            
            if (($voltsP2 > 11.00) and ($voltsP2 < 13.00)) {
                $testResult = self::PASS;
            } else {
                $this->_TEST_FAIL[] = "P2 Fault Off:".$voltsP2."V";
                $testResult = self::HFAIL;
            }
        } else {
            $this->out("PORT 2 FAULT OFF:");
            $this->_faultSet(2, 0);
            sleep(1);
            $this->_TEST_FAIL[] = "P2 Fault On:".$voltsP2."V";
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
        $this->out("Testing UUT VBUS");
        $this->out("******************************");
        sleep(1);

        $testResult = $this->_Port1ToVbusTest();

        if ($testResult == self::PASS) {
            $testResult = $this->_Port2ToVbusTest();
        }

        if ($testResult == self::PASS) {
            $this->out("VBus Load Test - PASSED!");
        } else {
            $this->out("VBus Load Test - FAILED!");
        }
            

        $this->out("");
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
            $voltsVB = $this->_readTesterBusVolt();
            $this->out("Bus Volts Tester = ".$voltsVB." volts");
            $VBvolts = $this->_readUUTBusVolts();
            $this->out("Bus Volts UUT    = ".$VBvolts." volts");

            if ($VBvolts < 0.2) {
                $this->_setPort1(self::ON);

                $voltsVB = $this->_readTesterBusVolt();
                $this->out("Bus Volts Tester = ".$voltsVB." volts");
                $VBvolts = $this->_readUUTBusVolts();
                $this->out("Bus Volts UUT    = ".$VBvolts." volts");
                $p1Volts = $this->_readUUTPort1Volts();
                $p1Amps = $this->_readUUTPort1Current();
                $this->out("Port 1 current   = ".$p1Amps." amps");

                if (($VBvolts > 11.4) and ($VBvolts < 13.00)) {
                    $this->_setPort1(self::OFF);

                    $voltsVB = $this->_readTesterBusVolt();
                    $this->out("Bus Volts Tester = ".$voltsVB." volts");
                    $p1Amps = $this->_readUUTPort1Current();
                    $this->out("Port 1 current   = ".$p1Amps." amps");

                    if ($voltsVB < 0.2) {
                        $testResult = self::PASS;
                    } else { 
                        $this->_TEST_FAIL[] = "P1 off to Vbus:".$voltsVB."V";
                        $testResult = self::HFAIL;
                    }

                } else {
                    $this->_setPort1(self::OFF);
                    $this->_setVBus_V12(self::ON);
                    $this->_TEST_FAIL[] = "P1 on to Vbus:".$VBvolts."V";
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
            $this->out("Port 1  Tester = ".$voltsP1." volts");
            $testResult = self::HFAIL;
        }

        return $testResult;
    }
   
    /**
    *******************************************************************
    * Port 2 to VBus Test Routine
    *
    * This function tests the ability of Port 2 to supply current and
    * voltage to VBus.  It assumes that Vbus is currently connected 
    * to the load resistor through relay K1 and Port 1 is connected to
    * +12V through relay K4.
    *
    * @return int $testResult 1=pass, 0=fail, -1=hard fail
    */
    private function _Port2ToVbusTest()
    {
        $this->_setPort2_V12(self::ON);
        $voltsP2 = $this->_readTesterP2Volt();
        $p2Volts = $this->_readUUTPort2Volts();
        $this->out("Port 2 UUT       = ".$p2Volts." volts");
        
        if (($p2Volts > 11.50) and ($p2Volts < 13.00)) {
            $this->_setPort1_V12(self::OFF);
            
            $voltsP1 = $this->_readTesterP1Volt();
            $this->_setPort2(self::ON);
            
            $voltsVB = $this->_readTesterBusVolt();
            $this->out("Bus Volts Tester = ".$voltsVB." volts");
            $VBvolts = $this->_readUUTBusVolts();
            $this->out("Bus Volts UUT    = ".$VBvolts." volts");
            $p2Volts = $this->_readUUTPort2Volts();
            $p2Amps = $this->_readUUTPort2Current();
            $this->out("Port 2 current   = ".$p2Amps." amps");

            if (($VBvolts > 11.50) and ($VBvolts < 13.00)) {
                $this->_setPort2(self::OFF);

                $voltsVB = $this->_readTesterBusVolt();
                $this->out("Bus Volts Tester = ".$voltsVB." volts");
                $VBvolts = $this->_readUUTBusVolts();
                $this->out("Bus Volts UUT    = ".$VBvolts." volts");
                $p2Amps = $this->_readUUTPort2Current();
                $this->out("Port 2 current   = ".$p2Amps." amps");
                
                if ($VBvolts < 0.2) {
                    $testResult = self::PASS;
                } else {
                    $testResult = self::HFAIL;
                    $this->_TEST_FAIL[] = "P2 off to Vbus:".$VBvolts."V";
                }
                $this->_setVBus_V12(self::ON);

                $voltsVB = $this->_readTesterBusVolt();
                $this->out("Bus Volts Tester = ".$voltsVB." volts");
                $VBvolts = $this->_readUUTBusVolts();
                $this->out("Bus Volts UUT    = ".$VBvolts." volts");
                $this->_setPort2_V12(self::OFF);
                $voltsP2 = $this->_readTesterP2Volt();

            } else {
                $this->_setPort2(self::OFF);
                $this->_setPort2_V12(self::OFF);
                $this->_TEST_FAIL[] = "P2 on to Vbus:".$VBvolts."V";
                $this->_setVBus_V12(self::ON);
                $this->_setPort1_V12(self::OFF);
                $testResult = self::HFAIL;
            }
        } else {
            $this->_setPort2_V12(self::OFF);
            $this->_TEST_FAIL[] = "P2 Supply :".$p2Volts."V";
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
        $this->out("Testing UUT External Thermistor Circuits");
        $this->out("****************************************");
        $this->out("EXT THERM CIRCUITS OPEN:");

        /* read ext therm 1 voltage from UUT */
        $extTemp1 = $this->_readUUTExtTemp1();
        $Tv1 = number_format($extTemp1, 2);
        $this->out("ExtTemp 1 Voltage = ".$Tv1." volts");

        /* 4. read ext therm 2 value from UUT */
        $extTemp2 = $this->_readUUTExtTemp2();
        $Tv2 = number_format($extTemp2, 2);
        $this->out("ExtTemp 2 Voltage = ".$Tv2." volts");

        if (($Tv1 > 3.0) and ($Tv1 < 3.4)) {

            if (($Tv2 > 3.0) and ($Tv2 < 3.4)) {
                $result1 = self::PASS;
            } else {
                $result1 = self::HFAIL;
                $this->_TEST_FAIL[] = "Ext Therm2 High:".$Tv2."V";
            }

        } else {
            $result1 = self::HFAIL;
            $this->_TEST_FAIL[] = "Ext Therm1 High:".$Tv1."V";
        }

        /* close ext therm circuits for low test */
        $this->_setRelay(7, 1);
        $this->_setRelay(8, 1);

        $this->out("EXT THERM CIRCUITS CLOSED:");
        sleep(1);

        $extTemp1 = $this->_readUUTExtTemp1();
        $Tv1 = number_format($extTemp1, 2);
        $this->out("ExtTemp 1 Voltage = ".$Tv1." volts");

        $extTemp2 = $this->_readUUTExtTemp2();
        $Tv2 = number_format($extTemp2, 2);
        $this->out("ExtTemp 2 Voltage = ".$Tv2." volts");

        if ($Tv1 < 0.2) {
            if ($Tv2 < 0.2) {
                $result2 = self::PASS;
            } else {
                $result2 = self::HFAIL;
                $this->_TEST_FAIL[] = "Ext Therm2 Low:".$Tv2."V";
            }
        } else {
            $result2 = self::HFAIL;
            $this->_TEST_FAIL[] = "Ext Therm1 Low:".$Tv1."V";
        }

        /* open ext therm relays */
        $this->_setRelay(7, 0);
        $this->_setRelay(8, 0);

        if (($result1 == self::PASS) and ($result2 == self::PASS)) {
            $this->out("External Thermistor Test - PASSED!");
            $testResult = self::PASS;
        } else {
            $this->out("External Thermistor Test - FAILED!");
            $testResult = self::FAIL;
        }

        $this->out("");
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
        $this->out(" Testing LEDs");
        $this->out("**************");

        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::SET_LED_COMMAND;
        $dataVal = "01"; /*turn on Green Status LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $choice = readline("\n\rAre all 3 Green Status LEDs on? (Y/N) ");
        if (($choice == "Y") || ($choice == "y")) {
            $result1 = self::PASS; 
        } else {
            $result1 = self::FAIL;
            $this->_TEST_FAIL[] = "Green LED Fail";
        }

        $dataVal = "02"; /* Turn on Red status LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); 

        $choice = readline("\n\rAre all 3 Red Status LEDs on? (Y/N) ");
        if (($choice == "Y") || ($choice == "y")) {
            $result2 = self::PASS; 
        } else {
            $result2 = self::FAIL;
            $this->_TEST_FAIL[] = "Red LED Fail";
        }

        $dataVal = "00";  /* Turn off all LEDs */
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
       
        if (($result1 == self::PASS) and ($result2 == self::PASS)) {
            $this->out("LED Test - PASSED!");
            $testResult = self::PASS;
        } else {
            $this->out("LED Test - FAILED!");
            $this->_FAIL_FLAG = true;
            $testResult = self::FAIL;
        }
        
        $this->out("");
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

        $this->out("**********************************************");
        $this->out("*  L O A D I N G   U U T   P R O G R A M S   *");
        $this->out("**********************************************");
        $this->out("");

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
        
        $this->out("Testing Application Program");
        $this->out("***************************");

        $this->out("Checking UUT Communication");
        //$this->_ENDPT_SN = "8012";
        $decVal = hexdec($this->_ENDPT_SN);
        
        $replyData = $this->_pingEndpoint($decVal);

        if ($replyData == true) {
            $this->out("UUT Board Responding!");
            $testResult = $this->_runPort1AppTest($decVal);
            
            if ($testResult == self::PASS) {
                $testResult = $this->_runPort2AppTest($decVal);
                if ($testResult == self::PASS) {
                    $this->out("Application Test - PASSED!");
                } else {
                    $this->out("Application Test - FAILED!");
                }
                
            } else {
                $this->out("Application Test - FAILED!");
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
        $this->out("Read Port 1 Control Channel");
        $idNum = $SNVal;
        $cmdNum = self::READCONTROLCHAN_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Port 1 Control Channel Reply = ".$ReplyData);

        $this->_setPort1Load(self::ON);
        $voltsP1 = $this->_readTesterP1Volt();

        $this->out("Turning on Port 1");
        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        $dataVal = "0000000000";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Set Control Channel Reply = ".$ReplyData);
        sleep(1);
    
        $voltsP1 = $this->_readTesterP1Volt();

        if (($voltsP1 > 11.00) and ($voltsP1 < 13.00)) {
            $this->out("Turning off Port 1");

            $cmdNum = self::SETCONTROLCHAN_COMMAND;
            $dataVal = "00204E0000";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $this->out("Set Control Channel Reply = ".$ReplyData);
            sleep(1);

            $voltsP1 = $this->_readTesterP1Volt();
            $this->_setPort1Load(self::OFF); /* Remove 12 ohm load */

            if ($voltsP1 < 0.2) {
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "App Code P1 off:".$p1v."V";
            }
        } else {
            $cmdNum = self::SETCONTROLCHAN_COMMAND;
            $dataVal = "00204E0000";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $this->out("Set Control Channel Off Reply = ".$ReplyData);
            sleep(2);
            $this->_setPort1Load(self::OFF);
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "App Code P1 on:".$p1v."V";
        }

        return $testResult;
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
    private function _runPort2AppTest($SNval)
    {
        $idNum = $SNval;
        $this->_setPort2Load(self::ON);
        
        $voltsP2 = $this->_readTesterP2Volt();

        $this->out("Turning on Port 2");
        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        $dataVal = "0100000000";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Set Control Channel Reply = ".$ReplyData);
        sleep(1);
    
        $voltsP2 = $this->_readTesterP2Volt();
        
        if (($voltsP2 > 11.00) and ($voltsP2 < 13.00)) {
            $this->out("Turning off Port 2");

            $cmdNum = self::SETCONTROLCHAN_COMMAND;
            $dataVal = "01204E0000";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $this->out("Set Control Channel Reply = ".$ReplyData);
            sleep(1);

            $voltsP2 = $this->_readTesterP2Volt();
            $this->_setPort2Load(self::OFF); /* Remove 12 ohm load */

            if ($voltsP2 < 0.2) {
                $testResult = self::PASS;
            } else {
                $testResult = self::HFAIL;
                $this->_TEST_FAIL[] = "App Code P2 off:".$p2v."V";
            }
        } else {
            $this->out("Application Test - FAILED!");
            $cmdNum = self::SETCONTROLCHAN_COMMAND;
            $dataVal = "01204E0000";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $this->out("Set Control Channel Off Reply = ".$ReplyData);
            sleep(1);
            $this->_setPort2Load(self::OFF);
            $testResult = self::HFAIL;
            $this->_TEST_FAIL[] = "App Code P2 on:".$p2V."V";
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
    private function _readTesterVCC()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VCC_PORT);
      
        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 6.6;
        $vccv = number_format($volts, 2);
	
        $this->out("Tester Vcc Volts = ".$VccVolts." volts");
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
    private function _readTesterBusVolt()
    {
    
        $rawVal = $this->_readTesterADCinput(self::TSTR_VBUS_PORT);
      
        if ($rawVal > 0x7fff) {
            $rawVal = 01;
        }

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 21;
        $voltsVB = number_format($volts, 2);

        $this->out("Tester Bus Volts = ".$voltsVB." volts");
        return $voltsVB;
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
        $voltsP2 = number_format($volts, 2);
	
        $this->out("Port 2 Tester    = ".$voltsP2." volts");
        return $voltsP2;
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
        $voltsP1 = number_format($volts, 2);
	
        $this->out("Port 1 Tester    = ".$voltsP1." volts");
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
    * Read UUT Port 2 Voltage
    *
    * This function gets the averaged adc reading for the Port 2
    * adc channel input.  It sends a command to the UUT to return
    * the DataChan value and then converts the adc steps into 
    * voltage values.
    *
    * @return $volts  Port 2 voltage value.
    */
    private function _readUUTPort2Volts()
    {
        
        $rawVal = $this->_readUUT_ADCval(self::UUT_P2_VOLT);
       
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
        return $p2Volts;
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
        $rawVal = $this->_readUUT_ADCval(self::UUT_BUS_TEMP);

        $bTemp = $this->_convertTempData($rawVal);
        $busTemp = number_format($bTemp, 2);
        
        $this->out("Bus Temp    : ".$busTemp." C");
        return $busTemp;

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_P2_TEMP);

        $p2Temp = $this->_convertTempData($rawVal);
        $port2Temp = number_format($p2Temp, 2);
        
        $this->out("Port 2 Temp : ".$port2Temp." C");
        return $port2Temp;

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
    private function _readUUTPort1Current()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_CURRENT);
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / 0.0532258;
        $p1Amps = number_format($current, 2);

        return $p1Amps;
    }


     /**
    ************************************************************
    * Read UUT Port 2 Current Routine
    * 
    * This function reads the Port 2 Current flow measured 
    * by the Unit Under Test (UUT).  Index 4
    *
    * @return $volts 
    */
    private function _readUUTPort2Current()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P2_CURRENT);
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / 0.0532258;
        $p2Amps = number_format($current, 2);

        return $p2Amps;
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
        
        $this->out("UUT Bus Volts    = ".$voltsVbus." volts");
        return $voltsVB;

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_EXT_TEMP2);
        if ($rawVal > 0x7ff) {
            $rawVal = 0xffff - $rawVal;
        }

        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        
        return $volts;

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_EXT_TEMP1);
        if ($rawVal > 0x7ff) {
            $rawVal = 0xffff - $rawVal;
        }

        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        
        return $volts;

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_TEMP);

        $p1Temp = $this->_convertTempData($rawVal);
        $port1Temp = number_format($p1Temp, 2);
        
        $this->out("Port 1 Temp : ".$port1Temp." C");
        return $port1Temp;

    }

    /**
    **************************************************************
    * Read UUT Port 1 Voltage
    *
    * This function gets the averaged adc reading for the Port 1
    * adc channel input.  It sends a command to the UUT to return
    * the DataChan value and then converts the adc steps into 
    * voltage values.
    *
    * @return $volts  Port 1 voltage value.
    */
    private function _readUUTPort1Volts()
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
        $this->out("Port 1 UUT       = ".$p1Volts." volts");

        return $p1Volts;
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
        $rawVal = $this->_readUUT_ADCval("a");

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 10;
        
        $VccVolts = number_format($volts, 2);
        $this->out("UUT Vcc Volts    = ".$VccVolts." volts");
        return $VccVolts;

    }

    /**
    ************************************************************
    * Read UUT DAC Voltage Routine
    * 
    * This function reads the DAC output voltage internally 
    * measured by the Unit Under Test (UUT). Index B
    *
    * @return $volts 
    */
    private function _readUUTdacVolts()
    {
        $rawVal = $this->_readUUT_ADCval("b");

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

        $this->out("TEST DATA: ".$testData);
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

        $this->out("TEST FAIL: ".$testFail);
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
    private function _setPort1Load($state)
    {
        switch ($state) {
            case 0: 
                /* open relay K4 to remove 12 ohm load from port 1 */
                $this->_setRelay(4,0);
                break;
            case 1:
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
    private function _setPort1($state)
    {
        switch ($state) {
            case 0:
                $this->_setPort(1,0); /* Port 1 off */
                $this->out("PORT 1 OFF:");
                break;
            case 1:
                $this->_setPort(1, 1); /* Port 1 On */
                $this->out("PORT 1 ON:");
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
    private function _setPort1_V12($state)
    {
        switch ($state) {
            case 0: 
                $this->_setRelay(4, 0);  /* open K4 to remove +12V */
                $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                $this->out("Port 1 +12V OFF:");
                break;
            case 1:
                $this->_setRelay(3, 1);  /* close K3 to select +12V  */
                $this->_setRelay(4, 1);  /* close K4 to connect +12V */
                $this->out("PORT 1 +12V CONNECTED:");
                break;
        }
        sleep(1);

    }

    /**
    ************************************************************
    * Set Port 2 Load Routine
    *
    * This function connects the 12 ohm load resistor to
    * port 2 and delays for 1 second before returning. It 
    * assumes that relay K5 is open so that the load is 
    * selected.
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    private function _setPort2Load($state)
    {
        switch ($state) {
            case 0: 
                /* open relay K6 to disconnect 12 ohm load from Port 2 */
                $this->_setRelay(6,0);
                break;
            case 1:
                /* close relay K6 to connect 12 ohm load to Port 2 */
                $this->_setRelay(6, 1);
                break;
        }
        sleep(1);

    }

    /**
    ***********************************************************
    * Set Port 2 State Routine
    *
    * This function turns Port 2 FET's on or off for the load
    * or supply tests and delays 1 second.
    *
    * @param int $state  1=on, 0= off
    * @return void
    */
    private function _setPort2($state)
    {
        switch ($state) {
            case 0:
                $this->_setPort(2,0); /* Port 1 off */
                $this->out("PORT 2 OFF:");
                break;
            case 1:
                $this->_setPort(2, 1); /* Port 1 On */
                $this->out("PORT 2 ON:");
                break;
        }

        sleep(1);
    }
    
    /**
    ************************************************************
    * Set Port 2 +12V Routine
    *
    * This function connects the +12V supply to port 2
    * and delays for 1 second before returning.  
    *
    * @param int $state  1=On, 0=off
    * @return void
    */
    private function _setPort2_V12($state)
    {
        switch ($state) {
            case 0: 
                $this->_setRelay(6, 0);  /* open K6 to remove +12V from Port 2 */
                $this->_setRelay(5, 0);  /* open K5 to select 12 Ohm load */
                $this->out("Port 2 +12V OFF:");
                break;
            case 1:
                $this->_setRelay(5, 1);  /* close K5 to select +12V */
                $this->_setRelay(6, 1);  /* close K6 to connect +12V to Port 2 */
                $this->out("PORT 2 +12V CONNECTED:");
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
    private function _setVBus_V12($state)
    {
        switch ($state) {
            case 0:
                /* open K1 to remove +12V and connect Load */
                $this->_setRelay(1, 0);  
                $this->out("VBUS 12 OHM LOAD CONNECTED:");
                break;
            case 1:
                /* close K1 to select +12V */
                $this->_setRelay(1, 1);  
                $this->out("Bus Voltage ON:");
                break;
        }
        sleep(1);
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
    private function _setRelay($relay, $state)
    {
        $idNum = self::EVAL_BOARD_ID;
        
        if ($state == 1) {
	    $cmdNum = self::SET_DIGITAL_COMMAND;
	} else {
	    $cmdNum = self::CLR_DIGITAL_COMMAND;
	}
	
	switch ($relay) {
	  case 1:
	    $dataVal = "0300";  /* VBUS */
	    break;
	  case 2:
	    $dataVal = "0301";  /* +12V or 10V */
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
    * @param $portNum  Power port 1 or 2
    * @param $state    0= clear, 1=set 
    *
    * @return integer $testResult
    */
    private function _faultSet($portNum, $state)
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
                $dataVal = "0000";
            }
        } else if ($portNum == 2) {
            if ($state == 0) {
                $dataVal = "0200";
            } else if ($state == 1) {
                $dataVal = "0201";
            } else {
                $dataVal = "0000";
            }
        } else {
            $dataVal = "0000";
        }

        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "00") {
            $result = false;
        } else {
            $result = true;
        }

        return $result;
    }



    /**
    ************************************************************
    * Clear Tests Routine
    *
    * This function clears the testers digital outputs in the 
    * event of a test failure.
    *
    */
    private function _clearTester()
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
            $this->_system->out("Eval Board Failed to Respond!");
            $testResult = self::HFAIL;
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
        $this->out("Testing UUT Communications");
        $this->out("******************************");
        $Result = $this->_pingEndpoint(self::UUT_BOARD_ID);
        if ($Result == true) {
            $this->_system->out("UUT Board Responding!\n\r");
            $testResult = self::PASS;
        } else {
            $this->_system->out("UUT Board Failed to Respond!\n\r");
            $testResult = self::HFAIL;
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
    ************************************************************
    * Test User Signature routines
    *
    * This function will send packet commands to the UUT test
    * firmware to read the user signature bytes and write them.
    *
    */
    private function _readUserSig()
    {
        $this->out("Powering up UUT");
        $result = $this->_testUUTpower();
        sleep(1);
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->out("Sending Read User Signature Command!");
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::READ_USERSIG_COMMAND;
        $dataVal = "00";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $this->out("Reply Data = ".$ReplyData);
 
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->out("Powering down UUT");
        $this->_powerUUT(self::OFF);
        $this->_clearTester();
        $choice = readline("\n\rHit Enter to Continue: ");
    }

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
        $this->out("Writing User Signature Bytes!");

        $Avrdude = "sudo avrdude -px32e5 -c avrisp2 -P usb -B 10 -i 100 ";
        $usig  = "-U usersig:w:104603test.usersig:r ";

        $Prog = $Avrdude.$usig;
        exec($Prog, $output, $return); 

        if ($return == 0) {
            $this->out("Writing User Signature Bytes - PASSED");
            $result = self::PASS;
        } else {
            $this->out("Writing User Signature Bytes - FAILED");
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

        $this->out("Sending Erase User Signature Command!");
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::ERASE_USERSIG_COMMAND;
        $dataVal = "00";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        if ($ReplyData == "80") {
            $this->out("Erase User Signature - PASSED");
            $result = self::PASS;
        } else {
            $this->out("Erase User Signature - FAILED");
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
     
        $this->out("******************************");
        $this->out("* Creating UserSig Data File *");
        $this->out("******************************");

        $SNdata = $this->_ENDPT_SN;
        $HWPNdata = self::HWPN;
        $CALdata = $this->_ADC_OFFSET;
        $CALdata .= $this->_ADC_GAIN;
        $DACcal = $this->_DAC_OFFSET;
        $DACcal .= $this->_DAC_GAIN;

        $this->out("SNdata   = ".$SNdata);
        $this->out("HWPNdata = ".$HWPNdata);
        $this->out("CALdata  = ".$CALdata);
        $this->out("DACcal   = ".$DACcal);
        
        $Sdata = $SNdata.$HWPNdata.$CALdata.$DACcal;

        $SIGdata = pack("H*",$Sdata);
        $fp = fopen("104603test.usersig","wb");
        if ($fp != NULL) {
            fwrite($fp, $SIGdata);
            $this->out("User Signature Bytes File Written!");
            $result = self::PASS;
        } else {
            $this->out("Failed to write Signature bytes file!");
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
    private function _loadTestFirmware()
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
            $result = true;
        } else {
            $result = false;
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
    private function _loadBootloaderFirmware()
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
        $flash = "-U flash:w:104603boot.ihex ";
        $eeprm = "-U eeprom:w:104603boot.eep ";
        $fuse1 = "-U fuse1:w:".$FUSE1.":m ";
        $fuse2 = "-U fuse2:w:".$FUSE2.":m ";
        $fuse4 = "-U fuse4:w:".$FUSE4.":m ";
        $fuse5 = "-U fuse5:w:".$FUSE5.":m ";

        $Prog = $Avrdude.$flash.$eeprm.$fuse1.$fuse2.$fuse4.$fuse5;
        exec($Prog, $output, $return); 

        if ($return == 0) {
            $this->out("Loading Bootloader - SUCCESSFUL");
            $result = self::PASS;
        } else {
            $this->out("Loading Bootloader - FAILED");
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Failed to load Bootloader";
        }

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
    private function _setPowerTable()
    {
        $this->out("");
        $this->out("Setting Power Table");
        $this->out("*******************");
        
        $decVal = hexdec($this->_ENDPT_SN);
        $idNum = $decVal;
        $cmdNum = self::SET_POWERTABLE_COMMAND;
        $portData = "00";
        $driverData ="FE0000";  /* Driver, Subdriver and priority */
        $driverCapacity = "10270000";
        $driverName = "4C6F616420310000000000000000000000000000";
        $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
        $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
        $dataVal = $portData.$driverData.$driverCapacity.$driverName.
                    $fillData.$fillData2;
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $ReplyData = substr($ReplyData, 0, 14);
        $this->out("Port 1 Reply = ".$ReplyData);
        
        $testReply = substr($ReplyData, 0, 4);
        if ($testReply == "FE00") {
        
            $portData = "01";
            $dataVal = $portData.$driverData.$driverCapacity.$driverName.
                        $fillData.$fillData2;
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $ReplyData = substr($ReplyData, 0, 14);
            $this->out("Port 2 Reply = ".$ReplyData);
            
            $testReply = substr($ReplyData, 0, 4);
            if ($testReply == "FE00") {
                $this->out("Setting Power Table - PASSED!");
                $testResult = self::PASS;
            } else {
                $this->out("Setting Power Table - FAILED!");
                $testResult = self::FAIL;
                $this->_TEST_FAIL[] = "Fail Setting P2 Power Table";
            }
        } else {
            $this->out("Setting Power Table - FAILED!");
            $testResult = self::FAIL;
            $this->_TEST_FAIL[] = "Fail Setting P1 Power Table";
        }
        
        $this->out("");
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
        $this->out("Reading Battery Socializer Configuration");
        
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
        $this->out("Loading Application Firmware");

        $hugnetLoad = "../bin/./hugnet_load";
        $firmwarepath = "~/code/HOS/packages/104603-00393801C-0.3.0.gz";

        $Prog = $hugnetLoad." -i ".$this->_ENDPT_SN." -D ".$firmwarepath;

        system($Prog, $return);

        if ($return == 0) {
            $result = self::PASS;
        } else {
            $result = self::FAIL;
            $this->_TEST_FAIL[] = "Fail to load Application";
        } 


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
    private function _getSerialNumber()
    {
        do {
            $this->out("***********************************");
            $this->out("*     OBTAINING SERIAL NUMBER     *");
            $this->out("***********************************");
            $this->out("");
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

        $this->out("SN response is ".$SNresponse);
        $this->out("");

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
    private function _readMicroSN()
    {
        $idNum = self::UUT_BOARD_ID;
        $cmdNum = self::READ_PRODSIG_COMMAND;
        $dataVal = "00";
    
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        //$this->out("Serial number in reply data: ".$ReplyData);
        
        /* convert data to big endian */
        $len = strlen($ReplyData);
        $loops = $len/2;
        
        for ($i = 0; $i < $loops; $i++) {
            $start = $len - (2 * ($i+1));
            $newData .= substr($ReplyData, $start, 2);
        }
        
        $this->out("Serial Number is : ".$newData);
        
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
    private function _runUUTadcCalibration()
    {
        $this->out("****************************************");
        $this->out("*   Entering ADC Calibration Routine   *");
        $this->out("****************************************");

        /*****************************************
        * Set up port 1 for measuring offset error. */
        $this->_setPort1Load(self::ON);
        $this->_setPort1(self::ON);

        /* 3.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTPort1Volts();
        $this->out("Port 1 UUT     = ".$p1Volts." volts!");

        /* 4.  Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTPort1Current();
        $this->out("Port 1 current = ".$p1Amps." A");
        
        if (($p1Volts > 11.00) and ($p1Volts < 13.00)) {
        
            sleep(1);

            $this->_setPort1(self::OFF);
            $p1Volts = $this->_readUUTPort1Volts();
        
            if ($p1Volts <= 0.2) {
                $offsetHexVal = $this->_runAdcOffsetCalibration();
                $this->_ADC_OFFSET = $offsetHexVal;
                $this->_TEST_DATA["ADCoffset"] = $offsetHexVal;

                $this->_setPort1Load(self::OFF);
                $this->out("Setting Port 1 to 10V Reference");
                $this->_setRelay(2,1);  /* Select 10V reference */
                usleep(1000);
                $this->_setRelay(3,1);  /* Select Voltage supply */
                usleep(1000);
                $this->_setRelay(4,1);  /* Connect 10V reference */
                sleep(1);

                $voltsP1 = $this->_readTesterP1Volt();
                $voltsUp1 = $this->_readUUTPort1Volts();
            
                $gainErrorValue = $this->_runAdcGainCorr($offsetIntVal);
                $this->_TEST_DATA["ADCgain"] = $gainErrorValue;
                sleep(1);
                
                $this->out("");
                $this->out("** Setting ADC Gain Correction **");
                $retVal = $this->_setAdcGainCorr($gainErrorValue);
                $this->_ADC_GAIN = $retVal;
                
                /* measure port 1 voltage with UUT */
                $voltsUp1 = $this->_readUUTPort1Volts();
                
                $this->_setRelay(4,0); /* Disconnect Port 1 */
                $this->_setRelay(3,0); /* Select load */
                $this->_setRelay(2,0); /* Select 12V   */
                sleep(1);

                $this->out("********************************");
                $this->out("*  ADC CALIBRATION COMPLETE!   *");
                $this->out("********************************\n\r");
                
                $testResult = self::PASS;
            } else {
                $this->out("Port 1 fail, unable to calibrate ADC!");
                $this->_setPort1Load(self::OFF);
                $this->_TEST_FAIL[] = "P1 Off in ADC Cal:".$p1Volts."V";
                $testResult = self::HFAIL;
            }
        } else {
        
            $this->out("Port 1 fail unable to calibrate ADC!");
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
        $this->out("Hex Value for the offset ".$hexVal);
      
        $this->out("*** Setting ADC Offset ****");
        $retVal = $this->_setAdcOffset($hexVal);

        $this->out("");
    
    
    
        return $retVal;
    
    }
    
    /**
    ************************************************************
    * Run ADC Gain Error Correction Routine
    * 
    * This function performs the gain error correction by 
    * measuring port 1 voltage which is set at the reference
    * voltage of 10.0V.  It then uses the following formula
    * to calculate the gain error correction value:
    *
    *                         expected value
    *  GAIN CORR = 2048 X -----------------------
    *                      (MeasuredValue - OffsetCor)
    *
    */
    private function _runAdcGainCorr($offsetIntValue)
    {

        $rawAvg = $this->_readUUT_ADCval(self::UUT_P1_VOLT);;

        
        $this->out("\n\r");
        $this->out("**************************************");
        $this->out("*  Calculating Gain Error Value      *");
        $this->out("**************************************");
        
        $gainIntVal = number_format($rawAvg, 0, "", "");
        $this->out("Integer Value = ".$gainIntVal);
        
        $tempVal = $gainIntVal - $offsetIntValue;
        //$this->out("Gain Int - Offset Int = ".$tempVal);
        
        $gainRatio = 975/ $tempVal;
        //$this->out("Gain Ratio = ".$gainRatio);
        
        $gainVal = 2048 * $gainRatio;
    
        $this->out("Gain Value    = ".$gainVal);
    
        $gainIntValue = number_format($gainVal, 0, "", "");
    
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
        
        $this->out("Offset Value   = ".$ReplyData);
        
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
        
        $this->out("Gain Set Value = ".$ReplyData);
        
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
        $this->out("************************************");
        $this->out("* Entering DAC Calibration Routine *");
        $this->out("************************************");

        $this->out("");
        $this->out("Starting DAC offset calibration");
        $this->out("Setting DAC output to ".self::DAC_OFFCAL_LEVEL." volts");

 
        $result = $this->_runDacOffsetCalibration();
        
        if ($result) {
            $this->out("Setting DAC offset to :".$this->_DAC_OFFSET);
            $this->_setDacOffset($this->_DAC_OFFSET);
            $this->_TEST_DATA["DACoffset"] = $this->_DAC_OFFSET;
            sleep(1);

            $dacVal = "0400";
            $this->_setDAC($dacVal);

            $dacVolts = $this->_readUUTdacVolts();
            $this->out("Adjusted DAC volts : ".$dacVolts);
            $this->out("");

        } else {
            $this->out("Offset Calibration Failed!");
        }
        
        if ($result) {
            $this->out("******************************");
            $this->out("Starting DAC gain calibration");
            $this->out("Setting DAC output to ".self::DAC_GAINCAL_LEVEL." volts");
            
            $result = $this->_runDacGainCalibration();
            
            if ($result) {
                $this->out("Setting DAC Gain to :".$this->_DAC_GAIN);
                $this->_setDacGain($this->_DAC_GAIN);
                $this->_TEST_DATA["DACgain"] = $this->_DAC_GAIN;
                sleep(1);

                $dacVal = "07C2";
                $this->_setDAC($dacVal);

                $dacVolts = $this->_readUUTdacVolts();
                $this->out("Adjusted DAC volts : ".$dacVolts);
                $this->out("");

            } else {
                $this->out("Offset Calibration Failed!");
            }
        }

        $this->out("********************************");
        $this->out("*  DAC CALIBRATION COMPLETE!   *");
        $this->out("********************************\n\r");

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

        $dacOffset = 0;

        $dacVal = self::DAC_OFFCAL_START;
        $dataVal = dechex($dacVal);
        $dataVal = "0".$dataVal;
        $replyData= $this->_setDAC($dataVal);

        $dacVolts = $this->_readUUTdacVolts();
        $this->out("DAC volts = ".$dacVolts);
        
        $this->out("Obtaining DAC offset ");
        
        if ($dacVolts < self::DAC_OFFCAL_LEVEL) {
            $error = false;
            /* lets add some code to reduce the number of steps needed */
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
                    //$this->out("DAC volts = ".$dacVolts);
                }
                print "*";

            } while (($dacVolts < self::DAC_OFFCAL_LEVEL) and (!$error));

            if (!$error) {
                /*******************************************************************
                * Explaination of offset calculation:   
                * ($dacVal - self::DAC_OFFCAL_START) represents the number of steps 
                * above the start value necessary to get the DAC output to the 
                * desired voltage level.  However, it appears that the MSB of the 
                * the offset byte is a sign bit with a 1 being positive and a zero
                * being negative.  So, in order to get the offset steps added to 
                * the DAC output, it is necessary to set the MSB or add 128 to the 
                * the offset value.
                */
                $offset = $dacVal - self::DAC_OFFCAL_START + 128;
                
                $hexOffset = dechex($offset);

                /* make sure we have one hex byte */
                $len = strlen($hexOffset);
                if ($len < 2) {
                    $hexOffset = "0".$hexOffset;
                } else if ($len > 2) {
                    $hexOffset = substr($hexOffset, $len-2, 2);
                }
                
                $this->_DAC_OFFSET = $hexOffset;
                $this->out("");
                $this->out("Offset Value = ".$hexOffset);
                $result = self::PASS;
            } else {
                $this->_TEST_FAIL[] = "Com Fail Setting DAC";
                $result = self::FAIL;
            }

        } else {
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
                $len = strlen($hexOffset);
                if ($len < 2) {
                    $hexOffset = "0".$hexOffset;
                } else if ($len > 2) {
                    $hexOffset = substr($hexOffset, $len-2, 2);
                }

                $this->_DAC_OFFSET = $hexOffset;
                $this->out("");
                $this->out("Offset Value : ".$hexOffset);
                $result = self::PASS;
            } else {
                $result = self::FAIL;
                $this->_TEST_FAIL[] = "Com Fail Setting DAC";
            }
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
        $result = self::PASS;
        
        $dacVal = self::DAC_GAINCAL_START;
        $dataVal = dechex($dacVal);
        $dataVal = "0".$dataVal;
        $replyData= $this->_setDAC($dataVal);

        $dacVolts = $this->_readUUTdacVolts();
        $this->out("DAC volts = ".$dacVolts);
        
        $this->out("Obtaining DAC gain value ");
        if ($dacVolts < self::DAC_GAINCAL_LEVEL) {
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
                    $len = strlen($hexGainCor);
                    if ($len < 2) {
                        $hexGainCor = "0".$hexGainCor;
                    } else if ($len > 2) {
                        $hexGainCor = substr($hexGainCor, $len-2, 2);
                    }
                    $replyData = $this->_setDacGain($hexGainCor);

                    if(is_null($replyData)) {
                        $error = true;
                    } else {
                        $dacVolts = $this->_readUUTdacVolts();
                        //$this->out("DAC volts = ".$dacVolts);
                    }
                    print "*";
                } while (($dacVolts < self::DAC_GAINCAL_LEVEL) and (!$error));
           
            
                if (!$error) {
                    $this->_DAC_GAIN = $hexGainCor;
                    $this->out("");
                    $this->out("Gain Error Value = ".$hexGainCor);
                    $result = self::PASS;
                } else {
                    $result = self::FAIL;
                    $this->_TEST_FAIL[] = "Com Error Setting DAC Gain";
                } 
       } else {
            
            $diffVolts = self::DAC_GAINCAL_LEVEL - $dacVolts;
            $steps = 3.3/ pow(2,12);
            $numSteps = round($diffVolts/$steps);
            
            $hexGainCor = $this->_negInt_to_twosComplement($numSteps);
            
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
                    $numSteps--;
                    $hexGainCor = $this->_negInt_to_twosComplement($numSteps);
                    
                    $len = strlen($hexGainCor);
                    if ($len < 2) {
                        $hexGainCor = "0".$hexGainCor;
                    } else if ($len > 2) {
                        $hexGainCor = substr($hexGainCor, $len-2, 2);
                    }
                    
                    $replyData = $this->_setDacGain($hexGainCor);

                    if(is_null($replyData)) {
                        $error = true;
                    } else {
                        $dacVolts = $this->_readUUTdacVolts();
                        //$this->out("DAC volts = ".$dacVolts);
                    }
                    print "*";
                } while (($dacVolts > self::DAC_GAINCAL_LEVEL) and (!$error));
           
            
                if (!$error) {
                    $this->_DAC_GAIN = $hexGainCor;
                    $this->out("");
                    $this->out("Gain Error Value = ".$hexGainCor);
                    $result = self::PASS;
                } else {
                    $result = self::FAIL;
                    $this->_TEST_FAIL[] = "Com Error Setting DAC Gain";
                } 
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
