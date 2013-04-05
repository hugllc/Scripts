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
class EndpointTest extends \HUGnet\ui\Daemon
{
    /** predefined endpoint serial number used in test firmware **/
    const TEST_ID = 0x20;
    const KNOWN_GOOD_ID = 0x1022;
    
    /** packet commands to test firmware **/
    const TEST_ANALOG_COMMAND  = 0x20;
    const SET_DIGITIAL_COMMAND = 0x25;
    const TEST_DIGITAL_COMMAND = 0x26;
    const CONFIG_DAC_COMMAND   = 0x27;
    const SET_DAC_COMMAND      = 0x28;
    
    /* DAC Configuration bytes */
    const DAC_CONFIG_IREF = '0010';
    const DAC_CONFIG_AREF = '0013';

    /** path to openocd for JTAG emulator **/
    private $_openOcdPath = "~/code/HOS/toolchain/bin/openocd";

    /** path to program.cfg for loading test elf file through JTAG **/
    private $_programTestPath = "~/code/HOS/src/003937test/program.cfg";

    /** path to program.cfg for loading boot elf file through JTAG **/
    private $_programBootPath = "~/code/HOS/src/003937boot/program.cfg";

    private $_device;
    private $_goodDevice;

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
    protected function __construct(&$config)
    {
        parent::__construct($config);
        $this->_device = $this->system()->device();
        $this->_goodDevice = $this->system()->device();
        $this->_goodDevice->set("id", self:: KNOWN_GOOD_ID);
        $this->_goodDevice->set("Role","TesterKnownGood");
        $this->_goodDevice->action()->config();
        $this->_goodDevice->action()->loadConfig();
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
        $obj = new EndpointTest($config);
        return $obj;
    }

    /**
    ************************************************************
    *
    *                          M A I N 
    *
    ************************************************************
    *
    * @return null
    */
    public function main()
    {
        $exitTest = false;
        $result;

        do{

            $selection = $this->_mainMenu();

            if (($selection == "A") || ($selection == "a")) {
                $result = $this->_checkGoodEndpoint();
                if ($result = true) {
                    $this->_testMain();
                } else {
                    $exitTest = true;
                }
            } else if (($selection == "B") || ($selection == "b")){
                $this->_cloneMain();
            } else {
                $exitTest = true;
                $this->out("Exit Test Tool");
            }

        } while ($exitTest == false);
    }


    /**
    ************************************************************
    * Display Header and Menu Routine
    *
    * This function displays the test and program tool header
    * and a menu which allows you to exit the program.
    *
    * @return string $choice menu selection
    */
    private function _mainMenu()
    {
        $this->_clearScreen();
        $this->_printHeader();
        $this->out();
        $this->out("A ) Test, Serialize and Program");
        $this->out("B ) Clone and Test");
        $this->out("C ) Exit");
        $this->out();
        $choice = readline("\n\rEnter Choice(A,B or C): ");
        
        return $choice;
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
        $this->out();
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
    * Clear Screen Routine
    * 
    * This function clears screen area by outputting 24 carriage 
    * returns and line feeds.
    *
    * @return void
    * 
    */
    private function _clearScreen()
    {

        system("clear");
    }



    /**
    ************************************************************
    * Print Header Routine
    *
    * The function prints the header box and title.
    *
    * @return void
    *
    */
    private function _printHeader()
    {
        $this->out(str_repeat("*", 50));
       
        $this->out("*                                                *");
        $this->out("*         HUGnetLab Test & Program Tool          *");
        $this->out("*                                                *");

        $this->out(str_repeat("*", 50));

        $this->out();

    }
    
    /**
    ************************************************************
    * Main Clone Routine
    *
    * This is the main routine for cloning an existing endpoint
    * the serial number for the board to be cloned will be written
    * into the new board, but the unique serial number will 
    * remain the same and the board will run through program test.
    * 
    * @return void
    *
    */

    private function _cloneMain()
    {
        
        $this->_clearScreen();
        $this->out("\n\r");
        $this->out("\n\r");
       
        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->out("*                                                *");
        $this->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");


    }


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
    private function _testMain()
    {
        $exitTest = false;
        

        $StartSN = $this->_getSerialNumber();
        $snCounter = 0;
        $programHW = $this->_getHardwareNumber();

        do {
            parent::main();
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
                        $this->out("*****************************************");
                        $this->out("*                                       *");
                        $this->out("*       Load Boot Loader Failed!        *");
                        $this->out("*                                       *");
                        $this->out("*****************************************");
                    }
                } else {
                    $this->out("*********************************************");
                    $this->out("*                                           *");
                    $this->out("*     Board SN & HW Programming Failed      *");
                    $this->out("*             Please verify:                *");
                    $this->out("*   Serial number and hardware partnumber   *");
                    $this->out("*                                           *");
                    $this->out("*********************************************");
                }
            } else {
                $this->_displayFailed();
            }

            $exitTest = $this->_repeatTestMenu();

        } while ($exitTest == false);  


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
        $this->out();
        $this->out(str_repeat("*", 54));
        $this->out("* Loading HUGnetLab Test Firmware "
            ."and Beginning Test *"
        );
        $this->out(str_repeat("*", 54));
        $this->out();
        
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
    *
    */
    private function _checkGoodEndpoint()
    {
        
        

        $Result = $this->_pingEndpoint(self::KNOWN_GOOD_ID);
        if ($Result = true) {
            $this->out("Known Good Endpoint Responding!");
        } else {
            $this->out("Known Good Endpoint Failed to Respond!");
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

        /*if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = self::TEST_ANALOG_COMMAND;
            $dataVal = 01;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
            if ($ReplyData == "01") {
                $Result = true;
            } else {
                $Result = false;
            }
        } else {
            $this->out($_testErrorArray[3]);
        }; */

        if ($Result == true) {
            $Result = $this->_testADC();
        }

        if ($Result == true) {
            $Result =  $this->_testDAC();
        }

       if ($Result == true) {
            $Result = $this->_testDigital();
        }

        return $Result;
    }

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
        $result = true;
        

        /* read known good board for input voltage values */
        $voltageVals = $this->_goodDevice->action()->poll();
        if (is_object($voltageVals)) {
            $channels = $this->_goodDevice->dataChannels();
            $this->out("Date: ".date("Y-m-d H:i:s", $voltageVals->get("Date")));
            for ($i = 0; $i < $channels->count(); $i++) {
                $chan = $channels->dataChannel($i);
                $KnownVolts[$i] = $voltageVals->get("Data".$i);
                $this->out($chan->get("label").": ".$voltageVals->get("Data".$i)." ".html_entity_decode($chan->get("units")));
            }
        } else {
            $this->out("No object returned");
        }

        /* read test board for input voltage values */

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "01";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "02";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "03";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "04";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "05";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "06";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "07";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");


        $idNum = self::TEST_ID;
        $cmdNum = self::TEST_ANALOG_COMMAND;
        $dataVal = "08";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        $myVolts = $this->_convertADCbytes($ReplyData);
        $this->out("Votage :".$myVolts." V");

        /* set up a while loop and a case statement to step 
           through each channel.  Read results and determine
           if the channel passes or fails.  If it passes, 
           continue testing.  If not, then stop testing and
           return failure. */

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
        $newString = substr($inString, 6, 2);
        $newString = $newString.substr($inString, 4, 2);
        $newString = $newString.substr($inString, 2, 2);
        $newString = $newString.substr($inString, 0, 2);
        $newString = "0x".$newString;
        $newVal = 0 + $newString;
        
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
        
        $steps = 1.2 / pow(2,23);
        $volts = $steps * $myValue;
        
        return $volts;
    }



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
        
        $idNum = self::TEST_ID;
        $cmdNum = self::CONFIG_DAC_COMMAND;
        $dataVal = self::DAC_CONFIG_IREF;
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        /* 2 byte response comes back little endian, so the byte */
        /* order is reversed from send data.                     */
        if ($ReplyData == "1000") {
            $Result = true;
        } else {
            $this->out("Failed DAC Config 1");
            $this->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = self::SET_DAC_COMMAND;
            $dataVal = "07ff";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "FF07") {
                $Result = true;
            } else {
                $this->out("Failed DAC Set 1");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }


        }


        /*
        **********************************************
        * This is where we will insert code to read 
        * the known good board on input 1 and check 
        * the DAC output voltage.
        */



        /* config DAC to use 2.5v reference */
        if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = self::CONFIG_DAC_COMMAND;
            $dataVal = self::DAC_CONFIG_AREF;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "1300") {
                $Result = true;
            } else {
                $this->out("Failed DAC Config 2");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }


        }

        /* output 1.2 volts */
        if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = self::SET_DAC_COMMAND;
            $dataVal = "07ff";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            /* 2 byte response comes back little endian, so the byte */
            /* order is reversed from send data.                     */
            if ($ReplyData == "FF07") {
                $Result = true;
            } else {
                $this->out("Failed DAC Set 2");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }


        }

        /*
        **********************************************
        * This is where we will insert code to read 
        * the known good board on input 1 and check 
        * the DAC output voltage.
        */
       
        return $Result;
    }

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

        $idNum = self::TEST_ID;
        $cmdNum = self::SET_DIGITIAL_COMMAND;
        $dataVal = "01";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        if ($ReplyData == "00") {
            $Result = true;
        } else {
            $this->out("Failed to configure GPIO 1");
            $this->out("Digital data is:".$ReplyData);
            $Result = false;
        }

        if ($Result == true) {
            $cmdNum = self::TEST_DIGITAL_COMMAND;
            $dataVal = "0101";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "15") {
                $Result = true;
            } else {
                $this->out("Failed GPIO, Config 1 Test 1");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        }

        if ($Result == true) {
            $cmdNum = self::TEST_DIGITAL_COMMAND;
            $dataVal = "0102";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "0A") {
                $Result = true;
            } else {
                $this->out("Failed GPIO, Config 1 Test 2");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        }


        /*   Setup for test configurations 2  */
        if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = self::SET_DIGITIAL_COMMAND;
            $dataVal = "02";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "1F") {
                $Result = true;
            } else {
                $this->out("Failed to configure GPIO 2");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        }

        if ($Result == true) {
            $cmdNum = self::TEST_DIGITAL_COMMAND;
            $dataVal = "0201";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "15") {
                $Result = true;
            } else {
                $this->out("Failed GPIO, Config 2 Test 1");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        }

        if ($Result == true) {
            $cmdNum = self::TEST_DIGITAL_COMMAND;
            $dataVal = "0202";
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

            if ($ReplyData == "0A") {
                $Result = true;
            } else {
                $this->out("Failed GPIO, Config 2 Test 2");
                $this->out("Digital data is:".$ReplyData);
                $Result = false;
            }
        }
   
        return $Result;
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
        $this->out("\n\r");
        $this->out("\n\r");

        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*      B O A R D   T E S T   P A S S E D !       *");
        $this->out("*                                                *");
        $this->out("**************************************************");

        $this->out("\n\r");
        $this->out("\n\r");

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
        $this->out("\n\r");
        $this->out("\n\r");

        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*      B O A R D   T E S T   F A I L E D !       *");
        $this->out("*                                                *");
        $this->out("**************************************************");

        $this->out("\n\r");
        $this->out("\n\r");

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

        
        $this->out("\n\r");
        $this->out("Enpoint programmed with bootloader!");
        $response = substr($this->_device->encode(), 0, 20);
        $this->out("Serial number and Hardware version");
        $this->out("are :".$response);

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
            $this->_clearScreen();
            $this->out("Enter a hex value for the starting serial number");
            $SNresponse = readline("in the following format- 0xhhhh: ");
            $this->out();
            $this->out("Your starting serial number is: ".$SNresponse);
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

        $dev = $this->system()->device()->getHardwareTypes();
        
        $HWarray = array();

        foreach ($dev as $endp) {
            if ($endp['Param']['ARCH'] == "ADuC7060") {
                $HWarray[] = $endp['HWPartNum'];
            }
        }
        

        foreach ($HWarray as $key => $HWnum) {
            $this->out($key."= ".$HWnum);
        }
        $this->out();

        $HWver = (int)readline(
            "\n\rEnter Hardware version (0 - ". (count($HWarray)-1)."): "
        );
        $this->out();

        $HWnumber = $HWarray[$HWver];
        $this->_device->set("HWPartNum", $HWnumber);
 
 
        $rev = substr($HWnumber, (strlen($HWnumber)-1), 1);
        $this->_HWrev = $rev;

        $myData = sprintf("%02X", ord($rev));
        $this->out("dataVal: ".$myData);
     

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
        $this->out("Serial number and Hardware version");
        $this->out("program data is : ".$response);
        $this->out("Unique Serial Number is : ".$response2);

        if (strlen($response) == 30) {
            $idNum = self::TEST_ID;
            $cmdNum = 0x1c;
            $dataVal = $response;
            $replyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
            $this->out("\nReply Data : ".$replyData);
            $result = true;
        } else {
            $this->out("Invalid program data, programming aborted!");
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
        $dev = $this->system()->device($Sn);
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

        $this->out("Data is ".$DataVal);


        $dev = $this->system()->device($Sn);
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
            $this->out(
                "From: ".$pkt->From()
                ." -> To: ".$pkt->To()
                ."  Command: ".$pkt->Command()
                ."  Type: ".$pkt->Type(),
                2
            );
           
            $data = $pkt->Data();
            if (!empty($data)) {
                $this->out("Data: ".$data, 2);
            }

            $data = $pkt->Reply();
            if (is_null($data)) {
                $this->out("No Reply", 2);
            } else if (!empty($data)) {
                $this->out("Reply Data: ".$data, 2);
            } else {
                $this->out("Empty Reply", 2);
            }
        }

        return $data;
    }



}
?>
