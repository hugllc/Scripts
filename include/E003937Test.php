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
class E003937Test
{
    /** predefined endpoint serial number used in test firmware **/
    const TEST_ID = 0x20;
    const KNOWN_GOOD_ID = 0x1022;
    const KNOWN_GOOD_ID_TWO = 0x1010;
    
    /** packet commands to test firmware **/
    const TEST_ANALOG_COMMAND  = 0x20;
    const SET_DIGITIAL_COMMAND = 0x25;
    const TEST_DIGITAL_COMMAND = 0x26;
    const CONFIG_DAC_COMMAND   = 0x27;
    const SET_DAC_COMMAND      = 0x28;
    
    /* DAC Configuration bytes */
    const DAC_CONFIG_IREF = "0010";
    const DAC_CONFIG_AREF = "0013";
    const DAC_CONFIG_16_IREF = "0018";
    const DAC_CONFIG_16_AREF = "001B";

    /** path to openocd for JTAG emulator **/
    private $_openOcdPath = "~/code/HOS/toolchain/bin/openocd";

    /** path to program.cfg for loading test elf file through JTAG **/
    private $_programTestPath = "~/code/HOS/src/003937test/program.cfg";

    /** path to program.cfg for loading boot elf file through JTAG **/
    private $_programBootPath = "~/code/HOS/src/003937boot/program.cfg";

    private $_device;
    private $_system;
    private $_goodDevice;
    private $_goodDeviceTwo;

    private $_testErrorArray = array(
                0 => "No Response!",
                1 => "Board Test Passed!",
                2 => "Ping Test Failed!",
                3 => "Test 1 Failed",
                4 => "Test 2 Failed",
                5 => "Test 3 Failed",
            );


    /** ascii string hex value for revision letter **/
    private $_HWrev;

    /*
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        $this->_system = &$sys; 
        $this->_device = $this->_system->device();

        $this->_goodDevice = $this->_system->device();
        $this->_goodDevice->set("id", self:: KNOWN_GOOD_ID);
        $this->_goodDevice->set("Role","TesterKnownGood");
        $this->_goodDevice->action()->config();
        $this->_goodDevice->action()->loadConfig();

        $this->_goodDeviceTwo = $this->_system->device();
        $this->_goodDeviceTwo->set("id", self:: KNOWN_GOOD_ID_TWO);
        $this->_goodDeviceTwo->set("Role", "TesterKnownGood");
        $this->_goodDeviceTwo->action()->config();
        $this->_goodDeviceTwo->action()->loadConfig();


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
        $obj = new E003937Test($config, $sys);
        return $obj;
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
    * @return void
    *   
    */
    public function runTest()
    {
        $exitTest = false;
        
        $result = $this->_checkGoodEndpoint();
        if ($result == true) {

            $StartSN = $this->_getSerialNumber();
            $snCounter = 0;
            $programHW = $this->_getHardwareNumber();

            do {
                /* parent::main(); */
                $programSN = $StartSN + $snCounter;
                $this->_device->set("id", $programSN);

                $this->_loadTestFirmware();
                $testResult = $this->_testEndpoint();

                if ($testResult == true) {
                    $this->_displayPassed();

                    $retVal = $this->_writeSerialNumAndHardwareVer();
                    
                    if ($retVal == true) {
                        $snCounter++;
                        $retVal = $this->_loadBootLoader();
                        if ($retVal == 0) {
                            $this->_displayPassed();
                        } else {
                            $this->_system->out("*****************************************");
                            $this->_system->out("*                                       *");
                            $this->_system->out("*       Load Boot Loader Failed!        *");
                            $this->_system->out("*                                       *");
                            $this->_system->out("*****************************************");
                        }
                    } else {
                        $this->_system->out("*********************************************");
                        $this->_system->out("*                                           *");
                        $this->_system->out("*     Board SN & HW Programming Failed      *");
                        $this->_system->out("*             Please verify:                *");
                        $this->_system->out("*   Serial number and hardware partnumber   *");
                        $this->_system->out("*                                           *");
                        $this->_system->out("*********************************************");
                    }
                } else {
                    $this->_displayFailed();
                }

                $exitTest = $this->_repeatTestMenu();

            } while ($exitTest == false);  
        } else {
            $this->_system->out("Known Good Endpoint failed to respond!\n\r");
            $this->_system->out("Exiting test!\n\r");
        }

    }

    /**
    ************************************************************
    * Display Board Passed Routine
    *
    * This function displays the board passed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayPassed()
    {
        $this->_system->out("\n\r");
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   P A S S E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out("\n\r");
        $this->_system->out("\n\r");

    }

    /**
    ************************************************************
    * Display Board Passed Routine
    *
    * This function displays the board passed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayFailed()
    {
        $this->_system->out("\n\r");
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   F A I L E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out("\n\r");
        $this->_system->out("\n\r");

    }


    /**
    ************************************************************
    * Repeat Test Menu Routine
    * 
    * This function displays a menu which allows the user to 
    * continue testing and programming another board or exit 
    * the test program.
    *
    * @return boolean exit true or false
    *
    */
    private function _repeatTestMenu()
    {
        $this->_system->out("\n\r");
        $choice = readline("\n\rTest Another Board?(y/N): ");
        
        if (($choice == 'Y') || ($choice == 'y')) {
            
            $choice = readline("\n\rChange Hardware Version? (y/N): ");

            if (($choice == 'Y') || ($choice == 'y')) {
                $this->_getHardwareNumber();
            }
            $exitVal = false;
        } else {
            $exitVal = true;
        }

        return $exitVal;
    }

    /**
    ************************************************************
    * Load Test Firmware Routine
    *
    * This function loads the test firmware into the 
    * the endpoint through the endpoint programmer.
    *
    * @return void
    *
    */
    private function _loadTestFirmware()
    {
        $this->_system->out("\n\r");
        $this->_system->out(str_repeat("*", 54));
        $this->_system->out("* Loading HUGnetLab Test Firmware "
            ."and Beginning Test *"
        );
        $this->_system->out(str_repeat("*", 54));
        $this->_system->out("\n\r");
        
        /* Load the test firmware through the JTAG emulator */
        $Prog = $this->_openOcdPath." -f ".$this->_programTestPath; 

        exec($Prog, $out, $return);

    }

     /**
    ************************************************************
    * Test Endpoint Routine
    *
    * This function runs the tests in the endpoint test firmware
    * and returns the results.
    *
    * @return boolean $testResult   
    */
    private function _checkGoodEndpoint()
    {
        
        

        $Result = $this->_pingEndpoint(self::KNOWN_GOOD_ID);
        if ($Result = true) {
            $this->_system->out("Known Good Endpoint Responding!");
        } else {
            $this->_system->out("Known Good Endpoint Failed to Respond!");
        }


        return $Result;

    }


    /**
    ************************************************************
    * Test Endpoint Routine
    *
    * This function runs the tests in the endpoint test firmware
    * and returns the results.
    *
    * @return boolean $testResult
    *
    */
    private function _testEndpoint()
    {
            
   
        $Result = $this->_pingEndpoint(self::TEST_ID);

        if ($Result == true) {
            $Result = $this->_testADC();
        } else {
            $this->_system->out("Failed Endpoint Ping!");
        }

        if ($Result == true) {
            $Result =  $this->_testDAC();
        }

       if ($Result == true) {
            $Result = $this->_testDigital();
        }

        return $Result;
    }


    /*****************************************************************************/
    /*                                                                           */
    /*             A N A L O G - T O - D I G I T A L   T E S T S                 */
    /*                                                                           */
    /*****************************************************************************/


    /**
    ************************************************************
    * Test ADC Routine
    *
    * This runs the tests on each of the ADC channels and 
    * evaluates the results to determine a pass or fail of the
    * channel.
    *
    * @return boolean $result true for pass, false for failure
    *
    */
    private function _testADC()
    {
       
        $goodVolts = array();
        $result = $this->_readKnownGoodADC($goodVolts);
        $adcInput = 1;

        while (($result == true) and ($adcInput < 9)) {
            $myVolts = $this->_readADCinput($adcInput);
            $result = $this->_testADCinput($adcInput, $myVolts, $goodVolts);
            $adcInput++;
        }
            
        if ($result == true) {
            $result = $this->_testRTDinput();
        }

        return $result;
    }

    /**
    ***********************************************************
    * Test RTD Input Routine
    *
    * This routine reads the input voltage on ADC4 which is 
    * a reflection of the on board resistance temperature
    * device.
    *
    */
    private function _testRTDinput()
    {
        /* Rev B boards do not have RTD or bias resistor installed */
        if ($this->_HWrev != "B") {  

            $myVolts = $this->_readADCinput(9);

            $this->_system->out("RTD Input Voltage = ".$myVolts." VDC");
            if (($myVolts > 0.8) and ($myVolts < 0.95)) {
                $this->_system->out("RTD Input Passed!");
                $result = true;
            } else {
                $this->_system->out("RTD Input Failed!");
                $result = false;
            }

        } else {
            $result = true;
        }

        return $result;
    }


    /**
    ************************************************************
    * Read Known Good Board Routine
    *
    * This routine reads the ADC inputs of the known good board
    * and fills in the array with input voltage values.
    *
    * @return boolean true or false 
    *
    */
    private function _readKnownGoodADC(&$KnownVolts)
    {

        /* read known good board for input voltage values */
        $voltageVals = $this->_goodDevice->action()->poll();
        $voltageVals = $this->_goodDevice->action()->poll();
        if (is_object($voltageVals)) {
            $channels = $this->_goodDevice->dataChannels();
            $this->_system->out("\n\r");
            $this->_system->out("Date: ".date("Y-m-d H:i:s", $voltageVals->get("Date")));
            for ($i = 0; $i < $channels->count(); $i++) {
                $chan = $channels->dataChannel($i);
                $KnownVolts[$i] = $voltageVals->get("Data".$i);
                $this->_system->out($chan->get("label").": ".$voltageVals->get("Data".$i)." ".html_entity_decode($chan->get("units")));
            }
            $this->_system->out("\n\r");
            $result = true;
        } else {
            $this->_system->out("No object returned from device poll!");
            $result = false;
        }


        return $result;
    }

    /**
    ************************************************************
    * Read DUT ADC Input Routine
    *
    * This function reads the device under test analog input
    * specified by the input parameter and returns the reply 
    * data.
    * 
    * @return string $reply data
    *
    */
    private function _readADCinput($inputNum)
    {
        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = sprintf("0%s",$inputNum);
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $readVolts = $this->_convertADCbytes($ReplyData);
        $myMult = $this->_biasResistorAdjust($inputNum);
        $readVolts = $readVolts * $myMult;

        return $readVolts;
    }

    /**
    ************************************************************
    * Test ADC Input Routine
    *
    * This function tests the input voltage read from the DUT
    * and compares it to the known good board voltage reading.
    * If the test voltage is within limits, the test passes
    * and a true result is returned.
    *
    * @return boolean true or false test result
    *
    */
    private function _testADCinput($inputNum, $inVolts, $kVolts)
    {
        $kVal = $inputNum - 1;

        if ($inputNum > 4) {
            $hiTol = 1.10;
            $loTol = 0.90;
        } else {
            $hiTol = 1.04;
            $loTol = 0.96;
        }

        $this->_system->out("Known Voltage :".$kVolts[$kVal]."V  Test Voltage :".$inVolts."V");
        if (($inVolts >= ($kVolts[$kVal] * $loTol)) and ($inVolts <= ($kVolts[$kVal] * $hiTol))) {
            $result = true;
            $this->_system->out("ADC Input ".$inputNum." Passed!");
        } else {
            $result = false;
            $this->_system->out("ADC Input ".$inputNum." Failed!");
        }

        return $result;

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
    ************************************************************
    * Convert Negative Value Routine
    *
    * This routine check the ADC value returned to see if 
    * it is a negative voltage measurement.  If so, it 
    * it converts it to the positive number of steps.
    */
    private function _convertNegativeValue(&$InVal)
    {
        
        if($InVal > 0x7fffff) {
            /* invert bits and add 1 to convert */
            /* two's complement value from ADC  */
            $newVal = 0xffffff-$InVal;
            $newVal = $newVal + 1;

            /* adjust for offset between Agnd and Cgnd */
            /* measure offset is 0.90 volts. */
            $newVal = 6291456 - $newVal;    
        } else {
            $newVal = $InVal;
        }

        return $newVal;
    }


    /**
    ************************************************************
    * Convert ADC bytes to Voltage Routine
    *
    * This routine takes in 4 bytes of ADC channel data
    * and converts them into an output voltage. It uses the 
    * hardware version to determine the input and bias resistor
    * values and adjusts the reading based on them.
    *
    * @@return float voltage reading
    *
    */
    private function _convertADCbytes(&$inString)
    {
        $myValue = $this->_convertReplyData($inString);
        
        $myNewValue = $this->_convertNegativeValue($myValue);

        $steps = 1.2 / pow(2,23);
        $volts = $steps * $myNewValue;

        
        return $volts;
    }

    /**
    *************************************************************
    * Bias Resistor Adjust Routine
    *
    * This routine looks and the hardware version and the input
    * number to determine an adjustment multiplier for the input
    * voltage based on the input and bias resistors.
    *
    * @return float adjustment multiplier
    *
    */
    private function _biasResistorAdjust($inputNum)
    {
        switch ($inputNum) {
            case 1:
            case 2:
            case 3:
            case 4:
                if (($this->_HWrev == "A") | ($this->_HWrev == "C") | ($this->_HWrev == "D")){
                    $multiplier = 1.1;
                }
                else {
                    $multiplier = 1.0;
                }
                break;
            case 5:
            case 6:
                if (($this->_HWrev == "C") | ($this->_HWrev == "D")){
                    $multiplier = 1.1;
                } else if (($this->_HWrev == "A") | ($this->_HWrev == "F")){
                    $multiplier = 101;
                } else {
                    $multiplier = 1.0;
                }
                break;
            case 7:
            case 8:
                if ($this->_HWrev == "D"){
                    $multiplier = 1.1;
                } else if ($this->_HWrev == "B"){
                    $multiplier = 1.0;
                } else {
                    $multiplier = 101;
                }
                break;
            case 9:
                $multiplier = 1.0;
                break;
        }

        return $multiplier;

    }
    
    /*****************************************************************************/
    /*                                                                           */
    /*     D I G I T A L - T O - A N A L O G  C O N V E R T E R   T E S T S      */
    /*                                                                           */
    /*****************************************************************************/


    /**
    ************************************************************
    * Test DAC Routine
    *
    * This test sets the output of the Digital to Analog 
    * converter and checks the output voltage through the 
    * known good board A/D input 1.
    *
    * @return boolean $result true for pass, false for failure.
    *
    */
    private function _testDAC()
    {
        
        $voltageVals = $this->_goodDeviceTwo->action()->poll();

        $Result = $this->_configDAC(1);

        /*
        ****************************************************
        * set DAC output to 1.2 volts                      *
        */
        if ($Result == true) {
            $Result = $this->_dacTestMax(1);
        }

        /* set DAC for 0.60 Volts output */
        if ($Result == true) {
            $Result = $this->_dacTestMid(1);
        }

        /* set DAC for 0.0 Volts output */
        if ($Result == true) {
            $Result = $this->_dacTestMin(1);
        }
        
        /*
        ***************************************************
        * Set DAC configuration to use the 2.5v reference *
        */
        if ($Result == true) {
            $Result = $this->_configDAC(2);
        }

        /* output 2.50 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMax(2);
        }

        /* output 1.25 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMid(2);
        }

        /* output 0.0 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMin(2);
        }
        
        /*
        ****************************************************
        * Set DAC configuration for 16 bit, 1.2v reference *
        */
        if ($Result == true) {
            $Result = $this->_configDAC(3);
        }

        if ($Result == true) {
            $Result = $this->_dacTestMax(3);
        }

        /* output 0.6 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMid(3);
        }

        /* output 0.0 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMin(3);
        }

        /*
        ****************************************************
        * Set DAC configuration for 16 bit, 2.5v reference *
        */
        if ($Result == true) {
            $Result = $this->_configDAC(4);
        }

        if ($Result == true) {
            $Result = $this->_dacTestMax(4);
        }

        /* output 0.6 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMid(4);
        }

        /* output 0.0 volts */
        if ($Result == true) {
            $Result = $this->_dacTestMin(4);
        }

        return $Result;
    }


    /**
    ***********************************************************
    * Configure DAC Routine
    * 
    * This routine configures the DAC for either the internal
    * 1.2V reference or the Vaa 2.5V reference.
    *
    */
    private function _configDAC($configNum)
    {
        $idNum = self::TEST_ID;
        $cmdNum = self::CONFIG_DAC_COMMAND;

        if ($configNum == 1) {
            $dataVal = self::DAC_CONFIG_IREF;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "1000") {
                $Result = true;
            } else {
                $this->_system->out("Failed DAC Config 1");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else if ($configNum == 2) {
            $dataVal = self::DAC_CONFIG_AREF;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "1300") {
                $Result = true;
            } else {
                $this->_system->out("Failed DAC Config 2");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else if ($configNum == 3) {
            $dataVal = self::DAC_CONFIG_16_IREF;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "1800") {
                $this->_system->out("Successful set of DAC for 16 bit mode Iref!");
                $Result = true;
            } else {
                $this->_system->out("Failed DAC Config 3");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else if ($configNum == 4) {
            $dataVal = self::DAC_CONFIG_16_AREF;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "1B00") {
                $this->_system->out("Successful set of DAC for 16 bit mode Aref!");
                $Result = true;
            } else {
                $this->_system->out("Failed DAC Config 4");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else {
            $this->_system->out("Invalid DAC configuration requested!");
            $Result = false;
        }


        return $Result;
    }

    /**
    *************************************************************
    * Test Max DAC output Routine
    *
    * This function tests the maximum voltage output for the DAC
    * for the given reference voltage set by the configuration.
    *
    */
    private function _dacTestMax($configNum)
    {

        $idNum = self::TEST_ID;
        $cmdNum = self::SET_DAC_COMMAND;

        if ($configNum < 3) {
            $dataVal = "0fff";
        } else {
            $dataVal = "ffff";
        }
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2 byte response comes back little endian, so the byte */
        /* order is reversed from send data.                     */
        if (($configNum < 3) and ($ReplyData == "FF0F")) {
            $Result = true;
        } else if (($configNum > 2) and ($ReplyData == "FFFF")) {
            $Result = true;
        } else {
            $this->_system->out("Failed Config ".$configNum." Set Max");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }


        if ($Result == true) {
            /* read voltage for known good endpoint 2 */
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            if (is_object($voltageVals)) {
                $channels = $this->_goodDeviceTwo->dataChannels();
                for ($i = 0; $i < $channels->count(); $i++) {
                    $chan = $channels->dataChannel($i);
                    $KnownVolts[$i] = $voltageVals->get("Data".$i);
                }
            } else {
                $this->_system->out("No object returned");
                $result = false;
            }
            
            if ($Result == true) {
                /* test DAC output voltage */
                $dacVolts = $KnownVolts[6] + 0.98;
                if ($configNum == 1) {
                    $this->_system->out("DAC set to 1.2 V Measured : ".$dacVolts);
                    if (($dacVolts > 1.1) and ($dacVolts < 1.3)) {
                        $Result = true;
                        $this->_system->out("DAC Test 1 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 1 Failed!");
                    }
                } else if ($configNum == 2) {
                    $this->_system->out("DAC set to 2.5 V Measured : ".$dacVolts);
                    if (($dacVolts > 2.30) and ($dacVolts < 2.70)) {
                        $Result = true;
                        $this->_system->out("DAC Test 4 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 4 Failed!");
                    }
                } else if ($configNum == 3) {
                    $this->_system->out("DAC set to 1.2 V Measured : ".$dacVolts);
                    if (($dacVolts > 1.1) and ($dacVolts < 1.3)) {
                        $Result = true;
                        $this->_system->out("DAC Test 7 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 7 Failed!");
                    }
                } else if ($configNum == 4) {
                    $this->_system->out("DAC set to 2.5 V Measured : ".$dacVolts);
                    if (($dacVolts > 2.30) and ($dacVolts < 2.70)) {
                        $Result = true;
                        $this->_system->out("DAC Test 10 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 10 Failed!");
                    }
                
                } else {
                    $this->_system->out("Invalid Configuration Number");
                    $Result == false;
                }
            }
        }

        return $Result;

    }


    /**
    *************************************************************
    * Test Mid DAC output Routine
    *
    * This function tests the mid range voltage output for the DAC
    * for the given reference voltage set by the configuration.
    *
    */
    private function _dacTestMid($configNum)
    {

        $idNum = self::TEST_ID;
        $cmdNum = self::SET_DAC_COMMAND;
        if ($configNum < 3) {
            $dataVal = "07ff";
        } else {
            $dataVal = "7fff";
        }
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2 byte response comes back little endian, so the byte */
        /* order is reversed from send data.                     */
        if (($configNum < 3) and ($ReplyData == "FF07")) {
            $Result = true;
        } else if (($configNum > 2) and ($ReplyData == "FF7F")){
            $Result = true;
        } else {
            $this->_system->out("Failed Config ".$configNum." Set Mid");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }
        
        if ($Result == true) {
            /* read voltage for known good endpoint 2 */
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            if (is_object($voltageVals)) {
                $channels = $this->_goodDeviceTwo->dataChannels();
                for ($i = 0; $i < $channels->count(); $i++) {
                    $chan = $channels->dataChannel($i);
                    $KnownVolts[$i] = $voltageVals->get("Data".$i);
                }
            } else {
                $this->_system->out("No object returned");
                $result = false;
            }

            if ($Result == true) {
                /* test DAC output */
                $dacVolts = $KnownVolts[6] + 0.98;
                if ($configNum == 1) {
                    $this->_system->out("DAC set to 0.6 V Measured : ".$dacVolts);
                    if (($dacVolts > 0.5) and ($dacVolts < 0.7)) {
                        $Result = true;
                        $this->_system->out("DAC Test 2 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 2 Failed!");
                    }
                } else if ($configNum == 2) {
                    $this->_system->out("DAC set to 1.25 V Measured : ".$dacVolts);
                    if (($dacVolts > 1.20) and ($dacVolts < 1.30)) {
                        $Result = true;
                        $this->_system->out("DAC Test 5 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 5 Failed!");
                    }
                } else if ($configNum == 3) {
                    $this->_system->out("DAC set to 0.6 V Measured : ".$dacVolts);
                    if (($dacVolts > 0.5) and ($dacVolts < 0.7)) {
                        $Result = true;
                        $this->_system->out("DAC Test 8 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 8 Failed!");
                    }
                } else if ($configNum == 4) {
                    $this->_system->out("DAC set to 1.25 V Measured : ".$dacVolts);
                    if (($dacVolts > 1.20) and ($dacVolts < 1.30)) {
                        $Result = true;
                        $this->_system->out("DAC Test 11 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 11 Failed!");
                    }
                } else {
                    $this->_system->out("Invalid Configuration Number!");
                    $Result = false;
                }
            }
        }

        return $Result;
    }


    /**
    *************************************************************
    * Test Min DAC output Routine
    *
    * This function tests the minimum voltage output for the DAC
    * for the given reference voltage set by the configuration.
    *
    */
    private function _dacTestMin($configNum)
    {
        $idNum = self::TEST_ID;
        $cmdNum = self::SET_DAC_COMMAND;
        $dataVal = "0000";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2 byte response comes back little endian, so the byte */
        /* order is reversed from send data.                     */
        if ($ReplyData == "0000") {
            $Result = true;
        } else {
            $this->_system->out("Failed Config ".$configNum." Set Min");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        if ($Result == true) {
            /* read voltage for known good endpoint 2 */
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            $voltageVals = $this->_goodDeviceTwo->action()->poll();
            if (is_object($voltageVals)) {
                $channels = $this->_goodDeviceTwo->dataChannels();
                for ($i = 0; $i < $channels->count(); $i++) {
                    $chan = $channels->dataChannel($i);
                    $KnownVolts[$i] = $voltageVals->get("Data".$i);
                }
            } else {
                $this->_system->out("No object returned");
                $result = false;
            }
            
            if ($Result == true) {
                /* test DAC output */
                $dacVolts = $KnownVolts[6] + 0.98;
                $this->_system->out("DAC set to 0.0 V Measured : ".$dacVolts);

                if ($configNum == 1) {
                    if (($dacVolts > -0.1) and ($dacVolts < 0.1)) {
                        $Result = true;
                        $this->_system->out("DAC Test 3 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 3 Failed!");
                    }
                } else if ($configNum == 2) {
                    if (($dacVolts > -0.1) and ($dacVolts < 0.1)) {
                        $Result = true;
                        $this->_system->out("DAC Test 6 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 6 Failed!");
                    }
                } else if ($configNum == 3) {
                    if (($dacVolts > -0.1) and ($dacVolts < 0.1)) {
                        $Result = true;
                        $this->_system->out("DAC Test 9 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 9 Failed!");
                    }
                } else if ($configNum == 4) {
                    if (($dacVolts > -0.1) and ($dacVolts < 0.1)) {
                        $Result = true;
                        $this->_system->out("DAC Test 12 Passed!");
                    } else {
                        $Result = false;
                        $this->_system->out("DAC Test 12 Failed!");
                    }
                } else {
                    $this->_system->out("Invalid Configuration Number!");
                    $Result = false;
                }
            }
        }


        return $Result;
    }

    /*****************************************************************************/
    /*                                                                           */
    /*                      D I G I T A L   T E S T S                            */
    /*                                                                           */
    /*****************************************************************************/

    /**
    ************************************************************
    * Test Digital I/O Routine
    *
    * This function tests the general purpose I/O ports available
    * on SV2.
    *
    * @return boolean $result true for pass, false for failure.
    *
    */
    private function _testDigital()
    {
        /* Setup for test configuration 1 */
        $Result = $this->_configDigital(1);

        if ($Result == true) {
            $Result = $this->_digitalTest1();
        }

        if ($Result == true) {
            $Result = $this->_digitalTest2();
        }


        /*   Setup for test configuration 2  */
        if ($Result == true) {
            $Result = $this->_configDigital(2);
        }

        if ($Result == true) {
            $Result = $this->_digitalTest3();
        }

        if ($Result == true) {
            $Result = $this->_digitalTest4();
        }
   
        return $Result;
    }

    /**
    ************************************************************
    * Digital Configuration Routine
    *
    * This function sends a command to the test firmware to 
    * to configure the digital I/O port for testing.
    *
    * Configuration 1:
    *      P2.1 output ---->  P0.4 input
    *      P1.6 output ---->  P0.3 input
    *      P1.5 output ---->  P0.2 input
    *      P1.4 output ---->  P0.1 input
    *      P2.0 output ---->  P0.0 input
    *
    * Configuration 2:
    *      P2.1 input <----  P0.4 output
    *      P1.6 input <----  P0.3 output
    *      P1.5 input <----  P0.2 output
    *      P1.4 input <----  P0.1 output
    *      P2.0 input <----  P0.0 output
    *
    */
    private function _configDigital($configNum)
    {
        $idNum = self::TEST_ID;
        $cmdNum = self::SET_DIGITIAL_COMMAND;
        
        if ($configNum == 1) {
            $dataVal = "01";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "00") {
                $Result = true;
            } else {
                $this->_system->out("Failed to configure GPIO 1");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else if ($configNum == 2) {
            $dataVal = "02";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "1F") {
                $Result = true;
            } else {
                $this->_system->out("Failed to configure GPIO 2");
                $this->_system->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        } else {
            $this->_system->out("Invalid Configuration");
            $Result = false;
        }

        return $Result;
        
    }

    /**
    ************************************************************
    * Digital Test 1 Routine
    *
    * This function performs the first digital test on I/O
    * configuration 1.
    *
    *  Test 1
    *      P2.1 output (H)---->  P0.4 input
    *      P1.6 output (L)---->  P0.3 input
    *      P1.5 output (H)---->  P0.2 input
    *      P1.4 output (L)---->  P0.1 input
    *      P2.0 output (H)---->  P0.0 input
    *
    *
    */
    private function _digitalTest1()
    {

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_DIGITAL_COMMAND;
        $dataVal = "0101";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "15") {
            $Result = true;
            $this->_system->out("Digital Test 1 Passed!");
        } else {
            $this->_system->out("Digital Test 1 Failed!");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        return $Result;

    }

    /**
    ************************************************************
    * Digital Test 2 Routine
    *
    * This function performs the second digital test on I/O
    * configuration 1.
    *
    *  Test 2
    *      P2.1 output (L)---->  P0.4 input
    *      P1.6 output (H)---->  P0.3 input
    *      P1.5 output (L)---->  P0.2 input
    *      P1.4 output (H)---->  P0.1 input
    *      P2.0 output (L)---->  P0.0 input
    *
    */
    private function _digitalTest2()
    {
        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_DIGITAL_COMMAND;
        $dataVal = "0102";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "0A") {
            $Result = true;
            $this->_system->out("Digital Test 2 Passed!");
        } else {
            $this->_system->out("Digital Test 2 Failed!");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        return $Result;

    }

    /**
    ************************************************************
    * Digital Test 3 Routine
    *
    * This function performs the first digital test on I/O
    * configuration 2.
    *
    * Test 3
    *      P2.1 input <----(H) P0.4 output
    *      P1.6 input <----(L) P0.3 output
    *      P1.5 input <----(H) P0.2 output
    *      P1.4 input <----(L) P0.1 output
    *      P2.0 input <----(H) P0.0 output
    *
    */
    private function _digitalTest3()
    {

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_DIGITAL_COMMAND;
        $dataVal = "0201";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "15") {
            $Result = true;
            $this->_system->out("Digital Test 3 Passed!");
        } else {
            $this->_system->out("Digital Test 3 Failed!");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        return $Result;

    }

    /**
    ************************************************************
    * Digital Test 4 Routine
    *
    * This function performs the second digital test on I/O
    * configuration 2.
    *
    * Test 4
    *      P2.1 input <----(L) P0.4 output
    *      P1.6 input <----(H) P0.3 output
    *      P1.5 input <----(L) P0.2 output
    *      P1.4 input <----(H) P0.1 output
    *      P2.0 input <----(L) P0.0 output
    *
    */
    private function _digitalTest4()
    {

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_DIGITAL_COMMAND;
        $dataVal = "0202";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "0A") {
            $Result = true;
            $this->_system->out("Digital Test 4 Passed!");
        } else {
            $this->_system->out("Digital Test 4 Failed!");
            $this->_system->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        return $Result;

    }



    /**
    ************************************************************
    * Load Bootloader Firmware Routine
    * 
    * This function loads the endpoint with the correct 
    * version of the bootloader through the endpoint 
    * programmer.
    *
    * @return void
    *
    */
    private function _loadBootLoader()
    {
        $Prog = $this->_openOcdPath." -f ".$this->_programBootPath; 

        exec($Prog, $out, $return);

        
        $this->_system->out("\n\r");
        $this->_system->out("Enpoint programmed with bootloader!");
        $response = substr($this->_device->encode(), 0, 20);
        $this->_system->out("Serial number and Hardware version");
        $this->_system->out("are :".$response);

        return $return;

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
            EndpointTest::_clearScreen();
            $this->_system->out("Enter a hex value for the starting serial number");
            $SNresponse = readline("in the following format- 0xhhhh: ");
            $this->_system->out("\n\r");
            $this->_system->out("Your starting serial number is: ".$SNresponse);
            $response = readline("Is this correct?(Y/N): ");
        } while (($response <> 'Y') && ($response <> 'y'));

        $SN = hexdec($SNresponse);

        return $SN;
    }


    /**
    ************************************************************
    * Get Hardware Version Routine
    *
    * This function reads the xml file with current hardware
    * versions and allows user to select the hardware version
    * number for the board they are programming.
    *
    * @return string $HWnum the hardware version as a string
    */
    private function _getHardwareNumber()
    {

        $dev = $this->_system->device()->getHardwareTypes();
        
        $HWarray = array();

        foreach ($dev as $endp) {
            if ($endp['Param']['ARCH'] == "ADuC7060") {
                $HWarray[] = $endp['HWPartNum'];
            }
        }
        

        foreach ($HWarray as $key => $HWnum) {
            $this->_system->out($key."= ".$HWnum);
        }
        $this->_system->out("\n\r");

        $HWver = (int)readline(
            "\n\rEnter Hardware version (0 - ". (count($HWarray)-1)."): "
        );
        $this->_system->out("\n\r");

        $HWnumber = $HWarray[$HWver];
        $this->_device->set("HWPartNum", $HWnumber);
 
 
        $rev = substr($HWnumber, (strlen($HWnumber)-1), 1);
        $this->_HWrev = $rev;

        $myData = sprintf("%02X", ord($rev));
        $this->_system->out("dataVal: ".$myData);
     

        return $HWnumber;

    }


    /**
    ***********************************************************
    * Write Serial Number and Hardware Version Routine
    * 
    * This function reads the input serial number and hardware
    * version, programs them into flash, and then verifies the
    * the programming by reading the numbers out.
    *
    * @return boolean result, true if pass, false if fail.
    *
    */
    private function _writeSerialNumAndHardwareVer()
    {

        $result = false;

        $response1 = substr($this->_device->encode(), 0, 20);
        $response2 = substr($this->_device->encode(), 0, 10);
        /* add new unique serial number */
        /* for now it is the same as ID number */
        /* but cloning should allow ID number to change */
        /* and unique serial number to remain the same */
        $response = $response1.$response2;
        $this->_system->out("Serial number and Hardware version");
        $this->_system->out("program data is : ".$response);
        $this->_system->out("Unique Serial Number is : ".$response2);

        if (strlen($response) == 30) {
            $idNum = self::TEST_ID;
            $cmdNum = 0x1c;
            $dataVal = $response;
            $replyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
            $this->_system->out("\nReply Data : ".$replyData);
            $result = true;
        } else {
            $this->_system->out("Invalid program data, programming aborted!");
            $result = false;
        }

        $idNum = self::TEST_ID;
        $cmdNum = 0x0c;
        $dataVal = "76000A";

        $SerialandHW = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        return $result;
    }


    /**
    ***********************************************************
    * Send a Ping routine
    *
    * This function pings the endpoint
    * with the test serial number and 
    * checks to see that the endpoint
    * responds.
    *
    * @param int $Sn Endpoint Serial Number
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
        return true;
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

        $this->_system->out("Data is ".$DataVal);


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
