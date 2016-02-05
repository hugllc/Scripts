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
use \HUGnetLib as HUGnetLib;

/** This is our base class */
require_once "HUGnetLib/ui/Daemon.php";
/** This is our units class */
require_once "HUGnetLib/devices/inputTable/Driver.php";
/** This is needed */
require_once "HUGnetLib/devices/inputTable/DriverAVR.php";
/** Displays class */
require_once "HUGnetLib/ui/Displays.php";
/** Test Class */
require_once "E104603Test.php";

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
class E104607TroubleShoot extends E104603Test
{

    private $_fixtureTest;
    private $_system;
    private $_device;
    private $_evalDevice;

    const HEADER_STR     = "Battery Coach Troubleshoot Tester & Program Tool";
    const RELAY_TST_STR  = "Relay Troubleshooting Tool";
    
    private $_testerTrblShtMainMenu = array(
                                0 => "Relay Test",
                                1 => "Fault Signals",
                                2 => "Troubleshoot 3",
                                );
   
   private $_relayTestMenu = array(
                        0 => "Troubleshoot Single Relay",
                        1 => "Run All Relay Test",
                        );
                                
    public $display;

    

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        parent::__construct($config, $sys);
        $this->_system = &$sys; 
        $this->_device = $this->_system->device();
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
        $obj = new E104607TroubleShoot($config, $sys);
        return $obj;
    }

    /**
    *****************************************************************************
    *
    *                  T R O U B L E S H O O T    M A I N 
    *
    *****************************************************************************
    *
    * It would be nice to have a test fixture ID test to verify
    * that the fixture matches the menu selection.
    * 
    * @return null
    */
    public function runTrblshtTesterMain()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_testerTrblShtMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_trblshtRelays();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_trblshtFaultSignals();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_trblsht3();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshoot Tester Tool");
            }

        } while ($exitTest == false);

        $choice = readline("\n\rHit Enter to Continue: ");

    }




    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*         T R O U B L E S H O O T    T E S T E R   R O U T I N E S          */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

 


    /**
    ************************************************************
    * Troubleshoot 1 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery coach functional tester.
    *
    */
    private function _trblshtRelays()
    {
        $exitTest = false;
        
        do {
            $this->display->clearScreen();

            $this->_system->out("");
            $this->_system->out("= RELAY TROUBLESHOOTING IS TO BE DONE WITH NO =");
            $this->_system->out("= BATTERY SOCIALIZER BOARD IN THE TEST BED!   =");
            $this->_system->out("");
            
            $selection = $this->display->displayMenu(self::RELAY_TST_STR, 
                            $this->_relayTestMenu);
            
            if (($selection == 'A') || ($selection == 'a')) {
                $this->_troubleshootSingleRelay();
            } else if (($selection == 'B') || ($selection == 'b')) {
                $this->_relayTest();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Relay Test Routine");
            }
        } while (!$exitTest);
        
   
        
    }

    /**
    ************************************************************
    * Troubleshoot 2 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery coach functional tester.
    *
    */
    private function _trblshtFaultSignals()
    {
        $this->display->clearScreen();

        $this->_system->out("");
        $this->_system->out("= INSTALL KNOWN GOOD BATTERY SOCIALIZER BOARD =");
        $this->_system->out("= IN THE TEST BED FOR TROUBLESHOOTING TESTER  =");
        $this->_system->out("= FAULT SIGNAL DRIVERS.                       =");
        $this->_system->out("");

        $this->_system->out("****   NOT DONE!  *****");
        $choice = readline("\n\rHit Enter to Exit: ");
        
    }

    /**
    ************************************************************
    * Troubleshoot 3 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery coach functional tester.
    *
    */
    private function _trblsht3()
    {
        $this->_system->out("\n\r  Hey #3 Not Done!");
        $this->_system->out("********************\n\r");
        
        $choice = readline("\n\rHit Enter to Exit: ");
        
    }

    /**
    ************************************************************
    * Troubleshoot a single relay Routine
    *
    * This function allows the troubleshooting of a single relay
    * drive and/or contact closure.
    */
    private function _troubleshootSingleRelay()
    {
        $this->_system->out("\n\r  Entering Single Relay Test Routine");
        $this->_system->out("*************************************\n\r");
        
        $testRelay = readline("Enter Relay Number (1-8) to Test: ");
        
        $this->_setRelay($testRelay, self::ON);
        
        $this->_system->out("\n\rRelay K".$testRelay." should be closed.");
        $this->_system->out("and the red LED on the relay board ON.\n\r");
        $this->_system->out("Measure driver voltage and NO contacts.");
        $choice = readline("\n\rHit Enter to Continue:");
        
        $this->_setRelay($testRelay, self::OFF);
        
        $this->_system->out("\n\rRelay K".$testRelay." should be open.");
        $this->_system->out("and the red LED on the relay board OFF.\n\r");
        $this->_system->out("Measure driver voltage and NC contacts.");
        $choice = readline("\n\rHit Enter to Continue:");
       
    
    
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
        $this->_system->out("\n\r  Entering Relay Test Routine");
        $this->_system->out("*******************************\n\r");
        for ($relayNum = 1; $relayNum < 9; $relayNum++) {
            $this->_system->out("Testing Relay K".$relayNum);
            $this->_runSingleRelay($relayNum);
        }
        
        $this->_system->out("Relay Test Complete!");
        $choice = readline("\n\rHit Enter to Exit: ");
    }

    /**
    ************************************************************
    * Run Relay Routine
    *
    * This function closes the selected relay for 1 second
    * and then opens the relay.  The purpose is to test the
    * relay driver.
    */
    private function _runSingleRelay($relayNum)
    {
        $idNum = self::EVAL_BOARD_ID;
        
        switch ($relayNum) {
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
        
        /* close relay */
        $cmdNum = self::SET_DIGITAL_COMMAND;
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        /* wait 1 second */
        sleep(1);
        
        /* open relay */
        $cmdNum = self::CLR_DIGITAL_COMMAND;
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        /*wait 1 second */
        sleep(1);
        
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
