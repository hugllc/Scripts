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
    const READ_POWERTABLE_COMMAND = 0x46;

    const SET_RTC_COMMAND         = 0x50;
    const READ_RTC_COMMAND        = 0x51;

    const READSENSOR_DATA_COMMAND = 0x53;
    const READRAWSENSOR_COMMAND   = 0x54;
    const READSENSORS_COMMAND     = 0x55;
    
    const SETCONFIG_COMMAND       = 0x5B;
    const READCONFIG_COMMAND      = 0x5c;

    const SETCONTROLCHAN_COMMAND  = 0x64;
    const READCONTROLCHAN_COMMAND = 0x65;
    
    const READ_ERRORLOG_COMMAND   = 0x73;
    
    const ADC_POFFSET_MAX = "0x0200";
    const ADC_NOFFSET_MAX = "0x0E66";
    const ADC_GAINCOR_MIN = "0x0400";
    const ADC_GAINCOR_MAX = "0x0FFF";

    const DAC_POFFSET_MAX = "0xDE";
    const DAC_POFFSET_MIN = "0x80";
    const DAC_NOFFSET_MAX = "0x5E";

    const DAC_PGAINCOR_MAX = "0x3E";
    const DAC_NGAINCOR_MIN = "0x80";
    const DAC_NGAINCOR_MAX = "0xBE";


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
                                0 => "Power Supply Port",    /* A */
                                1 => "Battery Port",         /* B */
                                2 => "Load Ports",           /* C */
                                );
                                
    private $_powerSupplyBdMenu = array(
                                0 => "Read Data Values",
                                1 => "Read the Config",
                                2 => "Turn on Power Supply Port",
                                3 => "Turn off Power Supply Port",
                                4 => "Turn on Relay Port",
                                5 => "Turn off Relay Port",
                                6 => "Write the Power Table",
                                7 => "Verify Power Table",
                                );

    private $_batteryBdMenu = array(
                                0 => "Read Data Values",
                                1 => "Read the Config",
                                2 => "Turn on Battery Port",
                                3 => "Turn off Battery Port",
                                4 => "Turn on Shorted Port",
                                5 => "Turn off Shorted Port",
                                6 => "Write the Power Table",
                                7 => "Verify Power Table",
                                8 => "Set Real Time Clock",
                                9 => "Read Real Time Clock",
                                );
   
    private $_loadBdMenu = array(
                                0 => "Read Data Values",
                                1 => "Read the Config",
                                2 => "Turn on Load Port A",
                                3 => "Turn off Load Port A",
                                4 => "Turn on Load Port B",
                                5 => "Turn off Load Port B",
                                6 => "Write the Power Table",
                                7 => "Verify Power Table",
                                );
                                
    public $display;
    private $_firmwareVersion;

    

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
    * Test Steps
    * -------------------------
    * 1.  Check communications with Battery Coach boards
    * 2.  Load release application firmware into each board.
    * 3.  Verify firmware version running.
    * 4.  Configure boards for power supply, battery and loads.
    * 5.  Make sure power supply port is on.
    * 6.  make sure battery and load ports are off.
    * 7.  Turn battery port on to determine charge level.
    * 8.  If charged, then begin discharge procedure.
    * 9.  To discharge, turn off power supply port so battery powers bus
    * 10. verify bus voltage.
    * 11. connect load port to bus to discharge the battery.
    * 12.  Monitor battery voltage until discharged to 11.8volts.
    * 13.  Turn off loads.
    * 14.  Turn on power supply and monitor battery charge current 
    *      and battery voltage.
    * 15.  When battery voltage reaches 13.8volts test is complete.
    *
    * 
    *
    */
    private function _runFirmwareTests()
    {
        $this->display->clearScreen();
        $this->_system->out("");
        
        $result = $this->_checkTestBoards();
        if ($result == self::PASS) {
            $this->_system->out("");
            $choice = readline("Do you need to run the setup? (Y/N):");
                
            if (($choice == "Y") || ($choice =="y")){

                $this->_loadReleaseFirmware();
                
                $this->_verifyFirmwareVersion(self::DEVICE1_ID);
                $this->_verifyFirmwareVersion(self::DEVICE2_ID);
                $this->_verifyFirmwareVersion(self::DEVICE3_ID);
                
                $this->_setPowerTablePowerSupply();
                $this->_setPowerTableBattery();
                $this->_setPowerTableNormalLoad();
                    
                $this->_setBatteryPort(self::OFF);
                $this->_setPowerSupplyPort(self::ON);
                $chan = 0;
                $this->_setPortLoad($chan, self::OFF); 
           }
           
           
          // $this->_runBatteryChargeTest();
           $this->_runErrorLogTest();
            
            
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
                $this->_singleStepPowerSupply();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_singleStepBattery();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_singleStepLoads();
            } else {
                $exitSingleStep = true;
                $this->_system->out("Exit Single Step Mode");
            }

        } while ($exitSingleStep == false);

       // $choice = readline("\n\rHit Enter to Continue: ");

        
    }
    
    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T    R O U T I N E S                            */
    /*                                                                           */
    /*****************************************************************************/
    
    /**
    ***********************************************************
    * Battery Charge Test Routine
    *
    * This function runs the battery charge test.  It tests the
    * the ability of the firmware to current limit the power 
    * being delivered to a battery on a port.
    *
    *  The ending state of the ports is as follows:
    *      PowerSupply Port: ON
    *      Battery Port    : OFF
    *      Short   Port    : OFF 
    *      Load 1  Port    : OFF 
    *      Load 2  Port    : OFF 
    * 
    *
    */
    private function _runBatteryChargeTest()
    {
        $this->_system->out("");
        $this->_system->out("***********************************");
        $this->_system->out("*                                 *");
        $this->_system->out("*   Running Battery Charge Test   *");
        $this->_system->out("*                                 *");
        $this->_system->out("***********************************");
        
        $powerSupplyStatus = $this->_powerSupplyOnBus();
        if (!$powerSupplyStatus) {
            $this->_setPowerSupplyPort(self::ON);
        }
        
        $this->_setBatteryPort(self::OFF);
        sleep(2);
        
        $batteryStatus = $this->_batteryCharged();
        if ($batteryStatus) {
            $this->_system->out("Battery Charged\n\r");
        } else {
            $this->_system->out("Battery Needs Charging\n\r");
        }
        
        /* if charged then lets discharge into loads */
        if ($batteryStatus) {
            $this->_system->out("Preparing to discharge battery");
            $this->_setBatteryPort(self::ON);
            sleep(2);
            
            $this->_setPowerSupplyPort(self::OFF);
            sleep(2);
            
            $chan = 0;
            $this->_setPortLoad($chan, self::ON);
            $chan = 1;
            $this->_setPortLoad($chan, self::ON);
            
            $this->_system->out("*** DISCHARGING BATTERY ****\n\r");
            $this->_watchBatteryForDischarge();
            
            $chan = 0;
            $this->_setPortLoad($chan, self::OFF);
            $chan = 1;
            $this->_setPortLoad($chan, self::OFF);
            
            $this->_setPowerSupplyPort(self::ON);
        } else {
            /* battery needs charging put it on line */
            $this->_setBatteryPort(self::ON);
            sleep(2);
        }
        
        $this->_system->out("** CHARGING Battery ***\n\r");
        /* monitor until charged */
        $this->_watchBatteryForCharge();
        $this->_setBatteryPort(self::OFF);
        
        $this->_system->out("Battery Charge Test Complete!");
    }
   
    /* Error log data from 9002 when shorted port 1 */
    /* 013B090002310000000000000000000000000000000000000000000000000
       000000000000000000000000000000000000000000000000000000000000000000000  */

    /**
    ***********************************************************
    * Error Log Test Routine
    *
    * This function tests the systems ability to log and 
    * recover from an error condition.
    *
    *
    */
    private function _runErrorLogTest()
    {
        $this->_system->out("");
        $this->_system->out("******************************");
        $this->_system->out("*                            *");
        $this->_system->out("*   Running Error Log Test   *");
        $this->_system->out("*                            *");
        $this->_system->out("******************************\n\r");
        

        $this->_system->out("Turning on Shorted Port");
        $this->_setShortPort(self::ON);
        $this->_system->out("");

        $this->_system->out("Closing relay to short Port 1 to GND!");
        $this->_setRelayPort(self::ON);
        $this->_system->out("");

        $this->_system->out("Opening relay shorting Port 1 to GND!");
        $this->_setRelayPort(self::OFF);
        $this->_system->out("");

        $this->_system->out("Reading Error Log For 9002");
        $idNum = self::DEVICE2_ID;
        $cmdNum = self::READ_ERRORLOG_COMMAND;
        $dataVal = "00";
        
        $ReplyData = $this->_sendPacket($idNum, $cmdNum, $dataVal);
        
        $this->_displayErrorLogData($ReplyData);;
        $this->_system->out("");
        

        $this->_system->out("Turning off Shorted Port");
        $this->_setShortPort(self::OFF);
        $this->_system->out("");


        $this->_system->out("Turning Power Supply Port On");
        $this->_setPowerSupplyPort(self::ON);
        $this->_system->out("");

        $errorNumber = substr($ReplyData, 8, 2);
        $error = hexdec($errorNumber);

        if ($error == 2) {
            $this->_system->out("Error Log Test Passed!");
            $result = true;
        } else {
            $this->_system->out("Error Log Test Failed!");
            $result = false;
        }

        return $result;
    }


    /**
    ***********************************************************
    * Power Supply On bus
    * 
    * This function reads the power supply boards bus voltage
    * to determine if the power supply is feeding the bus. 
    *
    * @return boolean true  = power supply on Bus. 
    *                 false = power supply not on Bus.
    *
    */
    private function _powerSupplyOnBus()
    {
        $idNum  = self::DEVICE1_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;
        $dataVal = "0D";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $busVoltage = $this->_convertDataValue($ReplyData);
        $this->_system->out("Bus Voltage: ".$busVoltage);
        
        if ($busVoltage > 14.0) {
            $this->_system->out("Power Supply feeding Bus\n\r");
            $supplyOnBus = true;
        } else {
            $this->_system->out("Power Supply not on Bus\n\r");
            $supplyOnBus = false;
        }
        
        return $supplyOnBus;
    }
        
    /**
    ***********************************************************
    * Battery Charged Routine
    *
    * This function turns on the battery port and monitors 
    * the battery voltage to determine if it is charged.
    *
    * @return boolean true = charged; false = not fully charged.
    */
    private function _batteryCharged()
    {
        
        $idNum  = self::DEVICE2_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;

        $dataVal = "00"; /* get Port A current data value */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $batCurrent = $this->_convertDataValue($ReplyData);
        
        $dataVal = "01"; /* get Port A voltage data value */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $batVoltage = $this->_convertDataValue($ReplyData);
        
        $this->_system->out("Battery Current: ".$batCurrent);
        $this->_system->out("Battery Voltage: ".$batVoltage);
        $this->_system->out("");
        
        if ($batVoltage > 13.0) {
            $charged = true;
        } else {
            $charged = false;
        }
        
        return $charged;
    }
    
    
    /**
    *****************************************************
    * Watch Battery For Discharge Routine
    * 
    * This function monitors the battery voltage while
    * it is under load until it is sufficiently discharged
    * enough to run the charge test.
    * 
    * @note: I need to add temperature monitoring of the 
    *        battery board bus and the load ports to 
    *        verify the temperature lookup tables and 
    *        conversion routines.
    *
    */
    private function _watchBatteryForDischarge()
    {
        $idNum = self::DEVICE2_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;
        
        
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $batAmps = $this->_convertDataValue($ReplyData);
        
        $dataVal = "01";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $batVolts = $this->_convertDataValue($ReplyData);
        
        $this->_system->out("Battery Current:".$batAmps);
        $this->_system->out("Battery Voltage:".$batVolts);
        $this->_system->out("");
        
        $count = 0;
        
        while (($batVolts > 12.00) and ($count < 10)) {
        
            for ($i = 0; $i < 10; $i++) {
                print "*";
                sleep(1);
            }
            print "\n\r";
            
            $idNum = self::DEVICE2_ID;
            $cmdNum = self::READSENSOR_DATA_COMMAND;
            
            $dataVal = "00";   /* Port A Current */
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $batAmps = $this->_convertDataValue($ReplyData);
            
            $dataVal = "01";   /* Port A Voltage */
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $batVolts = $this->_convertDataValue($ReplyData);
            
            $dataVal = "0E";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $batBusTemp = $this->_convertDataValue($ReplyData);
            
            $idNum = self::DEVICE3_ID;
            $dataVal = "02";   /* Port A Temperature */
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $loadPortATemp = $this->_convertDataValue($ReplyData);
            
            $this->_system->out("Battery Current :".$batAmps);
            $this->_system->out("Battery Voltage :".$batVolts);
            $this->_system->out("Battery Bus Temp:".$batBusTemp);
            $this->_system->out("Load Port A Temp:".$loadPortATemp);
            $this->_system->out("");
            $count++;
        }
        
        if ($count < 10) {
            $this->_system->out("");
            $this->_system->out("Battery Discharged!");
            $this->_system->out("");
        } else {
            $this->_system->out("Not enough time for discharge!");
            
            
        }
    
    }
    
    
    /**
    **********************************************************
    * Watch Battery For Charge Routine
    *
    * This function monitors the battery current and voltage
    * while it is being charged by the power supply until it
    * is sufficiently charged.
    *
    */
    private function _watchBatteryForCharge()
    {
        $idNum = self::DEVICE2_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;
        
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $batAmps = $this->_convertDataValue($ReplyData);
        
        $dataVal = "01";
        
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $batVolts = $this->_convertDataValue($ReplyData);
        $this->_system->out("Battery Current:".$batAmps);
        $this->_system->out("Battery Voltage:".$batVolts);
        $this->_system->out("");
        
        $count = 0;
        
        while ((($batVolts < 13.4) and ($count < 10)) or (($batAmps > 0.55) and ($count < 10))) {
        
            for ($i = 0; $i < 16; $i++) {
                print "*";
                sleep(1);
            }
            print "\n\r";
            
            $dataVal = "00";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $batAmps = $this->_convertDataValue($ReplyData);
            
            $dataVal = "01";
            $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
            $batVolts = $this->_convertDataValue($ReplyData);
            
            $this->_system->out("Battery Current:".$batAmps);
            $this->_system->out("Battery Voltage:".$batVolts);
            $this->_system->out("");
            $count++;
        }
        
        if ($count < 10) {
            $this->_system->out("");
            $this->_system->out("Battery Charged!");
            $this->_system->out("");
        } else {
            $this->_system->out("Not enough time for Charge!");
            
        }
    
    }

    /**
    ************************************************
    * Set the Real Time Clock Routine
    *
    * This routine gets the current time and sets 
    * the real time clocks in the Battery Coach 
    * boards.
    *
    */
    private function _setRTC($serNum)
    {
        $curTime = time();
        $this->_system->out("Current  Time: ".$curTime);

        $idNum = $serNum;
        $cmdNum = self::SET_RTC_COMMAND;
        
        $hexdata = dechex($curTime);

   
        $this->_system->out("hex time value".$hexdata);

        $dataVal = substr($hexdata, 6, 2);
        $dataVal .= substr($hexdata, 4, 2);
        $dataVal .= substr($hexdata, 2, 2);
        $dataVal .= substr($hexdata, 0, 2);

        $this->_system->out("Data Val: ".$dataVal);

        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);

        $this->_system->out("Reply Data: ",$ReplyData);

        $choice = readline("Hit Enter to Continue");

    }

    /**
    ***********************************************************
    * Read RTC Routine
    *
    * This function reads the real time clock to check 
    * its time setting.
    *
    */
    private function _readRTC($serNum )
    {
        $idNum = $serNum;
        $cmdNum = self::READ_RTC_COMMAND;

        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $this->_system->out("Read RTC reply:".$ReplyData);

        $timeLen = strlen($ReplyData);

        if ($timeLen == 8) {

            $hexTime  = substr($ReplyData, 6, 2);
            $hexTime .= substr($ReplyData, 4, 2);
            $hexTime .= substr($ReplyData, 2, 2);
            $hexTime .= substr($ReplyData, 0. 2);
        
            $decTime = hexdec($hexTime);
            $localTime = localtime($decTime);

            $dateStr = ($localTime[tm_mon] + 1);
            $dateStr .= "/".$localTime[tm_mday];
            $dateStr .= "/".($localTime[tm_year] + 1900);

            $timeStr = $localTime[tm_hour];
            $timeStr .= ":".$localTime[tm_min];
            $timeStr .= ":".$localTime[tm_sec];

            $this->_system->out(" RTC ".$dateStr."  ".$timeStr);


        } else {
            $this->_system->out("Invalid Number of Bytes for Time!");
        }

        $choice = readline("Hit Enter to Continue.");


    }
    
    
    
    /*****************************************************************************/
    /*                                                                           */
    /*               S I N G L E    S T E P    R O U T I N E S                   */
    /*                                                                           */
    /*****************************************************************************/
    
    
    
    /**
    ***********************************************************
    * Single Step Power Supply Port Routine
    *
    * This function allows access to single step routines for
    * the battery coach board with the power supply input.
    *
    */
    private function _singleStepPowerSupply()
    {
    
       $exitSStep = false;
        $result;
        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_powerSupplyBdMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_readPowerSupplyBoard();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_readConfigData(self::DEVICE1_ID);
            } else if (($selection == "C") || ($selection == "c")){
                $this->_setPowerSupplyPort(self::ON);
            } else if (($selection == "D") || ($selection == "d")){
                $this->_setPowerSupplyPort(self::OFF);
            } else if (($selection == "E") || ($selection == "e")){
                $this->_setRelayPort(self::ON);
            } else if (($selection == "F") || ($selection == "f")){
                $this->_setRelayPort(self::OFF);
            } else if (($selection == "G") || ($selection == "g")){
                $this->_setPowerTablePowerSupply();
            } else if (($selection == "H") || ($selection == "h")){
                $this->_verifyPowerTablePowerSupply();
            } else {
                $exitSStep = true;
                $this->_system->out("Exit Single Step Power Supply Board");
            }

        } while ($exitSStep == false);

    
    
    
    }
    
    /**
    ************************************************************
    * Read Power Supply Board Data Values Routine
    * 
    * This function sends a read sensors request to the battery
    * coach board with the power supply input.  It then parses
    * the reply data and displays the values.
    *
    */
    private function _readPowerSupplyBoard()
    {
    
        $idNum = self::DEVICE1_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;

        $dataVal = ""; /* get data values */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $portA_Cdata = substr($ReplyData, 2, 8);
        $portA_Vdata = substr($ReplyData, 10, 8);
        $portA_Tdata = substr($ReplyData, 18, 8);
        /* Port A Charge                 26, 8 */
        /* Port A Capacity               34, 8 */
        /* Port A Raw Status             42, 8 */
       
        $portB_Cdata = substr($ReplyData, 50, 8);
        $portB_Vdata = substr($ReplyData, 58, 8);
        $portB_Tdata = substr($ReplyData, 66, 8);
        /* Port B Charge                 74, 8 */
        /* Port B Capacity               82, 8 */
        /* Port B Raw Status             90, 8 */
        
        $bus_Cdata = substr($ReplyData, 98, 8);
        $bus_Vdata = substr($ReplyData, 106, 8);
        $bus_TAdata = substr($ReplyData, 114, 8);
        $bus_TBdata = substr($ReplyData, 122, 8);
        
        $portA_Current = $this->_convertDataValue($portA_Cdata);
        $portA_Volts = $this->_convertDataValue($portA_Vdata);
        $portA_Temp = $this->_convertDataValue($portA_Tdata);
        
        $portB_Current = $this->_convertDataValue($portB_Cdata);
        $portB_Volts = $this->_convertDataValue($portB_Vdata);
        $portB_Temp = $this->_convertDataValue($portB_Tdata);
        
        $bus_Current = $this->_convertDataValue($bus_Cdata);
        $bus_Volts = $this->_convertDataValue($bus_Vdata);
        $busportA_Temp = $this->_convertDataValue($bus_TAdata);
        $busportB_Temp = $this->_convertDataValue($bus_TBdata);
        
        $this->_system->out("");
        $this->_system->out("");
        $this->_system->out("**********************************");
        $this->_system->out("PORT A Current:".$portA_Current." Amps");
        $this->_system->out("PORT A Voltage:".$portA_Volts." Volts");
        $this->_system->out("PORT A Temp   :".$portA_Temp." Degrees C");
        $this->_system->out("");
        
        $this->_system->out("PORT B Current:".$portB_Current." Amps");
        $this->_system->out("PORT B Voltage:".$portB_Volts." Volts");
        $this->_system->out("PORT B Temp   :".$portB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("BUS    Current:".$bus_Current." Amps");
        $this->_system->out("BUS    Voltage:".$bus_Volts." Volts");
        $this->_system->out("BUS PA Temp   :".$busportA_Temp." Degrees C");
        $this->_system->out("BUS PB Temp   :".$busportB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("");
        
        $choice = readline("Hit Enter to Continue.");
    
    }
    
    
    
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
        
        if ($state == self::ON) {
            $this->_system->out("SETTING POWER SUPPLY PORT: ON\n\r");
        } else {
            $this->_system->out("SETTING POWER SUPPLY PORT: OFF\n\r");
        }
        
        $this->_readControlChan($snD1, $chan);
        sleep(2);
        
        $this->_setControlChan($snD1, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD1, $chan, $state);
        sleep(2);
        
    }
 
    /**
    ************************************************************
    * Set Relay Port Routine
    * 
    * This function sets the Relay Port 1 to on or off  
    * line based on the state passed in to it.
    *
    */
    private function _setRelayPort($state)
    {
        $chan = 1;
        $snD1 = self::DEVICE1_ID;
        
        if ($state == self::ON) {
            $this->_system->out("SETTING RELAY PORT: ON\n\r");
        } else {
            $this->_system->out("SETTING RELAY PORT: OFF\n\r");
        }
        
        $this->_readControlChan($snD1, $chan);
        sleep(2);
        
        $this->_setControlChan($snD1, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD1, $chan, $state);
        sleep(2);
        
    }
   
    
    /**
    *************************************************************
    * Single Step Battery Port Routine
    * This function allows access to single step routines for
    * the battery coach board with the Battery input.
    *
    */
    private function _singleStepBattery()
    {
       $exitSStep = false;
        $result;
        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_batteryBdMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_readBatteryBoard();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_readConfigData(self::DEVICE2_ID);
            } else if (($selection == "C") || ($selection == "c")){
                $this->_setBatteryPort(self::ON);
            } else if (($selection == "D") || ($selection == "d")){
                $this->_setBatteryPort(self::OFF);
            } else if (($selection == "E") || ($selection == "e")){
                $this->_setShortPort(self::ON);
            } else if (($selection == "F") || ($selection == "f")){
                $this->_setShortPort(self::OFF);
            } else if (($selection == "G") || ($selection == "g")){
                $this->_setPowerTableBattery();
            } else if (($selection == "H") || ($selection == "h")){
                $this->_verifyPowerTableBattery();
            } else if (($selection == "I") || ($selection == "i")){
                $this->_setRTC(self::DEVICE2_ID);
            } else if (($selection == "J") || ($selection == "j")){
                $this->_readRTC(self::DEVICE2_ID);
            } else {
                $exitSStep = true;
                $this->_system->out("Exit Single Step Battery Board");
            }

        } while ($exitSStep == false);

    
    }
    
    /**
    ************************************************************
    * Read Batter Board Data Values Routine
    * 
    * This function sends a read sensors request to the battery
    * coach board with the batter input.  It then parses
    * the reply data and displays the values.
    *
    */
    private function _readBatteryBoard()
    {
        $idNum = self::DEVICE2_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;

        $dataVal = ""; /* get data values */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $portA_Cdata = substr($ReplyData, 2, 8);
        $portA_Vdata = substr($ReplyData, 10, 8);
        $portA_Tdata = substr($ReplyData, 18, 8);
        $portA_Bdata = substr($ReplyData, 26, 8);
        $portA_Sdata = substr($ReplyData, 34, 8);
        /* Port A Raw Status             42, 8 */
       
        $portB_Cdata = substr($ReplyData, 50, 8);
        $portB_Vdata = substr($ReplyData, 58, 8);
        $portB_Tdata = substr($ReplyData, 66, 8);
        /* Port B Charge                 74, 8 */
        /* Port B Capacity               82, 8 */
        /* Port B Raw Status             90, 8 */
        
        $bus_Cdata = substr($ReplyData, 98, 8);
        $bus_Vdata = substr($ReplyData, 106, 8);
        $bus_TAdata = substr($ReplyData, 114, 8);
        $bus_TBdata = substr($ReplyData, 122, 8);
        
        $portA_Current = $this->_convertDataValue($portA_Cdata);
        $portA_Volts = $this->_convertDataValue($portA_Vdata);
        $portA_Temp = $this->_convertDataValue($portA_Tdata);
        $portA_Charge = $this->_convertDataValue($portA_Bdata);
        $portA_Capacity = $this->_convertDataValue($portA_Sdata);
        
        $portB_Current = $this->_convertDataValue($portB_Cdata);
        $portB_Volts = $this->_convertDataValue($portB_Vdata);
        $portB_Temp = $this->_convertDataValue($portB_Tdata);
        
        $bus_Current = $this->_convertDataValue($bus_Cdata);
        $bus_Volts = $this->_convertDataValue($bus_Vdata);
        $busportA_Temp = $this->_convertDataValue($bus_TAdata);
        $busportB_Temp = $this->_convertDataValue($bus_TBdata);
        
        $this->_system->out("");
        $this->_system->out("");
        $this->_system->out("**********************************");
        $this->_system->out("PORT A Current:".$portA_Current." Amps");
        $this->_system->out("PORT A Voltage:".$portA_Volts." Volts");
        $this->_system->out("PORT A Temp   :".$portA_Temp." Degrees C");
        $this->_system->out("PORT A Charge :".$portA_Charge." Ah");
        $this->_system->out("PORT A Capacty:".$portA_Capacity." Ah");
        $this->_system->out("");
        
        $this->_system->out("PORT B Current:".$portB_Current." Amps");
        $this->_system->out("PORT B Voltage:".$portB_Volts." Volts");
        $this->_system->out("PORT B Temp   :".$portB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("BUS    Current:".$bus_Current." Amps");
        $this->_system->out("BUS    Voltage:".$bus_Volts." Volts");
        $this->_system->out("BUS PA Temp   :".$busportA_Temp." Degrees C");
        $this->_system->out("BUS PB Temp   :".$busportB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("");
        
        $choice = readline("Hit Enter to Continue.");
    
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
        
        if ($state == self::ON) {
            $this->_system->out("SETTING BATTERY PORT: ON\n\r");
        } else {
            $this->_system->out("SETTING BATTERY PORT: OFF\n\r");
        }
        
        $this->_readControlChan($snD2, $chan);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        sleep(2);
        
    }

    /** 
    *************************************************************
    * Set Short Port Routine
    *
    * This function sets Port 1 which is shorted to ground, 
    * on or off line based on the state passed in to it.
    *
    */
    private function _setShortPort($state)
    {
        $chan = 1;
        $snD2 = self::DEVICE2_ID;

        
        if ($state == self::ON) {
            $this->_system->out("SETTING SHORT ON PORT: ON\n\r");
        } else {
            $this->_system->out("SETTING SHORT ON PORT: OFF\n\r");
        }
        
        $this->_readControlChan($snD2, $chan);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD2, $chan, $state);
        sleep(2);
        
    }
     
    /**
    *************************************************************
    * Single Step Load Ports Routine
    * This function allows access to single step routines for
    * the battery coach board with the load inputs.
    *
    */
    private function _singleStepLoads()
    {
       $exitSStep = false;
        $result;
        do{
            $this->display->clearScreen();
            
            $selection = $this->display->displayMenu(self::HEADER_STR, 
                            $this->_loadBdMenu);

            if (($selection == "A") || ($selection == "a")) {
                $this->_readLoadBoard();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_readConfigData(self::DEVICE3_ID);
            } else if (($selection == "C") || ($selection == "c")){
                $chan = 0; /* Port A */
                $this->_setPortLoad($chan, self::ON);
            } else if (($selection == "D") || ($selection == "d")){
                $chan = 0; /* Port A */
                $this->_setPortLoad($chan, self::OFF);
            } else if (($selection == "E") || ($selection == "e")){
                $chan = 1; /* Port B */
                $this->_setPortLoad($chan, self::ON);
            } else if (($selection == "F") || ($selection == "f")){
                $chan = 1; /* Port B */
                $this->_setPortLoad($chan, self::OFF);
            } else if (($selection == "G") || ($selection == "g")){
                $this->_setPowerTableNormalLoad();
            } else if (($selection == "H") || ($selection == "h")){
                $this->_verifyPowerTableNormalLoad();
            } else {
                $exitSStep = true;
                $this->_system->out("Exit Single Step Load Board");
            }

        } while ($exitSStep == false);

    }
      
    /**
    ************************************************************
    * Read Load Board Data Values Routine
    * 
    * This function sends a read sensors request to the battery
    * coach board with the loads.  It then parses
    * the reply data and displays the values.
    *
    */
    private function _readLoadBoard()
    {
        $idNum = self::DEVICE3_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;

        $dataVal = ""; /* get data values */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $portA_Cdata = substr($ReplyData, 2, 8);
        $portA_Vdata = substr($ReplyData, 10, 8);
        $portA_Tdata = substr($ReplyData, 18, 8);
        /* Port A Charge                 26, 8 */
        /* Port A Capacity               34, 8 */
        /* Port A Raw Status             42, 8 */
       
        $portB_Cdata = substr($ReplyData, 50, 8);
        $portB_Vdata = substr($ReplyData, 58, 8);
        $portB_Tdata = substr($ReplyData, 66, 8);
        /* Port B Charge                 74, 8 */
        /* Port B Capacity               82, 8 */
        /* Port B Raw Status             90, 8 */
        
        $bus_Cdata = substr($ReplyData, 98, 8);
        $bus_Vdata = substr($ReplyData, 106, 8);
        $bus_TAdata = substr($ReplyData, 114, 8);
        $bus_TBdata = substr($ReplyData, 122, 8);
        
        $portA_Current = $this->_convertDataValue($portA_Cdata);
        $portA_Volts = $this->_convertDataValue($portA_Vdata);
        $portA_Temp = $this->_convertDataValue($portA_Tdata);
        
        $portB_Current = $this->_convertDataValue($portB_Cdata);
        $portB_Volts = $this->_convertDataValue($portB_Vdata);
        $portB_Temp = $this->_convertDataValue($portB_Tdata);
        
        $bus_Current = $this->_convertDataValue($bus_Cdata);
        $bus_Volts = $this->_convertDataValue($bus_Vdata);
        $busportA_Temp = $this->_convertDataValue($bus_TAdata);
        $busportB_Temp = $this->_convertDataValue($bus_TBdata);
        
        $this->_system->out("");
        $this->_system->out("");
        $this->_system->out("**********************************");
        $this->_system->out("PORT A Current:".$portA_Current." Amps");
        $this->_system->out("PORT A Voltage:".$portA_Volts." Volts");
        $this->_system->out("PORT A Temp   :".$portA_Temp." Degrees C");
        $this->_system->out("");
        
        $this->_system->out("PORT B Current:".$portB_Current." Amps");
        $this->_system->out("PORT B Voltage:".$portB_Volts." Volts");
        $this->_system->out("PORT B Temp   :".$portB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("BUS    Current:".$bus_Current." Amps");
        $this->_system->out("BUS    Voltage:".$bus_Volts." Volts");
        $this->_system->out("BUS PA Temp   :".$busportA_Temp." Degrees C");
        $this->_system->out("BUS PB Temp   :".$busportB_Temp." Degrees C");
        $this->_system->out("");

        $this->_system->out("");
        
        $choice = readline("Hit Enter to Continue.");
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
        
        if ($state == self::ON) {
            $this->_system->out("SETTING LOAD PORT: ON\n\r");
        } else {
            $this->_system->out("SETTING LOAD PORT: OFF\n\r");
        }
        
        $this->_readControlChan($snD3, $chan);
        sleep(2);
        
        
        $this->_setControlChan($snD3, $chan, $state);
        sleep(2);
        $this->_setControlChan($snD3, $chan, $state);
    }


/**   2016-04-25 13:36:22 local From: FDE266 -> To: 009010 Command: 55 Type: SENSORREAD (CRC)
      2016-04-25 13:36:22 default From: 009010 -> To: FDE266 Command: 01 Type: REPLY (CRC)
Data: 57  B8FFFFFF = FF FF FF B8 = -48h  = -72d/1000   = -0.072 Amps  Port A
          C1380000 = 00 00 38 C1 = 38C1h = 14529d/1000 = 14.529 Volts Port A
          1C570000 = 00 00 57 1C = 57C1h = 22465d/1000 = 22.465 Degrees C
          00000000 = 00 00 00 00 = 0000h = 00/1000     = 0 Ah
          00000000 = 00 00 00 00 = 0000h = 00/1000     = 0 Ah
          03000000 = 00 00 00 03 = 0003h = 03  Raw Status
          00000000 = 00 00 00 00 = 0000h = 00/1000     = 0.0 Amps Port B
          70000000 = 00 00 00 70 = 0070h = 112d/1000   = 0.112 Volts Port B
          38590000 = 00 00 59 38 = 5938h = 22840/1000  = 22.840 Degrees C
          00000000 = 00 00 00 00 = 0000h = 00/1000     = 0 Ah
          00000000 = 00 00 00 00 = 0000h = 00/1000     = 0 Ah
          02000000 = 00 00 00 02 = 0002h = 02  Raw Status
          47000000 = 00 00 00 47 = 0047h = 71/1000     = 0.071 Amps Bus
          27380000 = 00 00 38 27 = 3827h = 14375/1000  = 14.375 Volts Bus
          30650000 = 00 00 65 30 = 6530h = 25904/1000  = 25.904 Degrees C Bus PA
          A8610000 = 00 00 61 A8 = 61A8h = 25000/1000  = 25.000 Degrees C Bus PB
          07F8FFFF = FF FF F8 07 = -07F9h = -2041/1000 = -2.041 Degrees C Ext 1 
          07F8FFFF = FF FF F8 07 = -07F9h = -2041/1000 = -2.041 Degrees C Ext 2
**/


    /**
    ***********************************************************
    * Measure Port Voltage Routine
    *
    * This function reads the Port A voltage from the power
    * supply board.
    *
    * In the data array the values are as follows:
    *
    *    $powerSupply[0] = Port A Current
    *    $powerSupply[1] = Port A Voltage
    *    $powerSupply[2] = Port A Temperature
    *    $powerSupply[3] = Port A Charge
    *    $powerSupply[4] = Port A Capacity
    *    $powerSupply[5] = Port A Status
    * 
    *    $powerSupply[6] = Port B Current
    *    $powerSupply[7] = Port B Voltage
    *    $powerSupply[8] = Port B Temperature
    *    $powerSupply[9] = Port B Charge
    *    $powerSupply[10]= Port B Capacity
    *    $powerSupply[11]= Port B Status
    *  
    *    $powerSupply[12] = Bus Current
    *    $powerSupply[13] = Bus Voltage
    *    $powerSupply[14] = Bus Temp Port A
    *    $powerSupply[15] = Bus Temp Port B
    *
    *    $powerSupply[16] = Ext Temp 1
    *    $powerSupply[17] = Ext Temp 2
    *
    *
    */
    private function _measurePortVoltage()
    {
        $this->_system->out("***************************************");
        $this->_system->out("* Hey let's try to get some readings! *");
        $this->_system->out("***************************************");
        $this->_system->out("\n\r");


        $idNum = self::DEVICE1_ID;
        $cmdNum = self::READSENSOR_DATA_COMMAND;

        $dataVal = "00"; /* get PORT A current */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $portA_Amps = $this->_convertDataValue($ReplyData);

        $this->_system->out("Port A Current: ".$portA_Amps);


        $dataVal = "01"; /* get PORT A voltage */
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        $portAVolts = $this->_convertDataValue($ReplyData);

        $this->_system->out("Port A Volts: ".$portAVolts);


        $this->_system->out("\n\r");

        $choice = readline("Hit Enter to Continue.");

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
    ***********************************************************
    * ADD a ReadPowerTable Routine
    *
    * This function tests the PACKET_READPOWERTABLE_COMMAND
    * and verifies power table settings.
    *
    /



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
            
                    /*    A0000000
                          4C6F616420310000000000000000000000000000000000
                          FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF
                          FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF  */

            $portData = "00";
            $driverData ="A0000000";  /* Driver, Subdriver, Priority and mode 4 bytes */
            $driverName = "4C6F616420310000000000000000000000000000000000";  /* 23 byte */
            $fillData   = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
            $fillData2  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
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
    * Verify Power Table Normal Load Routine
    * 
    * This function does a read power table from the test system
    * load board and compares it to the required power table 
    * setting.
    *
    */
    private function _verifyPowerTableNormalLoad()
    {
        $this->_system->out("\n\r****************************************");
        $this->_system->out("Verifying Normal Load Power Table");
        $this->_system->out("");
        $driveDataStr = "A0000000"; 
        $driveNameStr = "4C6F616420310000000000000000000000000000000000";
        
        $idNum = self::DEVICE3_ID;
        $cmdNum = self::READ_POWERTABLE_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $replyDataLen = strlen($ReplyData);
        $this->_system->out("Reply Data Length: ".$replyDataLen." characters");
        $this->_system->out("");
        if ($replyDataLen == 160) {
        
            $driverData = substr($ReplyData, 0, 8);
            $driverName = substr($ReplyData, 8, 46);
            
            if ($driverData == $driveDataStr) {
                if ($driverName == $driveNameStr) {
                    $this->_system->out("Power Table Normal Verified!");
                    $result = self::PASS;
                } else {
                    $this->_system->out("Driver Name Does Not Match");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Driver Data Does Not Match");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Not enough data from pwer table read");
            $result = self::FAIL;
        }
        $this->_system->out("");
        $choice = readline("Hit Enter to Continue");
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
                $driverData ="A0000000";  /* Driver, Subdriver, Priority and mode */
                $driverName = "506F727420420000000000000000000000000000000000";
                $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
                $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
                $dataVal = $portData.$driverData.$driverName.
                            $fillData.$fillData2;
                $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
                $ReplyData = substr($ReplyData, 0, 14);
                $this->_system->out("Port 1 Reply = ".$ReplyData);
                
                $testReply = substr($ReplyData, 0, 8);
                if ($testReply == "A0000000") {
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
    * Verify Power Table Battery Routine
    * 
    * This function does a read power table from the test system
    * load board and compares it to the required power table 
    * setting.
    *
    */
    private function _verifyPowerTableBattery()
    {
        $this->_system->out("\n\r****************************************");
        $this->_system->out("Verifying Battery Power Table");
        $this->_system->out("");
        
        $driveDataStr = "10000000"; 
        $driveNameStr = "506F727420410000000000000000000000000000000000";
        $fillDataStr  = "100E0100B0360000EC2C0000E80300000100BC340000F82A000004";  /* 27 bytes */
        $fillDataStr2 = "290000E8030000FC3A0000D4300000FFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
        
        $idNum = self::DEVICE2_ID;
        $cmdNum = self::READ_POWERTABLE_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $replyDataLen = strlen($ReplyData);
        $this->_system->out("Reply Data Length: ".$replyDataLen." characters");
        $this->_system->out("");
        if ($replyDataLen == 160) {
        
            $driverData = substr($ReplyData, 0, 8);
            $driverName = substr($ReplyData, 8, 46);
            $fillData = substr($ReplyData, 54, 54);
            $fillData2 = substr($ReplyData, 108, 52);
            
            if ($driverData == $driveDataStr) {
                if ($driverName == $driveNameStr) {
                    if (($fillData == $fillDataStr) and ($fillData2 == $fillDataStr2)){
                        $this->_system->out("Battery Power Table Verified!");
                        $result = self::PASS;
                    } else {
                        $this->_system->out("Driver Data Does Not Match");
                        $result = self::FAIL;
                    }
                } else {
                    $this->_system->out("Driver Name Does Not Match");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Driver Data Does Not Match");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Not enough data from pwer table read");
            $result = self::FAIL;
        }
        $this->_system->out("");
        $choice = readline("Hit Enter to Continue");
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
                $driverData ="A0000000";  /* Driver, Subdriver, Priority and mode */
                $driverName = "506F727420420000000000000000000000000000000000";
                $fillData  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
                $fillData2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
                $dataVal = $portData.$driverData.$driverName.
                            $fillData.$fillData2;
                $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
                $ReplyData = substr($ReplyData, 0, 14);
                $this->_system->out("Port 1 Reply = ".$ReplyData);
                
                $testReply = substr($ReplyData, 0, 8);
                if ($testReply == "A0000000") {
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
    * Verify Power Table Power Supply Routine
    * 
    * This function does a read power table from the test system
    * load board and compares it to the required power table 
    * setting.
    *
    */
    private function _verifyPowerTablePowerSupply()
    {
        $this->_system->out("\n\r****************************************");
        $this->_system->out("Verifying Power Supply Power Table");
        $this->_system->out("");
        
        $driveDataStr = "E0000000"; 
        $driveNameStr = "506F727420410000000000000000000000000000000000";
        $fillDataStr  = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";  /* 27 bytes */
        $fillDataStr2 = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";    /* 26 bytes */
        
        $idNum = self::DEVICE1_ID;
        $cmdNum = self::READ_POWERTABLE_COMMAND;
        $dataVal = "00";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $replyDataLen = strlen($ReplyData);
        $this->_system->out("Reply Data Length: ".$replyDataLen." characters");
        $this->_system->out("");
        if ($replyDataLen == 160) {
        
            $driverData = substr($ReplyData, 0, 8);
            $driverName = substr($ReplyData, 8, 46);
            $fillData = substr($ReplyData, 54, 54);
            $fillData2 = substr($ReplyData, 108, 52);
            
            if ($driverData == $driveDataStr) {
                if ($driverName == $driveNameStr) {
                    if (($fillData == $fillDataStr) and ($fillData2 == $fillDataStr2)){
                        $this->_system->out("Power Supply Power Table Verified!");
                        $result = self::PASS;
                    } else {
                        $this->_system->out("Driver Data 2 Does Not Match");
                        $result = self::FAIL;
                    }
                } else {
                    $this->_system->out("Driver Name Does Not Match");
                    $result = self::FAIL;
                }
            } else {
                $this->_system->out("Driver Data 1 Does Not Match");
                $result = self::FAIL;
            }
        } else {
            $this->_system->out("Not enough data from pwer table read");
            $result = self::FAIL;
        }
        $this->_system->out("");
        $choice = readline("Hit Enter to Continue");
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
    * Read Configuration Routine
    * 
    * This function reads the configuration bytes from the E2
    * memory of the battery coach board whose serial number is 
    * in the input parameter.
    * 
    *
    */
    private function _readConfigData($deviceID)
    {
        $idNum = $deviceID;
        $cmdNum = self::READCONFIG_COMMAND;
        $dataVal = "";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $length = strlen($ReplyData);

        if ($length >= 62) {
            $serialNum = substr($ReplyData, 0, 10);
            $serialNum = ltrim($serialNum, "0");

            $hwPartNum = substr($ReplyData,10, 10);
            $hwPartNum = $this->_formatHardwarePartNumber($hwPartNum);

            $fwPartNum = substr($ReplyData,20, 16);
            $fwPartNum = $this->_formatFirmwarePartNumber($fwPartNum);

            $spacers   = substr($ReplyData, 36, 8);
            $signature = substr($ReplyData, 44, 6);
            $userCal   = substr($ReplyData, 50, 12);
            
            $this->_system->out("Serial Number        = ".$serialNum);
            $this->_system->out("Hardware Part Number = ".$hwPartNum);
            $this->_system->out("Firmware Part Number = ".$fwPartNum);
            $this->_system->out("Signature Bytes      = ".$signature);
            $this->_system->out("");

            $this->_formatUserCalBytes($userCal);
        } else {
            $this->_system->out("Not Enough Configuration Bytes to Contain User Calibration");
            $this->_system->out("Reply Data:".$ReplyData);
        }
    
        $choice = readline("\n\rHit Enter to Continue");
    
    
    }
    
    /**
    *************************************************************
    * Format Hardware Part Number Routine
    *
    * This function formats the hardware part number string by
    * placing dashes in the appropriate spots and changing the 
    * hex ascii value at the end of the string to a letter.
    *
    * @param $hwNum hex string containing the hardware part number
    *
    * @return $hwStr  formatted hardware part number string
    */
    private function _formatHardwarePartNumber($hwNum)
    {
        $len = strlen($hwNum);
        if ($len == 10 ) {
            $hwStr = substr($hwNum, 0, 4);
            $hwStr .= "-";
            $hwStr .= substr($hwNum, 4, 2);
            $hwStr .= "-";
            $hwStr .= substr($hwNum, 6, 2);

            $rev = substr($hwNum, 8,2);
            $rev = "0x".$rev;
            $revChar = chr($rev);

            $hwStr.= "-". $revChar;
        } else {
            $hwStr = $hwNum;
        }

        return $hwStr;

    }
    
    /**
    *************************************************************
    * Format Firmware Part Number Routine
    *
    * This function formats the firmware part number string by
    * placing dashes in the appropriate spots, changing the 
    * hex ascii value to a letter and placing decimal points 
    * in for version value.
    *
    * @param $fwNum hex string containing the firmware part number
    *
    * @return $fwStr  formatted firmware part number string
    */
    private function _formatFirmwarePartNumber($fwNum)
    {
        $len = strlen($fwNum);
        if ($len == 16) {
            $fwStr = substr($fwNum, 0, 4);
            $fwStr .= "-";
            $fwStr .= substr($fwNum, 4, 2);
            $fwStr .= "-";
            $fwStr .= substr($fwNum, 6, 2);
            $fwStr .= "-";

            $rev = substr($fwNum, 8,2);
            $rev = "0x".$rev;
            $revChar = chr($rev);

            $fwStr.= $revChar;
            
            $ver = substr($fwNum, 10, 2);
            $intVer = hexdec($ver);
            $fwStr .= " ".strval($intVer);
            $fwStr .= ".";

            $ver = substr($fwNum, 12, 2);
            $intVer = hexdec($ver);
            $fwStr .= strval($intVer);
            $fwStr .= ".";

            $ver = substr($fwNum, 14, 2);
            $intVer = hexdec($ver);
            $fwStr .= strval($intVer);
        } else {
            $fwStr = $fwNum;
        }

        return $fwStr;
    }
    
     /**
    *************************************************************
    * Format User Calibration Bytes Routine
    *
    * This function separates the User Calibration bytes into
    * the ADC offset, ADC gain, DAC offset and DAC gain values
    * does range checking and displays them.
    * 
    * @param $usrCal  The string of user calibration bytes.
    *
    * @return void
    */
    private function _formatUserCalBytes($usrCal)
    {

        $length = strlen($usrCal);
        if ($length == 12) {

            $adcOffset = substr($usrCal, 2, 2);
            $adcOffset .= substr($usrCal, 0, 2);
            $adcOffset = "0x".$adcOffset;

            $adcGain = substr($usrCal, 6,2);
            $adcGain .= substr($usrCal, 4, 2);
            $adcGain = "0x".$adcGain;

            $dacOffset = "0x".substr($usrCal, 8, 2);
            $dacGain = "0x".substr($usrCal, 10, 2);

            if (($adcOffset < self::ADC_POFFSET_MAX) || 
                ($adcOffset > self::ADC_NOFFSET_MAX)) {
                $this->_system->out("ADC Offset Valid: ".$adcOffset);
            } else {
                $this->_system->out("*** ADC Offset Invalid: ", $adcOffset);
            }

            /* ADC_GAINCOR_MIN = "0x0400" */
            /* ADC_GAINCOR_MAX = "0x0FFF" */
            if (($adcGain >= self::ADC_GAINCOR_MIN) &&
                ($adcGain <= self::ADC_GAINCOR_MAX)) { 
                $this->_system->out("ADC Gain Valid  : ".$adcGain);
            } else {
                $this->_system->out("*** ADC Gain Invalid  : ". $adcGain);
            }

            if (($dacOffset <= self::DAC_NOFFSET_MAX) || 
                (($dacOffset > self::DAC_POFFSET_MIN) && 
                 ($dacOffset < self::DAC_POFFSET_MAX))) {
                $this->_system->out("DAC Offset Valid: ".$dacOffset);
            } else {
                $this->_system->out("*** DAC Offset Invalid: ".$dacOffset);
            }

            if (($dacGain <= self::DAC_PGAINCOR_MAX) ||
                (($dacGain > self::DAC_NGAINCOR_MIN) &&
                 ($dacGain < self::DAC_NGAINCOR_MAX))) {
                $this->_system->out("DAC Gain Valid  : ".$dacGain);
            } else {
                $this->_system->out("*** DAC Gain Invalid : ".$dacGain);
            }
        } else {
            $this->_system->out("Not enough characters for proper formatting.");
            $this->_system->out("User Cal Bytes: ".$usrCal);
        }

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
        //$this->_system->out("Port ".$chanNum." Control Channel Reply = ".$ReplyData);
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
        //$this->_system->out("Set Control Channel Reply = ".$ReplyData);

    }
   

    /*****************************************************************************/
    /*                                                                           */
    /*                 F I R M W A R E    R O U T I N E S                        */
    /*                                                                           */
    /*****************************************************************************/

    /**
    *******************************************************
    * Load Release Candidate Application Firmware Routine
    * 
    * this function loads the release candidate firmware into 
    * each of the test system battery coach boards.
    *
    */
    private function _loadReleaseFirmware()
    {
        
        $this->display->displayHeader("Loading Application Firmware");

        $this->_system->out(" Make sure you have downloaded the latest");
        $this->_system->out(" release candidate firmware for testing. ");
        $this->_system->out("");
        $this->_system->out(" If you have not, go to:");
        $this->_system->out("  hugnet.int.hugllc.com/downloads/rcfirmware/");
        $this->_system->out("  download the release candidate application");
        $this->_system->out("  for the 104603 and then continue.");

        $response = readline("\n\rHit Enter to Continue:");

        $firmwarefile = $this->_getReleaseFirmwareFileName();
        $this->_firmwareVersion = substr($firmwarefile, 0, 22);

        $hugnetLoad = "../bin/./hugnet_load";
        $firmwarepath = "~/Downloads/".$firmwarefile;

        $Prog = $hugnetLoad." -i ".dechex(self::DEVICE1_ID)." -D ".$firmwarepath;
        system($Prog, $return);

        if ($return == 0) {
            $Prog = $hugnetLoad." -i ".dechex(self::DEVICE2_ID)." -D ".$firmwarepath;
            system($Prog, $return);
            
            if ($return == 0) {
                $Prog = $hugnetLoad." -i ".dechex(self::DEVICE3_ID)." -D ".$firmwarepath;
                system($Prog, $return);
                
                if ($return == 0) {
                    $result = self::PASS;
                } else {
                    $this->_system->out("FAILED TO LOAD FIRMWARE INTO 3rd DEVICE");
                    $result = self::FAIL;
                }
                
            } else {
                $this->_system->out("FAILED TO LOAD FIRMWARE INTO 2nd DEVICE!");
                $result = self::FAIL;
            }
            
        } else {
            $this->_system->out("FAILED TO LOAD FIRMWARE INTO 1ST DEVICE!");
            $result = self::FAIL;
        } 

        return $result;
    }

    /**
    ***********************************************************
    * Get Release Firmware File Name Routine
    *
    * This function prompts user to enter release candidate 
    * file name that is in the Downloads directory.  It then
    * checks the file name for proper target and firmware 
    * part number and asks user to verify correctness.
    *
    */
    private function _getReleaseFirmwareFileName()
    {
        $done = false;

        do {
            $this->display->clearScreen();
            $this->display->displaySMHeader("   OBTAINING FIRMWARE FILE NAME   ");
            $this->_listFirmwareFiles();
            $firmwareVersion = readline("Enter the release candidate file name: ");
            $fnameLen = strlen($firmwareVersion);
            $testString = substr($firmwareVersion, 0, 17);
            $testVer = substr($firmwareVersion, 17, 5);
            $testExt = substr($firmwareVersion, $fnameLen -3, 3);

            if (($testString == "104603-00393801C-") and ($testExt == ".gz")) {
                $this->_system->out("RC Filename: ".$firmwareVersion."\n\r");
                $this->_system->out("RC Version : ".$testVer."\n\r");
                $response = readline("Is this correct?(Y/N): ");
                if (($response == "Y") || ($response == "y")) {
                    $firmwarefile = $firmwareVersion;
                    $done = true;
                } else {
                    $done = false;
                }

            } else {
                $this->_system->out("Error in Firmware File Name.");
                $this->_system->out("Please re-enter the file name.");
            }

        } while (!$done);

        return $firmwarefile;
    }


    /**
    *********************************************************************
    * List Release Firmware Files Routine
    *
    * This function lists the release candidate firmware files found 
    * in the Downloads directory.
    *
    */
    private function _listFirmwareFiles()
    {
        $this->_system->out("");
        $this->_system->out("List of Downloaded 104603 Application Packages");
        $this->_system->out("----------------------------------------------");

        $myCmd = "ls ~/Downloads/104603-00393801C*";
        system($myCmd, $return);
        $this->_system->out("");

    }
    
    
    /**
    ************************************************************************
    * Check Firmware Version Routine
    * 
    * This function sends a command to the Device whose serial number 
    * is passed in to return the firmware part number and version.  It then
    * compares the board version with the release candidate version to 
    * verify the correct firmware is loaded and running on the device.
    *
    */
    private function _verifyFirmwareVersion($SerNum)
    {
    
        $this->_system->out("Verifying Serial Number: ".dechex($SerNum)." Firmware Version.");
        $idNum = $SerNum;
        $cmdNum = self::READCONFIG_COMMAND;
        $dataVal = "";
        $ReplyData = $this->_sendpacket($idNum, $cmdNum, $dataVal);
        
        $ReplyVersion = substr($ReplyData, 10, 6)."-".substr($ReplyData, 20, 8);
        
        
        $letter = substr($ReplyData, 28, 2); 
        $letter = "0x".$letter;
        $letterChar = chr($letter);
        $ReplyVersion .= $letterChar."-";
        
        
        $ver = substr($ReplyData, 30, 2);
        $intVer = hexdec($ver);
        $ReplyVersion .= strval($intVer);
        $ReplyVersion .= ".";

        $ver = substr($ReplyData, 32, 2);
        $intVer = hexdec($ver);
        $ReplyVersion .= strval($intVer);
        $ReplyVersion .= ".";

        $ver = substr($ReplyData, 34, 2);
        $intVer = hexdec($ver);
        $ReplyVersion .= strval($intVer);

        if ($ReplyVersion == $this->_firmwareVersion) {
            $this->_system->out("Firmware Version Verified!");
            $result = self::PASS;
        } else {
            $result == self::FAIL;
        }
        
        return $result;
    }


    /**
    **********************************************************
    * Display Error Log Data Routine
    *
    * This routine takes the hex data string and displays
    * the time and data stamp, the logged error and the 
    * additional information data.
    */
    private function _displayErrorLogData($errorData)
    {
        $datalen = strlen($errorData);
        $dateData = substr($errorData, 0, 8);

        print "dateData = ".$dateData."\n\r";

        $errorNum = substr($errorData, 8, 2);
        $infoData = substr($errorData, 10, datalen-1);

        $hexDate = substr($dateData, 6, 2);
        $hexDate .= substr($dateData, 4, 2);
        $hexDate .= substr($dateData, 2, 2);
        $hexDate .= substr($dateData, 0, 2);

        print "hexDate = ".$hexDate."\n\r";

        $intDate = hexdec($hexDate);

        print "intDate = ".$intDate."\n\r";

        $localtime = localtime($intDate, true);
        $year = $localtime[tm_year] + 1900;
        $month = $localtime[tm_mon] + 1;
        $mday = $localtime[tm_mday];

        $hour = $localtime[tm_hour];
        $min  = $localtime[tm_min];
        $sec  = $localtime[tm_sec];

        $timeStamp = $month."/".$mday."/".$year."  ".$hour.":".$min.":".$sec;

        $this->_system->out("Timestamp: ".$timeStamp);

        $this->_system->out("Error Number".$errorNum);

    }


    /*****************************************************************************/
    /*                                                                           */
    /*               C O N V E R S I O N    R O U T I N E S                      */
    /*                                                                           */
    /*****************************************************************************/

    /**
    **********************************************************
    * Convert Data Value Routine
    *
    * This function takes the reply string from the read 
    * sensor data command and converts the hex data string
    * into a floating point value.  It works for all sensor 
    * data reads except the raw status.
    *
    */
    private function _convertDataValue($dataString)
    {

        $portValue = 0.0;

        $dataLen = strlen($dataString);
        
        if ($dataLen == 8) {
            /* convert hex string to little endian */
            for ($i = 0; $i < 4; $i++) {
                $tempStr .= substr($dataString, ($datalen - 2) - (2*$i), 2);
            }
            
            /* test for negative value */
            $testhex = substr($tempStr, 0, 2);
            $testdec = hexdec($testhex);
            

            if ($testdec > 127) {
                /* if MSBit set then use twos complement conversion */
                $pVal = $this->_twosComplement_to_negInt($tempStr);
            } else {
                /* convert hex string to decimal value */
                $pVal = hexdec($tempStr);
            }
            /* convert from milli-Units to Units */
            $portValue = $pVal / 1000;
        }


        return $portValue;
    }


    /**
    ***************************************************
    * Twos Complement to Negative Integer
    *
    * This function takes a 4 byte twos complement
    * number and returns the negative integer values.
    *
    * @param $hexVal  input 4 byte hex string
    *
    * @return $retVal a signed integer number.
    */
    public function _twosComplement_to_negInt($hexVal)
    {
        $bits = 32;

        $value = (hexdec($hexVal));
        $topBit = pow(2, ($bits-1));
        
        
        if (abs($value & $topBit) == $topBit) {
            $value = -(pow(2, $bits) - $value);
        }

        return $value;
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
