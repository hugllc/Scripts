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

    const READ_USERSIG_COMMAND   = 0x36;
    const ERASE_USERSIG_COMMAND  = 0x37;

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

    private $_eptroubleMainMenu = array(
                                0 => "Read User Signature Bytes",
                                1 => "Write User Signature Bytes",
                                2 => "Erase User Signature Bytes",
                                3 => "Load Test Firmware",
                                4 => "Write User Signature File",
                                5 => "Calibrate DAC",
                                6 => "Program UUT",
                                );
                                
    public $display;

    private $_ADC_OFFSET;
    private $_ADC_GAIN;
    private $_DAC_OFFSET;
    private $_DAC_GAIN;
    private $_ENDPT_SN;
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
    *   5: Run calibration routines & set values
    *   6: Run UUT tests
    *   7: Load testboot code to erase user signature page
    *   8: Write user signature bytes with AVRDUDE
    *   9: Load UUT bootloader and config data
    *  10: HUGnet Load UUT application code
    *  11: Power cycle UUT & verify communications
    *  12: Power Down
    *  13: Display passed and log data
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
                $this->_loadTestFirmware();
                $result = $this->_checkUUTBoard();
                if ($result) {
                    //$this->_runUUTadcCalibration();
                    //$this->_runUUTdacCalibration();
                    $this->_ENDPT_SN = $this->_getSerialNumber();
                    $result = $this->_testUUT();
                    if ($result) {
                        $this->_writeUserSigFile();
                        $this->_eraseUserSig();
                        $this->_writeUserSig();
                        $this->_loadBootloaderFirmware();
                        $this->_loadApplicationFirmware();
                        // Load production bootloader code.
                        // HUGnetLoad application code
                        // Test application code operation
                        $this->display->displayPassed();
                    } else {
                        $this->display->displayFailed();
                    }
                    $result = $this->_powerUUT(self::OFF);   
                    $this->_clearTester();
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
            $this->display->displayFailed();
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
    * opening or closing of relay K1.
    * +12V On  = K1 closed 
    * +12V Off = K1 open 
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
        
        if ($ReplyData == "30") {
            $result = true;
        } else { 
            $result = false;
        }


        return $result;
    }

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
    * @return $result  boolean true for success and false for  
    *                           failure.
    */
    private function _runUUTadcCalibration()
    {
        $result = true;
        $this->out("****************************************");
        $this->out("*   Entering ADC Calibration Routine   *");
        $this->out("****************************************");

        /*****************************************
        * Set up port 1 for measuring offset 
        * error.

        /* 1.  connect 12 ohm load  to port 1 */
        $this->_setRelay(4, 1);
        sleep(1);
    
        /* 2.  turn on Port 1 */
        $this->_setPort(1, 1);
        sleep(1);

        /* 3.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTPort1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT     = ".$pv1." volts!");

        /* 4.  Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTPort1Current();
        $p1A = number_format($p1Amps, 2);
        $this->out("Port 1 current = ".$p1A." A");
        sleep(1);

         /* Turn off Port 1 */
        $this->_setPort(1, 0);
        $this->out("PORT 1 OFF:");
        sleep(1);

        $p1Volts = $this->_readUUTPort1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT     = ".$pv1." volts!");
       
        $offsetHexVal = $this->_runAdcOffsetCalibration();
        $this->_ADC_OFFSET = $offsetHexVal;

        //$offsetIntVal = $this->_twosComplement_to_negInt($offsetHexVal);
    

        /* remove 12 Ohm load */
        $this->_setRelay(4, 0);
        sleep(1);
        $this->out("Setting Port 1 to 10V Reference");
        $this->_setRelay(2,1);  /* Select 10V reference */
        $this->_setRelay(3,1);  /* Select Voltage supply */
        $this->_setRelay(4,1);  /* Connect 10V reference */
        sleep(1);

        /* measure port 1 voltage with tester */
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 4);
        $this->out("Port 1 Tester  = ".$p1v." volts");

        /* measure port 1 voltage with UUT */
        $voltsUp1 = $this->_readUUTPort1Volts();
        $up1V = number_format($voltsUp1, 4);
        $this->out("Port 1 UUT     = ".$up1V." volts");
        
    
        $gainErrorValue = $this->_runAdcGainCorr($offsetIntVal);
    
        sleep(1);
        
        $this->out("");
        $this->out("** Setting ADC Gain Correction **");
        $retVal = $this->_setAdcGainCorr($gainErrorValue);
        $this->_ADC_GAIN = $retVal;
        
        /* measure port 1 voltage with UUT */
        $voltsUp1 = $this->_readUUTPort1Volts();
        $up1V = number_format($voltsUp1, 4);
        $this->out("Port 1 UUT     = ".$up1V." volts");
        
        $this->_setRelay(4,0); /* Disconnect Port 1 */
        $this->_setRelay(3,0); /* Select load */
        $this->_setRelay(2,0); /* Select 12V   */
        sleep(1);

        $this->out("********************************");
        $this->out("*  ADC CALIBRATION COMPLETE!   *");
        $this->out("********************************\n\r");


        return $result;
    }


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
        $result = true;

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
 
        $this->out("**********************************************");
        $this->out("* B E G I N N I N G   U U T   T E S T I N G  *");
        $this->out("**********************************************");
        $this->out("");

        $testNum = 0;

        do {
            $testNum += 1;
            switch ($testNum) {
                case 1:
                    $result = $this->_testUUTsupplyVoltages();
                    break;
                case 2:
                    $result = $this->_testUUTThermistors();
                    break;
                case 3: 
                    $result = $this->_testUUTport1();
                    break;
                case 4: 
                    $result = $this->_testUUTport2();
                    break;
                case 5:
                    $result = $this->_testUUTvbus();
                    break;
                case 6:
                    $result = $this->_testUUTexttherms();
                    break;
                case 7:
                    $result = $this->_testUUTleds();
                    break;
            }

        } while ($result and ($testNum < 7));


        return $result;
    }

    /**
    **************************************************************
    * UUT Supply Voltage Test Routine
    *
    * This function sends a command to the UUT to measure its 
    * Vbus and Vcc voltages to see if they are in spec before 
    * starting Port voltage tests.
    *
    */
    private function _testUUTsupplyVoltages()
    {
        $this->out("Testing UUT Supply Voltages");
        $this->out("******************************");

        $voltsVbus = $this->_readUUTBusVolts();
        $vB = number_format($voltsVbus, 2);
        $this->out("UUT Bus Volts    = ".$vB." volts");

        if (($vB > 11.5) and ($vB < 13.0)) {
            $voltsVcc = $this->_readUUTVccVolts();
            $vC = number_format($voltsVcc, 2);
            $this->out("UUT Vcc Volts    = ".$vC." volts");

            if (($vC > 3.1) and ($vC < 3.4)) {
                $this->out("UUT Supply Voltages - PASSED!");
                $result = true;
            } else {
                $this->out("UUT Supply Voltages - FAILED!");
                $result = false;
            }
        } else {
            $result = false;
            $this->out("UUT Supply Voltages - FAILED!");
        }
        
        $this->out("");

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
        $this->out("Bus Temp    : ".$busTemp." C");

        if (($busTemp > 11.00) and ($busTemp < 26.00)) {
            $resultT1 = true;
        } else {
            $resultT1 = false;
        }

        $p1Data = $this->_readUUTP1Temp();
        $p1Temp = $this->_convertTempData($p1Data);
        $p1Temp = number_format($p1Temp, 2);
        $this->out("Port 1 Temp : ".$p1Temp." C");

        if (($p1Temp > 11.00) and ($p1Temp < 24.00)) {
            $resultT2 = true;
        } else {
            $resultT2 = false;
        }
        

        $p2Data = $this->_readUUTP2Temp(); 
        $p2Temp = $this->_convertTempData($p2Data);
        $p2Temp = number_format($p2Temp, 2);
        $this->out("Port 2 Temp : ".$p2Temp." C");

        if (($p2Temp > 11.00) and ($p2Temp < 24.00)) {
            $resultT3 = true;
        } else {
            $resultT3 = false;
        }

        if ($resultT1 and $resultT2 and $resultT3) {
            $result = true;
            $this->out("UUT Thermistors - PASSED!");
        } else {
            $result = false;
            $this->out("UUT Thermistors - FAILED!");
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

        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 1 */
        $this->_setRelay(4, 1);
        sleep(1);
	
        /* 2.  turn on Port 1 */
        $this->_setPort(1, 1);
        $this->out("PORT 1 ON:");
        /* 3.  delay 1 Second */
        sleep(1);

        /* 4.  Eval Board Measure Port 1 Voltage */
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 Tester  = ".$p1v." volts");

        /* 5.  Get UUT Port 1 voltage */
        $p1Volts = $this->_readUUTPort1Volts();
        $pv1 = number_format($p1Volts, 2);
        $this->out("Port 1 UUT     = ".$pv1." volts");

        /* 6.  Get UUT Port 1 Current */
        $p1Amps = $this->_readUUTPort1Current();
        $p1A = number_format($p1Amps, 2);
        $this->out("Port 1 Current = ".$p1A." amps");

        /* 7.  Test voltage & current */
        if (($pv1 > 11.50) and ($pv1 < 13.00)) {
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
            /* $idNum = self::UUT_BOARD_ID;
            $cmdNum = self::SET_POWERPORT_COMMAND; 
            $dataVal = "0100"; 
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal); */

            $this->_setPort(1, 0);
            $this->out("PORT 1 OFF:");
            sleep(1);

            $voltsP1 = $this->_readTesterP1Volt();
            $p1v = number_format($voltsP1, 2);
            $this->out("Port 1 Tester  = ".$p1v." volts");

            $p1Volts = $this->_readUUTPort1Volts();
            $pv1 = number_format($p1Volts, 2);
            $this->out("Port 1 UUT     = ".$pv1." volts");
            
            if ($pv1 <= 0.1) {
                $result = true;
                $this->out("Port 1 Load Test - PASSED!");
            } else {
                $result = false;
                $this->out("Port 1 Load Test - FAILED!");
            }

        } else {
            $this->_setPort(1, 0);
            $this->out("PORT 1 OFF:");
            $result = false;
        }

        /* 16.  Disconnect load resistor */
        $this->_setRelay(4, 0); 

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

        /******** test steps ********/
        /* 1.  connect 12 ohm load  to port 2 */
        $this->_setRelay(6, 1);
        sleep(1);

        /* 2.  turn on Port 2 */
        $this->_setPort(2, 1);
        $this->out("PORT 2 ON:");
        /* 3.  delay 1 Second */
        sleep(2);

        /* 4.  Eval Board Measure Port 2 Voltage */
        $voltsP2 = $this->_readTesterP2Volt();
        $p2v = number_format($voltsP2, 2);
        $this->out("Port 2  Tester = ".$p2v." volts");

        /* 5.  Get UUT Port 2 voltage */
        $p2Volts = $this->_readUUTPort2Volts();
        $pv2 = number_format($p2Volts, 2);
        $this->out("Port 2 UUT     = ".$pv2." volts");

        /* 6.  Get UUT Port 2 Current */
        $p2Amps = $this->_readUUTPort2Current();
        $p2A = number_format($p2Amps, 2);
        $this->out("Port 2 Current = ".$p2A." amps");

        /* 7.  Test voltage & current */
        if (($pv2 > 11.50) and ($pv2 < 13.00)) {

            /* 8.  Set the fault signal */
            /* 9.  delay 100mS */

            /* 10. Eval Board Measure Port 2 voltage */
            //$voltsP2 = $this->_readTesterP2Volt();

            /* 11. Get UUT Port 2 voltage */
            //$p2Volts = $this->_readUUTP2Volts();

            /* 12. Get UUT Port 2 Current */
            //$p2Amps = $this->_readUUTP2Current();

            /* 13. Remove the fault signal */
            /* 14. delay 100mS */

            /* 15. Turn off Port 2 */
            $this->_setPort(2, 0);
            $this->out("PORT 2 OFF:");
            sleep(2);

            $voltsP2 = $this->_readTesterP2Volt();
            $p2v = number_format($voltsP2, 2);
            $this->out("Port 2 Tester  = ".$p2v." volts");

            $p2Volts = $this->_readUUTPort2Volts();
            $pv2 = number_format($p2Volts, 2);
            $this->out("Port 2 UUT     = ".$pv2." volts");

            if ($pv2 <= 0.1) {
                $result = true;
                $this->out("Port 2 Load Test - PASSED!");
            } else {
                $result = false;
                $this->out("Port 2 Load Test - FAILED!");
            }
        } else {
            $this->_setPort(2, 0);
            $this->out("PORT 2 OFF:");
            $result = false;
        }

        
        /* 16.  Disconnect load resistor */
        $this->_setRelay(6, 0);
	
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

        /* 1.  Connect +12V to Port 1  */
        $this->_setRelay(3, 1);  /* close K3 to select +12V */
        $this->_setRelay(4, 1);  /* close K4 to connect +12V to Port 1 */
        $this->out("PORT 1 +12V CONNECTED:");
        sleep(1);
        
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1  Tester  = ".$p1v." volts");

        if (($voltsP1 > 11.50) and ($voltsP1 < 13.00)) { 
            /* 3.  Disconnect +12V from Vbus */
            $this->_setRelay(1, 0);  /* open K1 to remove +12V and connect Load */
            $this->out("VBUS 12 OHM LOAD CONNECTED:");
            sleep(1);

            $voltsVB = $this->_readTesterBusVolt();
            $Bv = number_format($voltsVB, 2);
            $this->out("Bus Volts Tester = ".$Bv." volts");

            $VBvolts = $this->_readUUTBusVolts();
            $vB = number_format($VBvolts, 2);
            $this->out("Bus Volts UUT    = ".$vB." volts");

            if ($vB < 0.2) {
                /* 5.  Turn on Port 1 */
                $this->_setPort(1, 1);
                $this->out("PORT 1 ON:");
                sleep(1);

                /* 7.  Eval measure Vbus voltage */
                $voltsVB = $this->_readTesterBusVolt();
                $Bv = number_format($voltsVB, 2);
                $this->out("Bus Volts Tester = ".$Bv." volts");

                $VBvolts = $this->_readUUTBusVolts();
                $vB = number_format($VBvolts, 2);
                $this->out("Bus Volts UUT    = ".$vB." volts");

                /* 8.  Get UUT Port 1 voltage */
                $p1Volts = $this->_readUUTPort1Volts();
                $pv1 = number_format($p1Volts, 2);
                $this->out("Port 1 UUT       = ".$pv1." volts");

                /* 9.  Get UUT Port 1 current */
                $p1Amps = $this->_readUUTPort1Current();
                $p1A = number_format($p1Amps, 2);
                $this->out("Port 1 current   = ".$p1A." amps");

                /* 10. Test current & voltage values. */
                if (($vB > 11.4) and ($vB < 13.00)) {

                    /* 11. Turn off Port 1 */
                    $this->_setPort(1, 0);
                    $this->out("PORT 1 OFF:");
                    sleep(1);

                    /* 13. Eval measure Vbus Voltage */
                    $voltsVB = $this->_readTesterBusVolt();
                    $Bv = number_format($voltsVB, 2);
                    $this->out("Bus Volts Tester = ".$Bv." volts");

                    $p1Amps = $this->_readUUTPort1Current();
                    $p1A = number_format($p1Amps, 2);
                    $this->out("Port 1 current   = ".$p1A." amps");

                    /* 14. Connect +12V to Port 2 */
                    $this->_setRelay(5, 1);  /* close K5 to select +12V */
                    $this->_setRelay(6, 1);  /* close K6 to connect +12V to Port 2 */
                    $this->out("PORT 2 +12V CONNECTED:");
                    sleep(1);
                    
                    $voltsP2 = $this->_readTesterP2Volt();
                    $p2v = number_format($voltsP2, 2);
                    $this->out("Port 2  Tester   = ".$p2v." volts");

                    /* 8.  Get UUT Port 1 voltage */
                    $p2Volts = $this->_readUUTPort2Volts();
                    $pv2 = number_format($p2Volts, 2);
                    $this->out("Port 2 UUT       = ".$pv2." volts");
                    
                    if (($pv2 > 11.50) and ($pv2 < 13.00)) {
                        /* 15. Disconnect +12V from Port 1 */
                        $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
                        $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                        $this->out("Port 1 +12V OFF:");
                        sleep(1);
                        
                        $voltsP1 = $this->_readTesterP1Volt();
                        $p1v = number_format($voltsP1, 2);
                        $this->out("Port 1  Tester   = ".$p1v." volts");
                        
                        /* 16. Turn on Port 2 */
                        $this->_setPort(2, 1);
                        $this->out("PORT 2 ON:");
                        sleep(1);
                        
                        /* 17. Eval measure Vbus voltage */
                        $voltsVB = $this->_readTesterBusVolt();
                        $Bv = number_format($voltsVB, 2);
                        $this->out("Bus Volts Tester = ".$Bv." volts");

                        $VBvolts = $this->_readUUTBusVolts();
                        $vB = number_format($VBvolts, 2);
                        $this->out("Bus Volts UUT    = ".$vB." volts");

                        /* 18. Get UUT Port 2 voltage */
                        $p2Volts = $this->_readUUTPort2Volts();
                        $pv2 = number_format($p2Volts, 2);
                        $this->out("Port 2 UUT       = ".$pv2." volts");

                        /* 19. Get UUT Port 2 Current */
                        $p2Amps = $this->_readUUTPort2Current();
                        $p2A = number_format($p2Amps, 2);
                        $this->out("Port 2 current   = ".$p2A." amps");

                        /* 20. Test current and voltage values */
                        if (($vB > 11.50) and ($vB < 13.00)) {
                            /* 21. Turn off Port 2 */
                            $this->_setPort(2, 0);
                            $this->out("PORT 2 OFF:");
                            sleep(1);

                            /* 23. Eval measure Vbus voltage */
                            $voltsVB = $this->_readTesterBusVolt();
                            $Bv = number_format($voltsVB, 2);
                            $this->out("Bus Volts Tester = ".$Bv." volts");

                            $VBvolts = $this->_readUUTBusVolts();
                            $vB = number_format($VBvolts, 2);
                            $this->out("Bus Volts UUT    = ".$vB." volts");

                            $p2Amps = $this->_readUUTPort2Current();
                            $p2A = number_format($p2Amps, 2);
                            $this->out("Port 2 current   = ".$p2A." amps");
                            
                            /* 25. Connect +12V to Vbus */
                            $this->_setRelay(1, 1);  /* close K1 to select +12V */
                            $this->out("Bus Voltage ON:");
                            sleep(1);

                            $voltsVB = $this->_readTesterBusVolt();
                            $Bv = number_format($voltsVB, 2);
                            $this->out("Bus Volts Tester = ".$Bv." volts");

                            $VBvolts = $this->_readUUTBusVolts();
                            $vB = number_format($VBvolts, 2);
                            $this->out("Bus Volts UUT    = ".$vB." volts");

                            /* 26. Disconnect +12V from Port 2 */
                            $this->_setRelay(6, 0);  /* open K6 to remove +12V from Port 2 */
                            $this->_setRelay(5, 0);  /* open K5 to select 12 Ohm load */
                            $this->out("Port 2 +12V OFF:");
                            sleep(1);
                            
                            $voltsP2 = $this->_readTesterP2Volt();
                            $p2v = number_format($voltsP2, 2);
                            $this->out("Port 2  Tester   = ".$p2v." volts");

                            $result = true;

                        } else {
                            /* turn off Port 2 */
                            $this->_setPort(2, 0);
                            $this->out("PORT 2 OFF:");
                            sleep(1);

                            /* Disconnect +12V from Port 2 */
                            $this->_setRelay(6, 0);  /* open K6 to remove +12V from Port 2 */
                            $this->_setRelay(5, 0);  /* open K5 to select 12 Ohm load */
                            $this->out("Port 2 +12V OFF:");

                            /* 25. Connect +12V to Vbus */
                            $this->_setRelay(1, 1);  /* close K1 to select +12V */
                            $this->out("Bus Voltage ON:");
                            sleep(1);

                            $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
                            $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                            $this->out("Port 1 +12V OFF:");

                            $result = false;

                        }
                    } else {

                        /* Disconnect +12V from Port 2 */
                        $this->_setRelay(6, 0);  /* open K6 to remove +12V from Port 2 */
                        $this->_setRelay(5, 0);  /* open K5 to select 12 Ohm load */
                        $this->out("Port 2 +12V OFF:");

                        /* 25. Connect +12V to Vbus */
                        $this->_setRelay(1, 1);  /* close K1 to select +12V */
                        $this->out("Bus Voltage ON:");
                        sleep(1);

                        $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
                        $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                        $this->out("Port 1 +12V OFF:");

                        $result = false;
                    }
                } else {

                    /*  Turn off Port 1 */
                    $this->_setPort(1, 0);
                    $this->out("PORT 1 OFF:");
                    sleep(1);

                    $this->_setRelay(1, 1);  /* close K1 to select +12V */
                    $this->out("Bus Voltage ON:");
                    sleep(1);

                    $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
                    $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                    $this->out("Port 1 +12V OFF:");

                    $result = false;

                }
            } else {

                $this->_setRelay(1, 1);  /* close K1 to select +12V */
                $this->out("Bus Voltage ON:");
                sleep(1);

                $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
                $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
                $this->out("Port 1 +12V OFF:");

                $result = false;
            }

        } else {
            /*  Disconnect +12V from Port 1 */
            $this->_setRelay(4, 0);  /* open K4 to remove +12V from Port 1 */
            $this->_setRelay(3, 0);  /* Open K3 to select 12 Ohm Load */
            $this->out("Port 1 +12V OFF:");
            sleep(1);
            
            $voltsP1 = $this->_readTesterP1Volt();
            $p1v = number_format($voltsP1, 2);
            $this->out("Port 1  Tester = ".$p1v." volts");
            
            $result = false;
        }

        if ($result) {
            $this->out("VBus Load Test - PASSED!");
        } else {
            $this->out("VBus Load Test - FAILED!");
        }
            

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

        /*********** test steps ***********/
        /* 1. Close ext therm 1 circuit   */
        $this->_setRelay(7, 1);

        /* 2. Close ext therm 2 circuit */
        $this->_setRelay(8, 1);

        $this->out("EXT THERM CIRCUITS CLOSED:");
        sleep(1);

        /* 3. read ext therm 1 value from UUT */
        $extTemp1 = $this->_readUUTExtTemp1();
        $Tv1 = number_format($extTemp1, 2);
        $this->out("ExtTemp 1 Voltage = ".$Tv1." volts");

        /* 4. read ext therm 2 value from UUT */
        $extTemp2 = $this->_readUUTExtTemp2();
        $Tv2 = number_format($extTemp2, 2);
        $this->out("ExtTemp 2 Voltage = ".$Tv2." volts");

        /* 5. Test thermistor values          */

        /* 6. disconnect resistor from ext therm 1 */
        $this->_setRelay(7, 0);
	
        /* 7. disconnect resistor from ext therm 2 */
        $this->_setRelay(8, 0);
        
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

        $choice = readline("\n\rAre all 6 Status LEDs on? (Y/N) ");

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
        //exec($Prog, $output, $return); 
        
        $choice = readline("\n\rAre all 6 Status LEDs off? (Y/N) ");
        
        $this->out("");

        $result = true;

        return $result;

    }


    /**
    ***********************************************************
    * Test Application code Routine
    *
    * This routine runs a couple of quick tests to verify that
    * the application code is indeed up and running.
    *
    */
    private function _runApplicationTest()
    {
        $result = true;

        /* lets start with a read config */
        $this->_ENDPT_SN = "8012";
        $decVal = hexdec($this->_ENDPT_SN);
        $idNum = $decVal;
        $cmdNum = self::READ_CONFIG_COMMAND;
        $dataVal = "00";


        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $this->out("Read Config Reply = ".$ReplyData);

        $cmdNum = self::READCONTROLCHAN_COMMAND;
        
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Read Control Channel Reply = ".$ReplyData);

       /* 1.  connect 12 ohm load  to port 1 */
        $this->_setRelay(4, 1);
        sleep(1);

        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 No Action  = ".$p1v." volts");
   
        $this->out("Turning on Port 1");
        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        $dataVal = "00A00F0000";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Set Control Channel Reply = ".$ReplyData);
        sleep(1);
        
        /* 4.  Eval Board Measure Port 1 Voltage */
        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 ON  = ".$p1v." volts");

        $this->out("Turning off Port 1");

        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        $dataVal = "0000000000";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Set Control Channel Reply = ".$ReplyData);
        sleep(2);

        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 OFF  = ".$p1v." volts");

        $cmdNum = self::READCONTROLCHAN_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->out("Read Control Channel Reply = ".$ReplyData);

        $choice = readline("Hit Enter to Continue!");

        $voltsP1 = $this->_readTesterP1Volt();
        $p1v = number_format($voltsP1, 2);
        $this->out("Port 1 OFF  = ".$p1v." volts");
        sleep(1);
       
        $this->_setRelay(4, 0);

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_BUS_TEMP);

        
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
        $rawVal = $this->_readUUT_ADCval(self::UUT_P2_TEMP);

        
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
    private function _readUUTPort1Current()
    {
        $rawVal = $this->_readUUT_ADCval(self::UUT_P1_CURRENT);
        $steps = 1.65/ pow(2,11);
        $volts = $steps * $rawVal;

        $volts *= 2;
        $newVal = $volts - 1.65;
        $current = $newVal / 0.0532258;
        return $current;

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
        $rawVal = $this->_readUUT_ADCval(self::UUT_EXT_TEMP2);
        if ($rawVal > 0x7ff) {
            $this->out("Raw Value = ".$rawVal." !");
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

        
        return $rawVal;

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
        $rawVal = $this->_readUUT_ADCval("a");

        $steps = 1.0/ pow(2,11);
        $volts = $steps * $rawVal;
        $volts = $volts * 10;
        
        return $volts;

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
        do{
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_readUserSig();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_writeUserSig();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_eraseUserSig();
            } else if (($selection == "D") || ($selection == "d")){
                $this->_testLoadFirmware();
            } else if (($selection == "E") || ($selection == "e")){
                $this->_writeUserSigFile();
                $selection = "A";
            } else if (($selection == "F") || ($selection == "f")){
                $this->_calibrateDAC();
            } else if (($selection == "G") || ($selection == "g")){
                $this->_programUUT();
            } else {
                $exitTest = true;
                $this->out("Exit Troubleshooting Tool");
            }

        } while ($exitTest == false);

        $choice = readline("\n\rHit Enter to Continue: ");
    }

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

        $Avrdude = "avrdude -px32e5 -c avrisp2 -P usb -B 10 -i 100 ";
        $usig  = "-U usersig:w:104603test.usersig:r ";

        $Prog = $Avrdude.$usig;
        exec($Prog, $output, $return); 

        $choice = readline("\n\rHit Enter to Continue: "); 
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
        $this->out("Reply Data = ".$ReplyData);
 
        $choice = readline("\n\rHit Enter to Continue: ");


    }

    /**
    ****************************************************
    * Test Load Firmware
    *
    * This function powers up the UUT and waits for 
    * the user to remotely load firmware into the 
    * device.
    */
    private function _testLoadFirmware()
    {
        $this->out("Powering Up UUT!\n\r");
        $this->_powerUUT(self::ON);
        sleep(3);
        $this->out("More Sleep!");
        sleep(2);
        $this->out("Done sleeping!");

        $this->out("Go ahead and load code");

        $choice = readline("\n\rHit Enter to Continue: ");


        
        $this->_powerUUT(self::OFF);
        $choice = readline("\n\rHit Enter to Continue: ");

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
        fwrite($fp, $SIGdata);
        fclose($fp);

        $this->out("User Signature Bytes File Written!");
 
        $choice = readline("\n\rHit Enter to Continue: ");

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
    private function _programUUT()
    {
        $output = array();

        $this->display->displayHeader("Testing Programmed UUT");
        $this->out("\n\r");
        $this->_powerUUT(self::ON);
        sleep(3);
        $this->out("More Sleep!");
        sleep(2);
        $this->out("Done sleeping!");

        $replyData = $this->_pingEndpoint(0x8012);

        $this->out("Ping Reply = ".$replyData);
        
        $this->_runApplicationTest();
        $choice = readline("\n\rHit Enter to Continue: ");


        
        $this->_powerUUT(self::OFF);
        $choice = readline("\n\rHit Enter to Continue: ");

    }

    /**
    ************************************************************
    * Calibrate DAC Routine
    *
    * This function performs the DAC calibration.  It first
    * determines the offset value and sets it.  Then, it 
    * determines the gain error and sets that in the DAC 
    * registers.
    */
    private function _calibrateDAC()
    {
        $this->out("Powering up UUT");
        $result = $this->_testUUTpower();
        sleep(1);
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->out("Send Read DAC output Command");

        $dacVolts = $this->_readUUTdacVolts();
        $this->out("DAC volts = ".$dacVolts);

        $this->out("Setting DAC output to 0.825 Volts");
        $dataVal = "0400";
        $replyData = $this->_setDAC($dataVal);
        $this->out("Reply Data = ".$replyData);
        sleep(1);

        $dacVolts = $this->_readUUTdacVolts();
        $this->out("New DAC volts = ".$dacVolts);
        
        
            
        $offset = 114;

        
        for ($i = 0; $i<10; $i++) {
            $offset += 1;
            $this->out("Offset = ".$offset);
            $hexOff = dechex($offset);
            $len = strlen($hexOff);
            if ($len < 2) {
                $hexOff = "0".$hexOff;
            } else if ($len > 2) {
                $hexOff = substr($hexOff, $len-2, 2);
            }
        
            $this->out("Setting Offset : ".$hexOff);
            $result = $this->_setDACOffset($hexOff);

            $dacVolts = $this->_readUUTdacVolts();
            $this->out("Adjusted DAC volts = ".$dacVolts);
        }
        

        $choice = readline("\n\rHit Enter to Continue: ");

        $this->out("Powering down UUT");
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
        $hexVal = substr($hexVal, len-4, 4);
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
	    $dataVal = "0300";
	    break;
	  case 2:
	    $dataVal = "0301";
	    break;
	  case 3:
	    $dataVal = "0302";
	    break;
	  case 4:
	    $dataVal = "0303";
	    break;
	  case 5:
	    $dataVal = "0204";
	    break;
	  case 6:
	    $dataVal = "0205";
	    break;
	  case 7:
	    $dataVal = "0206";
	    break;
	  case 8:
	    $dataVal = "0207";
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
    * @return $result  boolean true for success, false for failure
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
                $result = true;
            } else {
                $result = false;
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
                $result = true;
            } else {
                $result = false;
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
        $result = true;
        
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
                    $result = true;
                } else {
                    $result = false;
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
                    $result = true;
                } else {
                    $result = false;
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
        

        $FUSE1 = 0x00;
        $FUSE2 = 0xFE;  /* changed to FE to use 0x0000000 as reset vector */
        $FUSE3 = 0xFF;
        $FUSE4 = 0xFF;
        $FUSE5 = 0xE1;
        $FUSE6 = 0xFF;


        $Avrdude = "avrdude -px32e5 -c avrisp2 -P usb -e -B 10 -i 100 ";
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
        $choice = readline(
                "\n\rVerify Programmer is Connected and Hit Enter to Continue: ");
        

        $FUSE1 = 0x00;
        $FUSE2 = 0xBE;  
        $FUSE3 = 0xFF;
        $FUSE4 = 0xFF;
        $FUSE5 = 0xE1;
        $FUSE6 = 0xFF;


        $Avrdude = "avrdude -px32e5 -c avrisp2 -P usb -e -B 10 -i 100 ";
        $flash = "-U flash:w:104603boot.ihex ";
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
    */
    private function _loadApplicationFirmware()
    {
        $this->out("Loading Application Firmware");

        $hugnetLoad = "../bin/./hugnet_load";
        $firmwarepath = "~/code/HOS/packages/104603-00393801C-0.3.0.gz";

        $Prog = $hugnetLoad." -i ".$this->_ENDPT_SN." -D ".$firmwarepath;

        system($Prog, $return);

        if ($return == 0) {
            $result = true;
        } else {
            $result = false;
        } 

        $this->out("Result = ".$result);

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
