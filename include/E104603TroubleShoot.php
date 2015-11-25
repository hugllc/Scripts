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
class E104603TroubleShoot extends E104603Test
{

    private $_fixtureTest;
    private $_system;
    private $_device;
    private $_evalDevice;

    const HEADER_STR    = "Battery Socializer Troubleshoot & Program Tool";
    
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

    

    /**
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        $this->_system = &$sys; 
        $this->_device = $this->_system->device();
        $this->_evalDevice = $this->_system->device();
        $this->_evalDevice->set("id", self:: EVAL_BOARD_ID);
        $this->_evalDevice->set("Role","TesterEvalBoard");

        $this->_display = \HUGnet\ui\Displays::factory($config);
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
        $obj = new E104603TroubleShoot($config, $sys);
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
    public function runTroubleshootMain()
    {
        $exitTest = false;
        $result;

        do{
        do{
            $this->_display->clearScreen();
            $selection = $this->_display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_troubleshoot1();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshoot2();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_troubleshoot3();
            } else if (($selection == "D") || ($selection == "d")){
                $this->_troubleshoot4();
            } else if (($selection == "E") || ($selection == "e")){
                $this->_troubleshoot5();
            } else if (($selection == "F") || ($selection == "f")){
                $this->_troubleshoot6();
            } else if (($selection == "G") || ($selection == "g")){
                $this->_troubleshoot7();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting Tool");
            }

        } while ($exitTest == false);

        $choice = readline("\n\rHit Enter to Continue: ");

        } while ($exitTest == false);
    }




    /*****************************************************************************/
    /*                                                                           */
    /*                                                                           */
    /*             T R O U B L E S H O O T   R O U T I N E S                     */
    /*                                                                           */
    /*                                                                           */
    /*****************************************************************************/

 


    /**
    ************************************************************
    * Troubleshoot 1 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot1()
    {
        $this->out("Powering up UUT!");
        $this->_powerUUT(self::ON);
        $this->out("Sleeping");
        sleep(2);
        $result = self::PASS;

        $this->_DB_DATA["id"] = hexdec("0x8012");
        $this->_DB_DATA["HWPartNum"] = "10460301A";
        $this->_DB_DATA["FWPartNum"] = "00393801C";
        $this->_DB_DATA["FWVersion"] = "0.3.0";
        $this->_DB_DATA["BtldrVersion"] = "0.3.0";
        $this->_DB_DATA["MicroSN"] = "0011223344556677889933";
        $this->_DB_DATA["TestDate"] = time();
        $this->_DB_DATA["TestResult"] = self::PASS;
        $this->_DB_DATA["TestData"] = $this->_TEST_DATA;
        $this->_DB_DATA["TestsFailed"] = $this->_TEST_FAIL;


        $db = $this->_system->table("DeviceTests");
        $db->fromArray($this->_DB_DATA);
        $db->insertRow();

        $this->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
        
        $this->out("************************");
        $this->out("*    Troubleshoot 1    *");
        $this->out("*       Not Done!      *");
        $this->out("************************");
        $this->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }


    
    /**
    ************************************************************
    * Troubleshoot 2 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot2()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 2    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }

    /**
    ************************************************************
    * Troubleshoot 3 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot3()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 3    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }

    /**
    ************************************************************
    * Troubleshoot 4 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot4()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 4    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }

    /**
    ************************************************************
    * Troubleshoot 5 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot5()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 5    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }

    /**
    ************************************************************
    * Troubleshoot 6 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot6()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 6    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
        $choice = readline("\n\rHit Enter to Continue: ");
    }

    /**
    ************************************************************
    * Troubleshoot 7 routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _troubleshoot7()
    {
    
        $this->_system->out("************************");
        $this->_system->out("*    Troubleshoot 7    *");
        $this->_system->out("*       Not Done!      *");
        $this->_system->out("************************");
        $this->_system->out("");
        
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
        
        //$this->_ENDPT_SN = 0x8012;
        //$this->_writePowerTable();
        
        /* next set the channel on */
        $this->_runApplicationTest();

        $choice = readline("\n\rHit Enter to Continue: ");


        $this->_powerUUT(self::OFF);
        $this->_clearTester();
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
    private function _testProgramUUT()
    {
        $output = array();

        $this->display->displayHeader("Testing Programmed UUT");
        $this->out("\n\r");
        $this->_powerUUT(self::ON);
        $this->out("Power Up Delay");
        sleep(5);
        $this->_ENDPT_SN = "8012";
        
        $choice = readline("\n\rHit Enter to Continue: ");
        $result = $this->_setPowerTable();
        
        if ($result == self::PASS) {
            $this->_runApplicationTest();
        } else { 
            $this->out("Unable to run App Test!");
        }
        
        $this->_powerUUT(self::OFF);
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

        /* close K1 - +12V to VBus */
        $cmdNum = self::SET_DIGITAL_COMMAND; 
        $dataVal = "0300";
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);

        sleep(1);
        /* open K1  - 12 Ohm Load to VBUS */
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
