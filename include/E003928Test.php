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
 * @author     Scott Price <prices@hugllc.com>
 * @author     Jeff Liesmaki <jeffl@hugllc.com>
 * @copyright  2014 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    Release: 0.9.7
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class E003928Test
{
    /** predefined endpoint serial number used in test firmware **/
    const TEST_ID = 0x116;
    
    /** packet commands to test firmware **/
    const TEST_ANALOG_COMMAND  = 0x20;
    const SET_DIGITIAL_COMMAND = 0x25;
    const TEST_DIGITAL_COMMAND = 0x26;
    const CONFIG_DAC_COMMAND   = 0x27;
    const SET_DAC_COMMAND      = 0x28;
    
    /* DAC Configuration bytes */
    const DAC_CONFIG_IREF = "0010";
    const DAC_CONFIG_AREF = "0013";
    const DAC_CONFIG_16_IREF = "0018";
    const DAC_CONFIG_16_AREF = "001B";

    /** path to AVR programmer command **/
    private $_programInitPath = "~/code/HOS/";

    /** path to hugnet load script forloading endpoint program **/
    private $_programloadPath = "~/code/Scripts/bin";

    /** command to load intial program into AVR endpoint through programmer **/
    private $_programInitCommand = "sudo make 003928-install SN=0x0000000020";

    /** command to load current release code into AVR endpoint **/
    private $_programLoadCommand = " ./hugnet_load -i";

    private $_programDownloadPath = "~/Downloads/003928-00393801C-0.2.1.gz";

    private $_device;
    private $_system;

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

    /*
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
        $obj = new E003928Test($config, $sys);
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

            $selection = $this->_E003928mainMenu();

            if (($selection == "A") || ($selection == "a")) {
                $this->_runTest();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_cloneMain();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_troubleshootMain();
            } else {
                $exitTest = true;
                $this->_system->out("Exit 003928 Test");
            }

        } while ($exitTest == false);
    }

    /**
    ************************************************************
    * Main 003937 Menu Routine
    * 
    * This is the main menu routine for 003937 HUGnetLab 
    * endpoint.  It displays the menu options, reads the 
    * user input choice and calls the appropriate routine in 
    * response.
    *
    * @return string $choice
    *
    */
    private function _E003928mainMenu()
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

        $this->_system->out("Test Routine Not Done!\n\r");

        $this->_system->out("pinging test endpoint!\n\r");
        $Result = $this->_pingEndpoint(self::TEST_ID);
        
        if ($Result) {
            $this->_system->out("Good pinging!\n\r");
        } else {
            $this->_system->out("Bad ping shame on you!\n\r");
        }

        $this->_system->out("Okay let's try programming!\n\r");

        $Prog = $this->_programInitPath." ".$this->_programInitCommand;

        exec($Prog, $out, $return);

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
    * Display Board Passed Routine
    *
    * This function displays the board passed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayPassed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   P A S S E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out("\n\r");

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
    private function _displayFailed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   F A I L E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out("\n\r");

    }

    /**
    ************************************************************
    * Display Boot Load Failed Routine
    *
    * This function displays the boot load failed message in a
    * visually obvious way so the user cannot miss it.
    *
    * @return void
    *
    */
    private function _displayBootFailed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("*****************************************");
        $this->_system->out("*                                       *");
        $this->_system->out("*       Load Boot Loader Failed!        *");
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
    private function _displayBoardProgramFailed()
    {
        $this->_system->out("\n\r");

        $this->_system->out("*********************************************");
        $this->_system->out("*                                           *");
        $this->_system->out("*     Board SN & HW Programming Failed      *");
        $this->_system->out("*             Please verify:                *");
        $this->_system->out("*   Serial number and hardware partnumber   *");
        $this->_system->out("*                                           *");
        $this->_system->out("*********************************************");

        $this->_system->out("\n\r");
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
        $this->_system->out("*        HUGnetLab 003928 Test & Program Tool              *");
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
                $this->_troubleshootAnalog();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_troubleshootDigital();
            } else if (($selection == "C") || ($selection == "c")) {
                $exitTest = true;
                $this->_system->out("Exit Troubleshooting");
            }

        } while ($exitTest == false);
    }

    /**
    ************************************************************
    * Troubleshoot 003937 Menu Routine
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
        $this->_system->out("A ) Analog Tests");
        $this->_system->out("B ) Digital Tests");
        $this->_system->out("C ) Exit");
        $this->_system->out("\n\r");
        $choice = readline("\n\rEnter Choice(A,B or C): ");
        
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

        EndpointTest::clearScreen();
        $this->_system->out("\n\r");
       

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*     T R O U B L E S H O O T   A N A L O G      *");
        $this->_system->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");
     }


    /**
    ************************************************************
    * Troubleshoot Digital Tests Routine
    *
    * This is the main routine for troubleshooting the digital
    * I/O on an existing endpoint.  It will have the option 
    * of single stepping through the tests or looping on a 
    * specific test.
    * 
    * @return void
    *
    */
    private function _troubleshootDigital()
    {

        EndpointTest::clearScreen();
        $this->_system->out("\n\r");
       

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*    T R O U B L E S H O O T   D I G I T A L     *");
        $this->_system->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");
    }


    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*****************************************************************************/


    /*****************************************************************************/
    /*                                                                           */
    /*             A N A L O G - T O - D I G I T A L   T E S T S                 */
    /*                                                                           */
    /*****************************************************************************/



    /*****************************************************************************/
    /*                                                                           */
    /*                      D I G I T A L   T E S T S                            */
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
        var_dump($result);
        return true;
    }

}
?>
