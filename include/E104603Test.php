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
    const UUT_BOARD_ID = 0x8013;

    const TEST_ANALOG_COMMAND  = 0x20;
    const SET_DIGITAL_COMMAND = 0x26;
    const CLR_DIGITAL_COMMAND = 0x27;

    const HEADER_STR    = "Battery Socializer Test & Program Tool";
    
    const VCC_PORT = 0;
    const VBUS_PORT = 1;
    const P2_PORT = 2;
    const P1_PORT = 3;
    const SW3V_PORT = 4;
    
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
            $result = $this->_powerDUTtest();
            if ($result) {
                $result = $this->_checkUUTBoard();
                /* next step is to load DUT test firmware */
                /* next test is to receive powerup packet */
                $this->display->displayPassed();
            } else {
                $this->display->displayFailed();
            }
        }

        $result = $this->_powerDUT(self::OFF);

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
    private function _powerDUTtest()
    {
       $result = $this->_powerDUT(self::ON);

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
    private function _powerDUT($state)
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
    * Read Board Vcc Voltage
    * 
    * This function reads the Battery Socializer +3.3VDC supply
    * voltage and returns the value.
    * 
    * @return $volts a floating point value for Vcc 
    */
    private function _readVCC()
    {
    
        $rawVal = $this->_readADCinput(self::VCC_PORT);
      
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
    private function _readBusVolt()
    {
    
	$rawVal = $this->_readADCinput(self::VBUS_PORT);
      
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
    private function _readP2Volt()
    {
    
        $rawVal = $this->_readADCinput(self::P2_PORT);
      
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
    private function _readP1Volt()
    {
    
        $rawVal = $this->_readADCinput(self::P1_PORT);
      
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
    private function _readSW3()
    {
    
        $rawVal = $this->_readADCinput(self::SW3V_PORT);
      
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
    private function _readADCinput($inputNum)
    {
        $idNum = self::EVAL_BOARD_ID;
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
        if ($Result = true) {
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
        if ($Result = true) {
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
        //var_dump($result);
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
