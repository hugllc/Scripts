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
                                0 => "UUT Power Up",
                                1 => "Load Test Firmware",
                                2 => "Port 1",
                                3 => "Port 2",
                                4 => "VBus",
                                5 => "Read Micro SN",
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
            $this->display->clearScreen();
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_eptroubleMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_trblshtPwrUp();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_trblshtLoadFirmware();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_trblshtPort1();
            } else if (($selection == "D") || ($selection == "d")){
                $this->_trblshtPort2();
            } else if (($selection == "E") || ($selection == "e")){
                $this->_trblshtVBus();
            } else if (($selection == "F") || ($selection == "f")){
                $this->_trblshtReadMicroSN();
            } else if (($selection == "G") || ($selection == "g")){
                $this->_troubleshoot7();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting Tool");
            }

        } while ($exitTest == false);

        $choice = readline("\n\rHit Enter to Continue: ");

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
    * Troubleshoot Power Up Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPwrUp()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        
        $this->_powerUUT(self::ON);
        
        $voltsVB = $this->_readTesterBusVolt();
        $voltsVcc = $this->_readTesterVCC();
        $this->_system->out("");
        
        If (($voltsVB > 11.5) and ($voltsVB < 13.00)) {
            $this->_system->out("Bus Voltage is within range");
            
            if (($voltsVcc > 3.0) and ($voltsVcc < 3.4)) {
                $this->_system->out("Vcc is within range");
            } else {
                $this->_system->out("Vcc is out of range");
                $this->_system->out("Scope out power supply circuit");
            }
        } else {
            $this->_system->out("Bus Voltage out of range");
            $this->_system->out("Scope out Bus Voltage Circuit");
        }
        
        $choice = readline("\n\rTake Measurements and Hit Enter to Exit: ");
        

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }


    
    /**
    ************************************************************
    * Troubleshoot Load Firmware Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtLoadFirmware()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);
        
        $result = $this->_loadTestFirmware();
        
        if ($result == self::PASS) {
            $this->_system->out("Test Firmware Loaded");
        } else {
            $this->_system->out("If load firmware fails, first verify the programmer connections.");
            $this->_system->out("If connections are good, check signal to microcontroller.");
            $this->_system->out("If signal is good, it may be a bad microcontroller.");
        }
   
        $choice = readline("\n\rHit Enter to Exit: ");
        
        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
        
        
    }

    /**
    ************************************************************
    * Troubleshoot Port 1 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPort1()
    {
    
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort1Load(self::ON);

        $this->_system->out("Port 1 Load connected!");
        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();

        if ($p1Volts < 0.2) {
            $this->_system->out("Port 1 off test pass!");
        } else {
            $this->_system->out("Port 1 off test fail!");
            $this->_system->out("Check upper FET for Short");
        }

        $this->_setPort1(self::ON);
        $this->_system->out("Port 1 turned on.\n\r");

        $voltsP1 = $this->_readTesterP1Volt(); 
        $p1Volts = $this->_readUUTPort1Volts();
        $p1Amps = $this->_readUUTPort1Current();

        $this->_system->out("\n\rScope PWM output and measure voltages.");
        $this->_system->out("");

        $choice = readline("Hit Enter to Continue");

        
        $this->_setPort1(self::OFF);
        $this->_setPort1Load(self::OFF); /* Disconnect Port 1 Load */


        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ************************************************************
    * Troubleshoot Port 2 Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtPort2()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $this->_setPort2Load(self::ON);

        $this->_system->out("Port 2 Load connected!");
        $voltsP2 = $this->_readTesterP2Volt(); 
        $p2Volts = $this->_readUUTPort2Volts();
        $p2Amps = $this->_readUUTPort2Current();

        if ($p2Volts < 0.2) {
            $this->_system->out("Port 2 off test pass!");
        } else {
            $this->_system->out("Port 2 off test fail!");
            $this->_system->out("Check upper FET for Short");
        }

        $this->_setPort2(self::ON);
        $this->_system->out("Port 2 turned on.\n\r");

        $voltsP2 = $this->_readTesterP2Volt(); 
        $p2Volts = $this->_readUUTPort2Volts();
        $p2Amps = $this->_readUUTPort2Current();

        $this->_system->out("\n\rScope PWM output and measure voltages.");
        $this->_system->out("");

        $choice = readline("Hit Enter to Continue");

        
        $this->_setPort2(self::OFF);
        $this->_setPort2Load(self::OFF); /* Disconnect Port 1 Load */


        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ************************************************************
    * Troubleshoot VBus Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtVBus()
    {
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);


        $this->_setPort1_V12(self::ON); /* +12V to Port 1 */
        $voltsP1 = $this->_readTesterP1Volt();

        if (($voltsP1 > 11.50) and ($voltsP1 < 13.00)) { 
            $this->_setVBus_V12(self::OFF); /* connects 12 ohm load */
            $voltsVB = $this->_readTesterBusVolt();
            $VBvolts = $this->_readUUTBusVolts();
            $tVolts = $this->_readTesterP1Volt();

            $choice = readline("\n\rHit Enter to Continue: ");

            if ($VBvolts < 0.2) {
                $this->_setPort1(self::ON);
                sleep(1);
                $voltsVB = $this->_readTesterBusVolt();
                $VBvolts = $this->_readUUTBusVolts();
                $p1Volts = $this->_readUUTPort1Volts();
                $p1Amps = $this->_readUUTPort1Current();
                $choice = readline("\n\rHit Enter to Continue: ");


                $this->_setPort1(self::OFF);
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Vbus Troubleshoot Complete");
                $this->_setPort1_V12(self::OFF);
            } else {
                $this->_setVBus_V12(self::ON);
                $this->_system->out("Bus Voltage Off:".$VBvolts."V");
                $this->_setPort1_V12(self::OFF);
            }

        } else {
            $this->_setPort1_V12(self::OFF);
            $voltsP1 = $this->_readTesterP1Volt();
            $this->_system->out("Port 1 Supply Failed!");
            $this->_system->out("Port 1  Tester = ".$voltsP1." volts");
        }
            
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
    }

    /**
    ************************************************************
    * Troubleshoot Read Micro Serial Number Routine
    *
    * This function will eventually do some troubleshooting 
    * routine for the battery socializer board.
    *
    */
    private function _trblshtReadMicroSN()
    {
    
        $this->_system->out("\n\r  Powering up UUT!");
        $this->_system->out("********************\n\r");
        $this->_powerUUT(self::ON);
        sleep(1);

        $MicroSN = $this->_readMicroSN();
        $choice = readline("\n\rHit Enter to Continue: ");

        $this->_system->out("Powering down UUT!");
        $this->_powerUUT(self::OFF);
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
