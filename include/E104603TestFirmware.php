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
class E104603TestFirmware
{

    private $_fixtureTest;
    private $_system;
    private $_device1;
    private $_device2;
    private $_device3;

    const DEVICE1_ID = 0x9010;
    const DEVICE2_ID = 0x9002;
    const DEVICE3_ID = 0x9000;

    const SET_POWERTABLE_COMMAND  = 0x45;
    const SETCONTROLCHAN_COMMAND  = 0x64;
    const READCONTROLCHAN_COMMAND = 0x65;

    const ON   = 1;
    const OFF  = 0;
    
    const PASS = 1;
    const FAIL = 0;

    const HEADER_STR     = "Battery Coach Firmware Release Test & Program Tool";
    
    private $_testFirmwareMainMenu = array(
                                0 => "Run Tests",
                                1 => "Single Step Tests",
                                );
   
    private $_singleStepMenu = array(
                                0 => "Turn on Power Supply Port",    /* A */
                                1 => "Turn off Power Supply Port",    /* B */
                                2 => "Turn on Battery Port",          /* C */
                                3 => "Turn off Battery Port",         /* D */
                                4 => "Turn on Load Port A",           /* E */
                                5 => "Turn on Load Port B",           /* F */
                                6 => "Turn off Load Port A",          /* G */
                                7 => "Turn off Load Port B",          /* H */
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
        $this->_device1 = $this->_system->device();
        $this->_device1->set("id", self:: DEVICE1_ID);
        $this->_device1->set("Role","PowerTestBoard");

        $this->_device2 = $this->_system->device();
        $this->_device2->set("id", self:: DEVICE2_ID);
        $this->_device2->set("Role","BatteryTestBoard");

        $this->_device3 = $this->_system->device();
        $this->_device3->set("id", self:: DEVICE3_ID);
        $this->_device3->set("Role","LoadTestBoard");

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
        $obj = new E104603TestFirmware($config, $sys);
        return $obj;
    }

    /**
    *****************************************************************************
    *
    *                  T E S T   F I R M W A R E   M A I N 
    *
    *****************************************************************************
    *
    * It would be nice to have a test fixture ID test to verify
    * that the fixture matches the menu selection.
    * 
    * @return null
    */
    public function runTestFirmwareMain()
    {
        $exitTest = false;
        $result;

        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_testFirmwareMainMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_runFirmwareTests();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_singleStepTests();
            } else {
                $exitTest = true;
                $this->_system->out("Exit Firmware Test Tool");
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
    * Run Firmware Tests Routine
    *
    * This function will run through the firmware test functions
    * and display the results.
    *
    */
    private function _runFirmwareTests()
    {
        $this->display->clearScreen();

        $result = $this->_checkTestBoards();
        $result = self::PASS; 
        if ($result == self::PASS) {
        
           //$this->_setPowerTableNormalLoad();
           // $this->_setPowerTableBattery();
            //$this->_setPowerTablePowerSupply();
            
           //$this->_setBatteryPort(self::ON);
           $this->_setPowerSupplyPort(self::ON);
          // $chan = 0;
          // $this->_setPortLoad($chan, self::OFF); 
            
            
            
        } else {
            $this->_system->out("Test boards failed communications. ");
            $this->_system->out("Correct the problem and retry testing. ");
        }
       

        $this->_system->out("");
        $this->_system->out("*** Not Done ***");

        $choice = readline("\n\rHit Enter to Exit!");
        
    }

    /**
    ************************************************************
    * Single Step Tests Routine
    *
    * This function will allow the user to single step through
    * the release firmware test procedures.
    *
    */
    private function _singleStepTests()
    {
        $exitSingleStep = false;
        $result;
        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_singleStepMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_setPowerSupplyPort(self::ON);
            } else if (($selection == "B") || ($selection == "b")){
                $this->_setPowerSupplyPort(self::OFF);
            } else if (($selection == "C") || ($selection == "c")){
                $this->_setBatteryPort(self::ON);
            } else if (($selection == "D") || ($selection == "d")){
                $this->_setBatteryPort(self::OFF);
            } else if (($selection == "E") || ($selection == "e")){
                $chan = 0;
                $this->_setPortLoad($chan, self::ON);
            } else if (($selection == "F") || ($selection == "f")){
                $chan = 1;
                $this->_setPortLoad($chan, self::ON);
            } else if (($selection == "G") || ($selection == "g")){
                $chan = 0;
                $this->_setPortLoad($chan, self::OFF);
            } else if (($selection == "H") || ($selection == "h")){
                $chan = 1;
                $this->_setPortLoad($chan, self::OFF);
            } else {
                $exitSingleStep = true;
                $this->_system->out("Exit Single Step Test");
            }

        } while ($exitSingleStep == false);

        $choice = readline("\n\rHit Enter to Continue: ");

        
    }
    
    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T    R O U T I N E S                            */
    /*                                                                           */
    /*****************************************************************************/
    
    /**
    ************************************************************
    * Set Power Supply Port Routine
    * 
    * This function sets the Power Supply Port 0 to on or _turn 
    * line based on the state passed in to it.
    *
    */
    private function _setPowerSupplyPort($state)
    {
        $chan = 0;
        $snD1 = self::DEVICE1_ID;
        
        $this->_readControlChan($snD1, $chan);
        sleep(2);
        
        $this->_setControlChan($snD1, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD1, $chan, $state);
        
    }
    /** 
    *************************************************************
    * Set Battery Port Routine
    *
    * This function sets the Battery Port 0 on or off line 
    * based on the state passed in to it.
    *
    */
    private function _setBatteryPort($state)
    {
        $chan = 0;
        $snD2 = self::DEVICE2_ID;
        
        $this->_readControlChan($snD2, $chan);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        
    }
     
     
     
   /**
    ************************************************************
    * Set Loads Off Routine
    *
    * This function turns off Ports A and B so there is no 
    * load current flowing.
    *
    */
    private function _turnOffLoads()
    {
    
        $chan = 0;
        $this->_setPortLoad($chan, self::OFF);
        $chan = 1;
        $this->_setPortLoad($chan, self::OFF);
    }
    
    
    
    /**
    ************************************************************
    * Set Load Port A Routine
    *
    * This function sets the Port on the Load test board 
    * to the desired state, either on or off.
    *
    */
    private function _setPortLoad($chan, $state)
    {
        $snD3 = self::DEVICE3_ID;
        $this->_readControlChan($snD3, $chan);
        sleep(2);
        
        
        $this->_setControlChan($snD3, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD3, $chan, $state);
    }
     
    
    /**
    ************************************************************
    * Check Test System Boards Routine
    * 
    * This function pings the test system boards and returns 
    * the results of the communications process.
    *
    * @return boolean $testResult
    */
    private function _checkTestBoards()
    {
    
        $serNum1 = self::DEVICE1_ID;   /* Power Supply Board */
        $serNum2 = self::DEVICE2_ID;   /* Battery Board      */
        $serNum3 = self::DEVICE3_ID;   /* Load Board         */
        
        $result = $this->_pingEndpoint($serNum1);
        if ($result == true) {
            $this->_system->out("SN ".dechex($serNum1)." Board Responding!");
            $result = $this->_pingEndpoint($serNum2);
            if ($result == true) {
                $this->_system->out("SN ".dechex($serNum2)." Board Responding!");
                $result = $this->_pingEndpoint($serNum3);
                if ($result == true) {
                    $this->_system->out("SN ".dechex($serNum3)." Board Responding!");
                    $testResult = self::PASS;
                } else {
                    $testResult = self::FAIL;
                    $this->_system->out("SN ".dechex($serNum3)." Board Failed Ping!");
                }
            } else {
                $testResult = self::FAIL;
                $this->_system->out("SN ".dechex($serNum2)." Board Failed Ping!");
            }
        } else {
            $testResult = self::FAIL;
            $this->_system->out("SN ".dechex($serNum1)." Board Failed Ping!");
        }
        
    
        return $testResult;
    
    }



    /*****************************************************************************/
    /*                                                                           */
    /*              P O W E R   T A B L E   R O U T I N E S                      */
    /*                                                                           */
    /*****************************************************************************/
   


    /**
    ************************************************************
    * Set Power Table Normal Load Routine
    *
    * This routine sets up the power table in a UUT that already
    * has the application code loaded.  It sets both power ports
    * to a normal load driver so they can be controlled with 
    * a set control chan command.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    */
    private function _setPowerTableNormalLoad()
    {
        $this->_system->out("");

        $this->_system->out("");
        $this->_system->out("Setting Power Table");
        $this->_system->out("*******************");
        
        $decVal = self::DEVICE3_ID;

        $result = $this->_configStore($decVal);

        if ($result == self::PASS) {
            $idNum = $decVal;
            $cmdNum = self::SET_POWERTABLE_COMMAND;

            $portData = "00";
            $driverData ="A0000000";  /* Driver, Subdriver, Priority and mode */
            $driverName = "4C6F616420310000000000000000000000000000000000";
            $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
            $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
            $dataVal = $portData.$driverData.$driverName.
                        $fillData.$fillData2;
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $ReplyData = substr($ReplyData, 0, 14);
            $this->_system->out("Port 0 Reply = ".$ReplyData);
            
            $testReply = substr($ReplyData, 0, 4);
            if ($testReply == "A000") {
            
                $this->_system->out("Setting Power Table 0 - PASSED!");
                
                $portData = "01";
                $dataVal = $portData.$driverData.$driverName.
                            $fillData.$fillData2;
                $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
                $ReplyData = substr($ReplyData, 0, 14);
                $this->_system->out("Port 1 Reply = ".$ReplyData);
                
                $testReply = substr($ReplyData, 0, 4);
                if ($testReply == "A000") {
                    $this->_system->out("Setting Power Table 1 - PASSED!");
                    $result = self::PASS;
                } else {
                    $this->_system->out("Setting Power Table 1 - FAILED!");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Setting Power Table 0 - FAILED!");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Failed to erase E2 Power Table");
        }

        return $result;
    }


    
    
    /**
    ************************************************************
    * Set Power Table Battery and EmptyPort Routine
    *
    * This routine sets up the power table in a UUT that already
    * has the application code loaded.  It sets power port 0 
    * to a battery driver and power port 1 to an empty port.
    *
    * @return integer $result  1=pass, 0=fail
    */
    private function _setPowerTableBattery()
    {
        $this->_system->out("");

        $this->_system->out("");
        $this->_system->out("Setting Power Table");
        $this->_system->out("*******************");
        
        $decVal = self::DEVICE2_ID;

        $result = $this->_configStore($decVal);

        if ($result == self::PASS) {
            $idNum = $decVal;
            $cmdNum = self::SET_POWERTABLE_COMMAND;

            $portData = "00";
            $driverData ="10000000";  /* Driver, Subdriver, Priority and mode */
            $driverName = "506F727420410000000000000000000000000000000000";
            $fillData  = "100E0100B0360000EC2C0000E80300000100BC340000F82A000004";  /* 27 bytes */
            $fillData2 = "290000E8030000FC3A0000D4300000FFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
            $dataVal = $portData.$driverData.$driverName.
                        $fillData.$fillData2;
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $ReplyData = substr($ReplyData, 0, 14);
            $this->_system->out("Port 0 Reply = ".$ReplyData);
            
            $testReply = substr($ReplyData, 0, 4);
            if ($testReply == "1000") {
            
                $this->_system->out("Setting Power Table 0 - PASSED!");
                
                $portData = "01";
                $driverData ="FF000001";  /* Driver, Subdriver, Priority and mode */
                $driverName = "506F727420420000000000000000000000000000000000";
                $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
                $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
                $dataVal = $portData.$driverData.$driverName.
                            $fillData.$fillData2;
                $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
                $ReplyData = substr($ReplyData, 0, 14);
                $this->_system->out("Port 1 Reply = ".$ReplyData);
                
                $testReply = substr($ReplyData, 0, 4);
                if ($testReply == "FF00") {
                    $this->_system->out("Setting Power Table 1 - PASSED!");
                    $result = self::PASS;
                } else {
                    $this->_system->out("Setting Power Table 1 - FAILED!");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Setting Power Table 0 - FAILED!");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Failed to erase E2 Power Table");
        }

        return $result;
    }


    /**
    ************************************************************
    * Set Power Table Power Supply and EmptyPort Routine
    *
    * This routine sets up the power table in a UUT that already
    * has the application code loaded.  It sets power port 0 
    * to a power supply driver and power port 1 to an empty port.
    *
    * @return integer $result  1=pass, 0=fail, -1=hard fail
    */
    private function _setPowerTablePowerSupply()
    {
        $this->_system->out("");

        $this->_system->out("");
        $this->_system->out("Setting Power Table");
        $this->_system->out("*******************");
        
        $decVal = self::DEVICE3_ID;

        $result = $this->_configStore($decVal);

        if ($result == self::PASS) {
            $idNum = $decVal;
            $cmdNum = self::SET_POWERTABLE_COMMAND;

            $portData = "00";
            $driverData ="E0000001";  /* Driver, Subdriver, Priority and mode */
            $driverName = "506F727420410000000000000000000000000000000000";
            $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
            $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
            $dataVal = $portData.$driverData.$driverName.
                        $fillData.$fillData2;
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $ReplyData = substr($ReplyData, 0, 14);
            $this->_system->out("Port 0 Reply = ".$ReplyData);
            
            $testReply = substr($ReplyData, 0, 4);
            if ($testReply == "E000") {
            
                $this->_system->out("Setting Power Table 0 - PASSED!");
                
                $portData = "01";
                $driverData ="FF000001";  /* Driver, Subdriver, Priority and mode */
                $driverName = "506F727420420000000000000000000000000000000000";
                $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
                $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
                $dataVal = $portData.$driverData.$driverName.
                            $fillData.$fillData2;
                $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
                $ReplyData = substr($ReplyData, 0, 14);
                $this->_system->out("Port 1 Reply = ".$ReplyData);
                
                $testReply = substr($ReplyData, 0, 4);
                if ($testReply == "FF00") {
                    $this->_system->out("Setting Power Table 1 - PASSED!");
                    $result = self::PASS;
                } else {
                    $this->_system->out("Setting Power Table 1 - FAILED!");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Setting Power Table 0 - FAILED!");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Failed to erase E2 Power Table");
        }

        return $result;
    }

    /**
    ************************************************************
    * Erase Power Port E2 Routine
    * 
    * This function sends a command to the device to erase 
    * the power table eeprom memory so that new power table
    * settings can be loaded.
    *
    * @param $deviceID  device serial number
    *
    * @return $result   1=pass, 0=fail
    */
    private function _configStore($deviceID)
    {
        $idNum = $deviceID;
        $cmdNum = 0x1A;
        $dataVal = "0000FFFFFFFF";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);

        $testReply = substr($ReplyData, 0, 8);
        if ($testReply == "FFFFFFFF") {
            $testResult = self::PASS;
        } else {
            $testResult = self::FAIL;
        }
        
        return $testResult;

    }

    /**
    ************************************************************
    * Read Control Channel Routine
    *
    * This function reads the control channel value for the 
    * channel passed in to it.
    *
    * @param int $snNum  serial number for endpoint
    * @param int $chanNum  channel number
    *
    * @return void
    */
    private function _readControlChan($snNum, $chanNum)
    {
        $idNum = $snNum;
        $cmdNum = self::READCONTROLCHAN_COMMAND;
        if ($chanNum == 1) {
            $dataVal = "00";
        } else {
            $dataVal = "01";
        }

        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Port ".$chanNum." Control Channel Reply = ".$ReplyData);
    }

    /**
    ************************************************************
    * Set Control Channel Routine
    *
    * This function sets the control channel of the channel number 
    * passed in to it either on or off depending on the state 
    * parameter.
    *
    * @param int $snNum    device serial number
    * @param int $chanNum  channel number
    * @param int $state    On or Off
    * 
    */
    private function  _setControlChan($snNum, $chanNum, $state)
    {
        $idNum = $snNum;
        $cmdNum = self::SETCONTROLCHAN_COMMAND;
        switch ($chanNum) {
            case 1:
                if ($state == self::ON) {
                    $dataVal = "01204E0000";
                } else {
                    $dataVal = "0100000000";
                }
                break;
            case 0:
                if ($state == self::ON) {
                    $dataVal = "00204E0000";
                } else {
                    $dataVal = "0000000000";
                }
                break;
        }
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Set Control Channel Reply = ".$ReplyData);

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
