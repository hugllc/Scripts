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
class E104602Test extends \HUGnet\ui\Daemon
{

 
    const TESTER_BOARD1_ID = 0x1051;

    private $_fixtureTest;
    private $_device;
    private $_system;
    private $_testerDevice;
    private $_testerDeviceTwo;


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

            $selection = $this->_mainMenu();

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
    /*            D I P L A Y   A N D   M E N U   R O U T I N E S                */
    /*                                                                           */
    /*****************************************************************************/


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
        //$this->clearScreen();
        $this->_printHeader();
        $this->out();
        $this->out("A ) Test, Program and Serialize");
        $this->out("B ) Troubleshoot");
        $this->out("C ) Exit");
        $this->out();
        $choice = readline("\n\rEnter Choice(A,B or C): ");
        
        return $choice;
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
    public static function clearScreen()
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
        $this->out(str_repeat("*", 60));
       
        $this->out("*                                                          *");
        $this->out("*        Battery Socializer Program & Test Tool            *");
        $this->out("*                                                          *");

        $this->out(str_repeat("*", 60));

        $this->out();

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
    public function displayPassed()
    {
        echo ("\n\r");
        echo ("\n\r");

        echo ("**************************************************\n\r");
        echo ("*                                                *\n\r");
        echo ("*      B O A R D   T E S T   P A S S E D !       *\n\r");
        echo ("*                                                *\n\r");
        echo ("**************************************************\n\r");

        echo ("\n\r");
        echo ("\n\r");

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
    public function displayFailed()
    {
        echo ("\n\r");
        echo ("\n\r");

        echo ("**************************************************\n\r");
        echo ("*                                                *\n\r");
        echo ("*      B O A R D   T E S T   F A I L E D !       *\n\r");
        echo ("*                                                *\n\r");
        echo ("**************************************************\n\r");

        echo ("\n\r");
        echo ("\n\r");

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
    private function _test1046Main()
    {
        $this->clearScreen();

        echo ("\n\r");
        echo ("\n\r");

        echo ("***********************************************\n\r");
        echo ("*                                             *\n\r");
        echo ("*        T E S T   N O T   D O N E !          *\n\r");
        echo ("*                                             *\n\r");
        echo ("***********************************************\n\r");
        echo ("\n\r");
                
       // $serialNumber = $this->_getSerialNumber();
        //$this->out("Serial Number : ".$serialNumber."\n\r");

        $Result = $this->_checkTesterEndpoint();

        $Result = $this->_powerDUT(1);

        if ($Result) {
            $this->out("Power Up Success!\n\r");
        } else {
            $this->out("Power Up Failed!\n\r");
        }
        $choice = readline("\n\rEnter to Continue: ");



        $Result = $this->_powerDUT(0);
        if ($Result) {
            $this->out("Power Down Success!\n\r");
        } else {
            $this->out("Power Down Failed!\n\r");
        }


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
    private function _powerDUT($state)
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
            $dataVal .= "FFFFFFFF";
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
        $this->clearScreen();

        echo ("\n\r");
        echo ("\n\r");

        echo ("***********************************************\n\r");
        echo ("*                                             *\n\r");
        echo ("* T R O U B L E S H O O T   N O T   D O N E ! *\n\r");
        echo ("*                                             *\n\r");
        echo ("***********************************************\n\r");


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
