<?php
/**
 *
 * PHP Version 5
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2014 Hunt Utilities Group, LLC
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
 * @copyright  2014 Hunt Utilities Group, LLC
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

/**
 * This code tests, serializes and programs HUGnet endpoints with 
 * current firmware.
 *
 * This is an endpoint test class, essentially.  It loads an endpoint without
 * test firmware, runs the tests, writes the serial number and hardware version
 * and then programs the firmware into the endpoint.
 *
 * @category   Libraries
 * @package    HUGnetLib
 * @subpackage UI
 * @author     Jeff Liesmaki <jeffl@hugllc.com>
 * @copyright  2014 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    Release: 0.9.7
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class E003912Test
{
    /** predefined endpoint serial number used in test firmware **/
    const TEST_ID = 0x20;
    
    /** packet commands to test firmware **/
    const TEST_ANALOG_COMMAND  = 0x20;
    const CONFIG_DIGITIAL_COMMAND = 0x25;
    const SET_DIGITAL_COMMAND = 0x26;
    const TEST_DIGITAL_COMMAND = 0x27;

    const ADC_STEP_VAL = 0.0048828125;
    
    const DIGITAL_CONFIG_MASK = 0x7B07;
    const DIGITAL_CONFIG_1 = 0x2105;
    const DIGITAL_CONFIG_2 = 0x5202;

    

    private $_device;
    private $_system;

    private $_devSN;
    private $_devFWN;


    /** ascii string hex value for revision letter **/
    private $_HWrev;

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        $this->_system = &$sys; 
        $this->_device = $this->_system->device();

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
        $obj = new E003912Test($config, $sys);
        return $obj;
    }

    

    /*****************************************************************************/
    /*                                                                           */
    /*         M A I N   M E N U  &  D I S P L A Y   R O U T I N E S             */
    /*                                                                           */
    /*****************************************************************************/


    /**
    ************************************************************
    * Run Test Main Routine
    *
    * This function is the main routine for the 003937 Endpoint
    * test.
    *
    * @return null                      
    *
    */
    public function runTestMain()
    {
        $exitTest = false;
        $result;


        do{

            $selection = $this->_E003912mainMenu();

            if (($selection == "A") || ($selection == "a")) {
                $this->_runTest();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_cloneMain();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_troubleshootMain();
            } else {
                $exitTest = true;
                $this->_system->out("Exit 003912 Test");
            }

        } while ($exitTest == false);
    }

    /**
    ************************************************************
    * Main 003937 Menu Routine
    * 
    * This is the main menu routine for 003912 HUGnet 
    * endpoint.  It displays the menu options, reads the 
    * user input choice and calls the appropriate routine in 
    * response.
    *
    * @return string $choice
    *
    */
    private function _E003912mainMenu()
    {
        EndpointTest::clearScreen();
        $this->_printHeader();
        $this->_system->out("\n\r");
        $this->_system->out("A ) Test, Program and Serialize");
        $this->_system->out("B ) Clone, Test and Program");
        $this->_system->out("C ) Troubleshoot");
        $this->_system->out("D ) Exit");
        $this->_system->out("\n\r");
        $choice = readline("\n\rEnter Choice(A,B,C or D): ");
        
        return $choice;

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
    private function _runTest()
    {
        

        $this->_devSN = $this->_getSerialNumber();


        $Result = $this->_loadTestFirmware();
        if ($Result) {

            $this->_system->out("Test Firmware Loaded!");
            $choice = readline("\n\rHit any key to begin testing.");

            $this->_system->out("\n\r");
            $this->_system->out("********************************************");
            $this->_system->out("*                                          *");
            $this->_system->out("*             T E S T I N G                *");
            $this->_system->out("*                                          *");
            $this->_system->out("********************************************");
            $this->_system->out("\n\r");

            $this->_system->out("********************************************");
            $this->_system->out("*            RUNNING PING TEST             *");
            $this->_system->out("********************************************");
            $Result = $this->_pingEndpoint(self::TEST_ID);
            
            $this->_system->out("\n\r");
            
            $this->_displayPingTestResult($Result);

            $this->_system->out("\n\r");
            

            if ($Result) {
                $Result = $this->_testADC();
                $this->_system->out("\n\r");
            }

            if ($Result) {
                $Result = $this->_loadInitSerialNumber();
                if ($Result) {
                    $this->_system->out("Endpoint SN intialized.");
                    $this->_system->out("Pinging Endpoint ".$this->_devSN." to verify");
                    $Result = $this->_pingEndpoint($this->_devSN);
                    $this->_displayPingTestResult($Result);
                } else {
                    $this->_displayInitProgramFailed();
                }
                $choice = readline("\n\rHit any key to continue.");
            }

            if ($Result) {
                $Result = $this->_loadFirmware();
                $this->_system->out("Firmware Loaded");
                $choice = readLine("\n\rHit any key to verify firmware");
                $Result = $this->_verifyFirmware();
            }
        } else {
            $this->_displayLoadTestFirmwareFailed();
        }

        if ($Result) {
            EndpointTest::displayPassed();
        } else {
            EndpointTest::displayFailed();

        }

        $choice = readline("\n\rHit Enter to Continue: ");
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
        
        EndpointTest::clearScreen();
        $this->_system->out("\n\r");
       
        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*          C L O N E   R O U T I N E             *");
        $this->_system->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");


    }


    /**
    ************************************************************
    * Display Load Test Firmware Failed Routine
    *
    * This function displays the load failed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayLoadTestFirmwareFailed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("*****************************************");
        $this->_system->out("*                                       *");
        $this->_system->out("*     Load Test Firmware Failed!        *");
        $this->_system->out("*                                       *");
        $this->_system->out("*****************************************");

        $this->_system->out("\n\r");
    }

    /**
    ************************************************************
    * Display Board Program Failed Routine
    *
    * This function displays the board serial number and 
    * hardware number programming failed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayInitProgramFailed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("*********************************************");
        $this->_system->out("*                                           *");
        $this->_system->out("*   Init Serial Number Programming Failed   *");
        $this->_system->out("*                                           *");
        $this->_system->out("*********************************************");

        $this->_system->out("\n\r");
    }

    /**
    ************************************************************
    * Display Ping Test Results
    *
    * This function displays passed or failed result of the 
    * endpoint ping test.
    *
    * @param boolean $result test failed or passed 
    *
    * @return void
    */
    private function _displayPingTestResult($result)
    {
        $this->_system->out("********************************************");
        if ($result) {
            $this->_system->out("*            PING TEST PASSED!             *");
        } else {
            $this->_system->out("*            PING TEST FAILED!             *");
        }
        $this->_system->out("********************************************");
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
        $this->_system->out(str_repeat("*", 60));
       
        $this->_system->out("*                                                          *");
        $this->_system->out("*        HUGnetLab 003912 Test & Program Tool              *");
        $this->_system->out("*                                                          *");

        $this->_system->out(str_repeat("*", 60));
    }

    /*****************************************************************************/
    /*                                                                           */
    /*                T R O U B L E S H O O T   R O U T I N E S                  */
    /*                                                                           */
    /*****************************************************************************/

    /**
    ************************************************************
    * Main Troubleshoot Routine
    *
    * This is the main routine for troubleshooting an existing 
    * endpoint.  It will have the option of single stepping 
    * through the tests or looping on a specific test.
    * 
    * @return void
    *
    */
    private function _troubleshootMain()
    {
        $exitTest = false;
        $result;

        do{

            $selection = $this->_troubleshootMenu();

            if (($selection == "A") || ($selection == "a")) {
                $this->_troubleshootPing();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshootAnalog();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting");
            }

        } while ($exitTest == false);
    }

    /**
    ************************************************************
    * Troubleshoot 003912 Menu Routine
    * 
    * This is the main menu routine for 003937 HUGnetLab 
    * endpoint.  It displays the menu options, reads the 
    * user input choice and calls the appropriate routine in 
    * response.
    *
    * @return string $choice
    *
    */
    private function _troubleshootMenu()
    {
        EndpointTest::clearScreen();
        $this->_printTroubleshootHeader();
        $this->_system->out("\n\r");
        $this->_system->out("A ) Ping Test");
        $this->_system->out("B ) Analog Tests");
        $this->_system->out("C ) Exit");
        $this->_system->out("\n\r");
        $choice = readline("\n\rEnter Choice(A-C): ");
        
        return $choice;

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
    private function _printTroubleshootHeader()
    {
        $this->_system->out(str_repeat("*", 60));
       
        $this->_system->out("*                                                          *");
        $this->_system->out("*           Troubleshoot HUGnetLab 003928                  *");
        $this->_system->out("*                                                          *");

        $this->_system->out(str_repeat("*", 60));
    }

    /**
    *************************************************************
    * Troubleshoot Endpoint Ping Routine
    *
    * This routine will repeat the ping command allowing the 
    * user to troubleshoot the communications problem with the 
    * endpoint.
    *
    * @return void
    */
    private function _troubleshootPing()
    {
        $Done = false;
        EndpointTest::clearScreen();
        $this->_system->out("Repeat Ping Command");

        do {
            $repeatNum = readline("\n\rEnter number of times to repeat: ");

            if (is_numeric($repeatNum)) {
                for ($i = 0; $i < $repeatNum; $i++) {
                    $Result = $this->_pingEndpoint(self::TEST_ID);
                    if ($Result) {
                        $this->_system->out("Ping ".($i+1)." Passed!");
                    } else {
                        $this->_system->out("Ping ".($i+1)." Failed!");
                    }
                }
                $Done = true;
            } else {
                $this->_system->out("Invalid repeat number!");
                $choice = readline("\n\rEnter C to continue or any other key to exit: ");
                if ($choice == 'C' or $choice == 'c') {
                    $Done = false;
                } else {
                    $Done = true;
                }
            }
        } while (!$Done);

    }

    /**
    ************************************************************
    * Troubleshoot Analog Tests Routine
    *
    * This is the main routine for troubleshooting the analog
    * inputs on an existing endpoint.  It will have the option 
    * of single stepping through the tests or looping on a 
    * specific test.
    * 
    * @return void
    *
    */
    private function _troubleshootAnalog()
    {

        $Done = false;
        EndpointTest::clearScreen();
        $this->_system->out("Read Analog Channel Command");
        $this->_system->out("\n\r");
       

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*     T R O U B L E S H O O T   A N A L O G      *");
        $this->_system->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        do {
            $inputNum = readline("\n\rEnter the adc number of the channel to read: ");

            $Done = true;
        } while (!$Done);

        $choice = readline("\n\rHit Enter To Continue: ");
     }


    /**
    *************************************************************
    * Display Firmware Menu Routine
    * 
    * This function gathers available firmware releases from the 
    * Downloads directory and displays them in a menu so the user
    * can select the firmware version to install.
    *
    * @return $choice
    */
    private function _firmwareMenu()
    {
        $output = array();

 
        $Prog = "ls ~/Downloads/003912*";
        exec($Prog,$output, $return);


        $fCount = count($output);

        for ($i = 0; $i < $fCount; $i++) {
            $output[$i] = strrchr($output[$i],'/');
            $output[$i] = substr($output[$i], 1, strlen($output[$i]));
        }

        do {
            $this->_system->out("");
            $this->_system->out("Choose Firmware for Installation");
            $this->_system->out("");
            for ($i=0; $i< $fCount; $i++) {
                $this->_system->out(($i+1).") ".$output[$i]);
                $this->_system->out("");
            }

            $choice = readline("\n\rEnter Menu Number : ");

            $cNum = intval($choice);

            $firmWare = $output[$cNum-1];

            $this->_system->out("Your firmware selection is : ".$firmWare);
            $response = readline("Is this correct?(Y/N): ");
        } while (($response <> 'Y') && ($response <> 'y'));

        $this->_devFWN = $firmWare;

        return $firmWare;

    }


    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*****************************************************************************/
  

    /**
    ************************************************************
    * Load test firmware routine
    *
    * This function loads the 003912 endpoint with the test 
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

        $this->_system->out("\n\r");
        $this->_system->out("********************************************");
        $this->_system->out("*                                          *");
        $this->_system->out("*         Loading Test Firmware            *");
        $this->_system->out("*                                          *");
        $this->_system->out("********************************************");
        $this->_system->out("\n\r");


        $Prog = "make -C ~/code/HOS 003912test-install SN=0x0000000020";
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
            EndpointTest::clearScreen();
            $this->_system->out("Enter the hex value of the board serial number");
            $SNresponse = readline("in the following format- 0xhhhh: ");
            $this->_system->out("\n\r");
            $this->_system->out("Your board serial number is: ".$SNresponse);
            $response = readline("Is this correct?(Y/N): ");
        } while (($response <> 'Y') && ($response <> 'y'));

        $SN = hexdec($SNresponse);

        return $SN;
    }


    /**
    ************************************************************
    * Init Board With Permanent Serial Number
    *
    * This function loads init firmware and board serial number.
    * A ping with the permanent serial number will be used to 
    * to test initialization.
    *
    * @return $result boolean true for pass and false for fail.
    */
    private function _loadInitSerialNumber()
    {
        $SNstring = sprintf("%02X",$this->_devSN);


       while( strlen($SNstring) < 10) {
            $SNstring = "0".$SNstring;
        }

        $SN = "SN=0x".$SNstring;

        

        $Prog = "make -C ~/code/HOS 003912-install ".$SN;
        exec($Prog, $output, $return);

        if ($return == 0) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    /**
    **************************************************************
    * Load Current Firmware Routine
    *
    * This routine loads the current firmware on to the current
    * board using the serial number given in the start of the 
    * test.  
    *
    * @return $result - boolean
    */
    private function _loadFirmware()
    {
        $result = true;
        $SN = sprintf("%02X",$this->_devSN);

        $newFirmware = $this->_firmwareMenu();

        $Prog = "~/code/Scripts/bin/./hugnet_load -i ".$SN." -D ~/Downloads/".$newFirmware;

        exec($Prog, $output, $return);

        if ($return == 0) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;

    }

    /**
    ****************************************************************
    * Verify Firmware Load Routine
    *
    * This function does a readconfig on the installed firmware and
    * uses the response data to verify endpoint serial number, 
    * hardware part number, and firmware part number.
    *
    * @return boolean $result - 0 for failure, 1 for success
    */
    private function _verifyFirmware()
    {
        
        $result = false;
        $cmdNum = 0x5C;
        $dataVal = "0";

        $this->_system->out("Confirming Firmware Loaded");
        $ResponseData = $this->_sendpacket($this->_devSN,$cmdNum,$dataVal);

        $SNstring = sprintf("%02X",$this->_devSN);
       while( strlen($SNstring) < 10) {
            $SNstring = "0".$SNstring;
        }
        
        /* check the serial number */
        $SNfirmware = substr($ResponseData, 0, 10);
        if ($SNfirmware == $SNstring) {
            $result = true;
        } else {
            $this->_system->out("Serial Number Entered : ".$SNstring);
            $this->_system->out("Serial Number Response: ".$SNfirmware);
            $result = false;
        }

        if ($result) {
            /* check the hardware part number */
            $HWpartNum = substr($ResponseData, 10, 10);
            if ($HWpartNum == "0039120243") {
                $result = true;
            } else {
                $this->_system->out("Hardware Part Number Expected :  0039120243");
                $this->_system->out("Hardware Part Number Response : ".$HWpartNum);
                $result = false;
            }

            if ($result) {
                /* check the firmware part number */
                $FWpartNum = substr($ResponseData, 20, 16);
                $part1 = substr($FWpartNum,0,8);

                $letter = substr($FWpartNum,8,2);
                $hlet = "0x".$letter;
                $decNum = hexdec($hlet);
                $part1 .= chr($decNum)."-";

                $version1 = substr($FWpartNum, 10, 2);
                $hlet = "0x".$version1;
                $decNum = hexdec($hlet);
                $part1 .= $decNum.".";

                $version2 = substr($FWpartNum, 12,2);
                $hlet = "0x".$version2;
                $decNum = hexdec($hlet);
                $part1 .= $decNum.".";
      
                $version3 = substr($FWpartNum, 14,2);
                $hlet = "0x".$version3;
                $decNum = hexdec($hlet);
                $part1 .= $decNum.".gz";

                $FWpart = substr($HWpartNum, 0, 6)."-".$part1;

                if ($FWpart == $this->_devFWN) {
                    $result = true;
                } else {
                    $this->_system->out("Firmware version selected : ".$this->_devFWN);
                    $this->_system->out("Firmware version response : ".$FWpart);
                    $result = false;
                }
            }
        }


        return $result;
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
        $this->_system->out("********************************************");
        $this->_system->out("*          RUNNING ANALOG TESTS            *");
        $this->_system->out("********************************************");
       
        $goodVolts = array();
        for ($i = 0; $i < 9; $i++) {
            $goodVolts[$i] = (($i + 1) * 0.50);
        }
        $adcInput = 0;
        $result = true;

        while (($result == true) and ($adcInput < 9)) {
            $myVolts = $this->_readADCinput($adcInput);
            $result = $this->_testADCinput($adcInput, $myVolts, $goodVolts);
            $adcInput++;
        }
        $this->_system->out("\n\r");
        $this->_system->out("********************************************");
        if ($result) {
            $this->_system->out("*         ANALOG TESTS PASSED!             *");
        } else {
            $this->_system->out("*         ANALOG TEST FAILED!              *");
        }
        $this->_system->out("********************************************");
            
        return $result;
    }

    /**
    ************************************************************
    * Test ADC Input Routine
    *
    * This function tests the input voltage read from the DUT
    * and compares it to the known voltage reading.
    * If the test voltage is within limits, the test passes
    * and a true result is returned.
    *
    * @param int   $inputNum    adc input number 
    * @param float $inVolts     voltage reading from input
    * @param array $kVolts      known voltages array
    *
    * @return boolean true or false test result
    *
    */
    private function _testADCinput($inputNum, $inVolts, $kVolts = array())
    {
        $hiTol = 1.05;
        $loTol = 0.95;
        
 
        $this->_system->out("Known Voltage :".$kVolts[$inputNum]."V  Test Voltage :".$inVolts."V");
        if (($inVolts >= ($kVolts[$inputNum] * $loTol)) and ($inVolts <= ($kVolts[$inputNum] * $hiTol))) {
            $result = true;
            $this->_system->out("ADC Input ".($inputNum+1)." Passed!");
        } else {
            $result = false;
            $this->_system->out("ADC Input ".($inputNum+1)." Failed!");
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
    * @param int $inputNum  adc input to read
    *
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
        print "dataVal is :".$dataVal."\n\r";
        print "ReplyData is :".$ReplyData."\n\r";

        $readVolts = $this->_convertReplyData($ReplyData);
        $readVolts = $readVolts * self::ADC_STEP_VAL;

        return $readVolts;
    }

    /**
    ************************************************************
    * Convert Reply Data String
    *
    * This function changes the bytes in the input string
    * from little endian to big endian so the hex string
    * can be converted to an integer.
    *
    * @param string &$inString  2 byte, hex string
    *
    *
    * @return int $result of conversion
    *
    */
    private function _convertReplyData(&$inString)
    {
        $newString = substr($inString, 2, 2);
        $newString = $newString.substr($inString, 0, 2);
        $newString = "0x".$newString;
        $newVal = 0 + $newString;
        
        return $newVal;

    }
    


    /*****************************************************************************/
    /*                                                                           */
    /*                    H U G N E T   R O U T I N E S                          */
    /*                                                                           */
    /*****************************************************************************/




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

}
?>
