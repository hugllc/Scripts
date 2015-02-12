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
/** Test Display class */
require_once "HUGnetLib/ui/Displays.php";

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
class E104602Test extends \HUGnet\ui\Daemon
{

 
    const TESTER_BOARD1_ID = 0x1051;

    const HEADER_STR    = "Battery Socializer Program & Test Tool";


    private $_fixtureTest;
    private $_device;
    private $_system;
    private $_testerDevice;
    private $_testerDeviceTwo;
    private $_batMainMenu = array(
                            0 => "Test, Program and Serialize",
                            1 => "Troubleshoot",
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
        $this->_device = $this->_system->device();

        $this->_testerDevice = $this->_system->device();
        $this->_testerDevice->set("id", self:: TESTER_BOARD1_ID);
        $this->_testerDevice->action()->config();
        $this->_testerDevice->action()->loadConfig();

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
        $obj = new E104602Test($config);
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
                                $this->_batMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_test1046Main();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshoot1046Main();
            } else {
                $exitTest = true;
                $this->out("Exit Test Tool");
            }

        } while ($exitTest == false);
    }


    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*****************************************************************************/


    /*****************************************************************************/
    /*                    R E L A Y    T A B L E                                 */
    /*                                                                           */
    /*  POWER1   +12V  ---->  K1 - ON                                            */
    /*  POWER1   LOAD  ---->  K1 - OFF                                           */
    /*                                                                           */
    /*  POWER2   +12V  ---->  K2 - ON,  K3 - OFF, K4 - OFF                       */
    /*  POWER2   LOAD  ---->  K2 - OFF, K3 - OFF, K4 - OFF                       */
    /*                                                                           */
    /*  POWER3   +12V  ---->  K2 - ON,  K3 - OFF, K4 - ON                        */
    /*  POWER3   LOAD  ---->  K2 - OFF, K3 - OFF, K4 - ON                        */
    /*                                                                           */
    /*  POWER4   +12V  ---->  K2 - ON,  K3 - ON,  K5 - OFF, K6 - OFF             */
    /*  POWER4   LOAD  ---->  K2 - OFF, K3 - ON,  K5 - OFF, K6 - OFF             */
    /*                                                                           */
    /*  POWER5   +12V  ---->  K2 - ON,  K3 - ON,  K5 - OFF, K6 - ON              */
    /*  POWER5   LOAD  ---->  K2 - OFF, K3 - ON,  K5 - OFF, K6 - ON              */
    /*                                                                           */
    /*  POWER6   +12V  ---->  K2 - ON,  K3 - ON,  K5 - ON,  K7 - OFF             */
    /*  POWER6   LOAD  ---->  K2 - OFF, K3 - ON,  K5 - ON,  K7 - OFF             */
    /*                                                                           */
    /*  POWER7   +12V  ---->  K2 - ON,  K3 - ON,  K5 - ON,  K7 - ON              */
    /*  POWER7   LOAD  ---->  K2 - OFF, K3 - ON,  K5 - ON,  K7 - ON              */
    /*                                                                           */
    /*  BUS      +12V  ---->  K8 - ON                                            */
    /*  BUS      LOAD  ---->  K8 - OFF                                           */
    /*****************************************************************************/


    /******************************************************************************/
    /*                T E S T   S T E P S   C H E C K L I S T                     */
    /*                                                                            */
    /******************************************************************************/
    /**
    * 1. Load HUGnetLab Test Boards and Check Response    
    * 2. Load bootloader code into lower and upper MCU's
    * 3. Load test firmware into lower and upper MCU's
    * 4. Calibrate ADC's and DAC's saving offset and gain values in signature area
    * 5. Check board supply voltages.
    * 6. Test LED's
    * 7. Test Bat Switches for load and voltages
    * 8. Test Power converter output and input
    * 9. Test thermistor inputs.
    * 10.  Load release firmware into lower and upper MCU's
    * 11.  Verify firmware is operating.
    * 12.  Power down device under test.
    */

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
    private function _test1046Main()
    {
        $this->display->clearScreen();
        $this->display->displayHeader("T E S T   N O T   D O N E !");
                
       // $serialNumber = $this->_getSerialNumber();
        //$this->out("Serial Number : ".$serialNumber."\n\r");

        $_relayStatus = 0;

        $Result = $this->_checkTesterEndpoint();

        $Result = $this->_powerONE(1);

        if ($Result) {
            $this->out("Power Up Success!\n\r");
        } else {
            $this->out("Power Up Failed!\n\r");
        }
        $choice = readline("\n\rEnter to Continue: ");



        $Result = $this->_powerONE(0);
        if ($Result) {
            $this->out("Power Down Success!\n\r");
        } else {
            $this->out("Power Down Failed!\n\r");
        }

        $this->display->displayPassed();
        $choice = readline("\n\rEnter to Continue: ");

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
            $this->clearScreen();
            $this->out("Enter a hex value for the starting serial number");
            $SNresponse = readline("in the following format- 0xhhhh: ");
            $this->out("\n\r");
            $this->out("Your starting serial number is: ".$SNresponse);
            $response = readline("Is this correct?(Y/N): ");
        } while (($response <> 'Y') && ($response <> 'y'));

        $SN = hexdec($SNresponse);

        return $SN;
    }


    /**
    **************************************************************
    * Power Up Battery Socializer
    *
    * This function applies power to the battery socializer board
    * by applying +12V to the POWER 1 input through relay K1.
    *
    * @param $state integer - 1 is power up, 0 is power down
    * @return boolean $result
    */
    private function _powerONE($state)
    {

        if ($state == 1) {
            $this->out("Powering Up the Battery Socializer!\n\r");
            $result = $this->_setRelay(1,1);
        } else {

            $this->out("Powering Down the Battery Socializer!\n\r");
            $result = $this->_setRelay(1,0);
        }
        return $result;
    }

    /**
    ***********************************************************
    * POWER2 Input Set Routine
    *
    * This function sets up the Power2 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerTWO($state)
    {

        if (state == 1) {
            $this->_setRelay(4,0);
            $this->_setRelay(3,0);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(4,0);
            $this->_setRelay(3,0);
            $this->_setRelay(2,0);
        }
    }

    /**
    ***********************************************************
    * POWER3 Input Set Routine
    *
    * This function sets up the Power3 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerTHREE($state)
    {

        if (state == 1) {
            $this->_setRelay(4,1);
            $this->_setRelay(3,0);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(4,1);
            $this->_setRelay(3,0);
            $this->_setRelay(2,0);
        }

    }

    /**
    ***********************************************************
    * POWER4 Input Set Routine
    *
    * This function sets up the Power4 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerFOUR($state)
    {

        if (state == 1) {
            $this->_setRelay(6,0);
            $this->_setRelay(5,0);
            $this->_setRelay(3,1);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(6,0);
            $this->_setRelay(5,0);
            $this->_setRelay(3,1);
            $this->_setRelay(2,0);
        }

    }

    /**
    ***********************************************************
    * POWER5 Input Set Routine
    *
    * This function sets up the Power5 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerFIVE($state)
    {

        if (state == 1) {
            $this->_setRelay(6,1);
            $this->_setRelay(5,0);
            $this->_setRelay(3,1);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(6,1);
            $this->_setRelay(5,0);
            $this->_setRelay(3,1);
            $this->_setRelay(2,0);
        }

    }

    /**
    ***********************************************************
    * POWER6 Input Set Routine
    *
    * This function sets up the Power6 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerSIX($state)
    {

        if (state == 1) {
            $this->_setRelay(7,0);
            $this->_setRelay(5,1);
            $this->_setRelay(3,1);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(7,0);
            $this->_setRelay(5,1);
            $this->_setRelay(3,1);
            $this->_setRelay(2,0);
        }

    }

    /**
    ***********************************************************
    * POWER7 Input Set Routine
    *
    * This function sets up the Power7 input to the battery
    * socializer for either a 1 amp load or a +12VDC input.
    *
    * @param $state  integer value to indicate power(1) or 
    *                  load(0) connect to Power input.
    *
    * @return $result boolean value 0=failure, 1=success
    */
    private function _powerSEVEN($state)
    {

        if (state == 1) {
            $this->_setRelay(7,1);
            $this->_setRelay(5,1);
            $this->_setRelay(3,1);
            $this->_setRelay(2,1);
        } else { 
            $this->_setRelay(7,1);
            $this->_setRelay(5,1);
            $this->_setRelay(3,1);
            $this->_setRelay(2,0);
        }

    }

    /**
    **************************************************************
    * Set Power Bus Routine
    *
    * This function sets the BUS input to either a load or +12VDC
    *
    * @param $state integer 1= +12VDC, 0= Load
    *
    * @return $result boolean 0=failure, 1=success
    */
    private function _powerBUS($state)
    {
        if ($state == 1) {
            $this->_setRelay(8,1);
        } else {
            $this->_setRelay(8,0);
        }
    }



    /**
    **************************************************************
    * Set Power Default 
    *
    * This function sets the relays for setting inputs to Power2
    * - Power7 to the default position.  This is setting a load 
    * on the Power2 input.
    */
    private function _powerDefault()
    {
        $this->_setRelay(4,0);
        $this->_setRelay(3,0);
        $this->_setRelay(2,0);
    }


    /**
    **************************************************************
    * Set Power Relay Routine
    *
    * This function takes the relay number and sets it to the 
    * state passed to it.  If the state is 0, then the relay is 
    * turned off and NC contacts close.  If the state is 1, then 
    * the relay is powered and the NO contacts close.
    *
    * @return boolean $Result
    */
    private function _setRelay($relayNum, $state)
    {
        switch($relayNum) {
            case 1:
                $dataVal = "05";
                break;
            case 2:
                $dataVal = "06";
                break;
            case 3: 
                $dataVal = "07";
                break;
            case 4:
                $dataVal = "00";
                break;
            case 5: 
                $dataVal = "01";
                break;
            case 6:
                $dataVal = "02";
                break;
            case 7:
                $dataVal = "03";
                break;
            case 8:
                $dataVal = "04";
                break;
        }

        if ($state == 1) {
            $dataVal .= "01000000";
        } else {
            $dataVal .= "FFFFFFFF";      /* Default to relay off */
        }

        $Result = $this->_sendPacket(self::TESTER_BOARD1_ID, "64", $dataVal);

        return $Result;



    }

    /*****************************************************************************/
    /*                                                                           */
    /*              T R O U B L E S H O O T   R O U T I N E S                    */
    /*                                                                           */
    /*****************************************************************************/

   
    /**
    ************************************************************
    * Main Troubleshoot Routine
    *
    * This is the main routine for testing, serializing and 
    * programming the 003912 endpoint.  
    * 
    * @return void
    *
    */
    private function _troubleshoot1046Main()
    {
        $this->display->clearScreen();
        $this->display->displayHeader("T R O U B L E S H O O T   N O T   D O N E !");


        $choice = readline("\n\rEnter to Continue: ");
    }


    /*****************************************************************************/
    /*                                                                           */
    /*              E N D P O I N T   C O M   R O U T I N E S                    */
    /*                                                                           */
    /*****************************************************************************/


     /**
    ************************************************************
    * Test Endpoint Routine
    *
    * This function runs the tests in the endpoint test firmware
    * and returns the results.
    *
    * @return boolean $testResult   
    */
    private function _checkTesterEndpoint()
    {
        
        

        $Result = $this->_pingEndpoint(self::TESTER_BOARD1_ID);
        if ($Result = true) {
            $this->_system->out("Tester Board 1 Responding!");
        } else {
            $this->_system->out("Tester Board 1 Failed to Respond!");
        }


        return $Result;

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
