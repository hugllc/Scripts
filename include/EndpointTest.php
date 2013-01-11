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

    /** digital I/O test response bytes **/
    const DIG_OUT1_P1_RESPONSE_BYTE0 = 0x05;
    const DIG_OUT1_P1_RESPONSE_BYTE1 = 0x08;
    const DIG_OUT1_P2_RESPONSE_BYTE0 = 0x0A;
    const DIG_OUT1_P2_RESPONSE_BYTE1 = 0x40;

    const DIG_OUT2_P1_RESPONSE_BYTE0 = 0x00;
    const DIG_OUT2_P1_RESPONSE_BYTE1 = 0x20;
    const DIG_OUT2_P1_RESPONSE_BYTE2 = 0x03;
    const DIG_OUT2_P2_RESPONSE_BYTE0 = 0x10;
    const DIG_OUT2_P2_RESPONSE_BYTE1 = 0x50;
    const DIG_OUT2_P2_RESPONSE_BYTE2 = 0x00;


    /** path to openocd for JTAG emulator **/
    private $_openOcdPath = "~/code/HOS/toolchain/bin/openocd";

    /** path to program.cfg for loading test elf file through JTAG **/
    private $_programTestPath = "~/code/HOS/src/003937test/program.cfg";

    /** path to program.cfg for loading boot elf file through JTAG **/
    private $_programBootPath = "~/code/HOS/src/003937boot/program.cfg";

    private $_device;

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

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config)
    {
        parent::__construct($config);
        $this->_device = $this->system()->device();
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

        $selection = $this->_mainMenu();

        if (($selection == "A") || ($selection == "a")) {
            $this->_testMain();
        } else {
            $this->out("Exit Test Tool");
        }

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
        $this->out("B ) Exit");
        $this->out();
        $choice = readline("\n\rEnter Choice(A or B): ");
        
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
        $this->_printHeader();
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
                $this->out("Board Test passed!");

                $retVal = $this->_writeSerialNumAndHardwareVer();
                
                if ($retVal == true) {
                    $snCounter++;
                    $this->_loadBootLoader();
                } else {
                    $this->out("     === Board SN & HW Programming Failed ===");
                    $this->out("              Please verify:                 ");
                    $this->out("       Serial number and hardware partnumber.");
                }
            } else {
                $this->out("       === Board Test Failed ===");
                $this->out("Please repair board before retesting.");
            }

            $exitTest = $this->_repeatTestMenu();

        } while ($exitTest == false);

        $this->out("Test and Program End!");

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
        $this->out(
            "==== Loading HUGnetLab Test Firmware"
            ."and Beginning Test ===="
        );
        
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
    private function _testEndpoint()
    {
            
        $this->out("Hey its working!");
   
        $Result = $this->_pingEndpoint(self::TEST_ID);

        if ($Result == true) {
            $idNum = self::TEST_ID;
            $cmdNum = 0x20;
            $dataVal = 0;
            $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
            if ($ReplyData == "01") {
                $Result = true;
            } else {
                $Result = false;
            }
        } else {
            $this->out($_testErrorArray[2]);
        };

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
        /* set power supply output based on board version */
        /* set up a while loop and a case statement to step 
           through each channel.  Read results and determine
           if the channel passes or fails.  If it passes, 
           continue testing.  If not, then stop testing and
           return failure. */

        return $result;
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
        $result = true;
        /* config DAC to use internal reference */
        /* output half of full scale */
        /* read good board input 1 and check results */
        /* config DAC to use 2.5v reference */
        /* output 1.2 volts */
        /* read good board input 1 and check results */
       
        return $result;
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
        $result = true;
        /* configure P2.1, P1.4-1.6, P2.0 & P0.4 as outputs */
        /* and configure P0.0-0.3, P1.2 & P1.3 as inputs */
        /* set output 1 pattern 1 - 
            P2.1 - high  to input P1.3
            P1.6 - low   to input P0.3
            P1.5 - high  to input P0.2
            P1.4 - low   to input P0.1
            P2.0 - high  to input P0.0
            P0.4 - low   to input P1.2
           
                                  and read inputs.  
          reaing the inputs will require the return of 
          2 bytes of data.  They will be ordered in the 
          return data buffer as P0 byte 0, and P1, byte 1.
          The response should be 0x05 and 0x08 respectively. */
        
        /* set output 1 pattern 2 and read inputs 
            P2.1 - low    to input P1.3
            P1.6 - high   to input P0.3
            P1.5 - low    to input P0.2
            P1.4 - high   to input P0.1
            P2.0 - low    to input P0.0
            P0.4 - high   to input P1.2 
                                   and read inputs.
           reading the inputs will require the return of 
           2 bytes of data.  They will be odered in them
           return data buffer as P0 byte 0 and P1, byte 1.
           The response should be 0x0A and 0x40 respectively. */

        /* configure P2.1, P1.4-1.6, P2.0 & P0.4 as inputs */
        /* and configure P0.0-0.3, P1.2 & 1.3 as inputs */
        /* set output 2 pattern 1 
            P1.3 - high  to input P2.1 
            P0.3 - low   to input P1.6
            P0.2 - high  to input P1.5
            P0.1 - low   to input P1.4
            P0.0 - high  to input P2.0
            P1.2 - low   to input P0.4

           and read inputs.  Reading the inputs will 
           require the return of 3 bytes of data.  They will 
           be ordered in the return data buffer as P0=byte 0, 
           P1=byte 1 and P2 = byte 2.  The response should be
           0x00, 0x20 and 0x03 respectively.  */
        /* set output 2 pattern 2 

            P1.3 - low   to input P2.1 
            P0.3 - high  to input P1.6
            P0.2 - low   to input P1.5
            P0.1 - high  to input P1.4
            P0.0 - low   to input P2.0
            P1.2 - high  to input P0.4
           and read inputs. Reading the inputs will 
           require the return of 3 bytes of data.  They will 
           be ordered in the return data buffer as P0=byte 0, 
           P1=byte 1 and P2 = byte 2.  The response should be
           0x10, 0x50 and 0x00 respectively. */
    
        return $result;
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

        $response = substr($this->_device->encode(), 0, 20);
        $this->out("Serial number and Hardware version");
        $this->out("program data is : ".$response);

        if (strlen($response) == 20) {
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
