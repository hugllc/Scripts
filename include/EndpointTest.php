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
/** This is the HUGnetLab endpoint test */
require_once "E003937Test.php";

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

    private $_fixtureTest;
    private $_device;
    private $_goodDevice;


    /*
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
        $exitTest = false;
        $result;

        do{

            $selection = $this->_mainMenu();

            if (($selection == "A") || ($selection == "a")) {
                $this->_testMain();
            } else if (($selection == "B") || ($selection == "b")){
                $this->_cloneMain();
            } else if (($selection == "C") || ($selection == "c")){
                $this->_troubleshootMain();
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
        $this->_clearScreen();
        $this->_printHeader();
        $this->out();
        $this->out("A ) Test 003937 HUGnetLab Endpoint");
        $this->out("B ) Clone, Test and Program");
        $this->out("C ) Troubleshoot");
        $this->out("D ) Exit");
        $this->out();
        $choice = readline("\n\rEnter Choice(A,B,C or D): ");
        
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
    public function _clearScreen()
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
        $this->out("*           HUGnet Endpoint Test & Program Tool            *");
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
    public function _displayPassed()
    {
        $this->out("\n\r");
        $this->out("\n\r");

        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*      B O A R D   T E S T   P A S S E D !       *");
        $this->out("*                                                *");
        $this->out("**************************************************");

        $this->out("\n\r");
        $this->out("\n\r");

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
    public function _displayFailed()
    {
        $this->out("\n\r");
        $this->out("\n\r");

        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*      B O A R D   T E S T   F A I L E D !       *");
        $this->out("*                                                *");
        $this->out("**************************************************");

        $this->out("\n\r");
        $this->out("\n\r");

    }


    /*****************************************************************************/
    /*                                                                           */
    /*                     T E S T   R O U T I N E S                             */
    /*                                                                           */
    /*****************************************************************************/

    
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
        
        $this->_clearScreen();
        $this->out("\n\r");
        $this->out("\n\r");
       
        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*          C L O N E   R O U T I N E             *");
        $this->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->out("*                                                *");
        $this->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");


    }

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

        $this->_clearScreen();
        $this->out("\n\r");
        $this->out("\n\r");
       

        $this->out("**************************************************");
        $this->out("*                                                *");
        $this->out("*    T R O U B L E S H O O T   R O U T I N E     *");
        $this->out("*      U N D E R   C O N S T R U C T I O N       *");
        $this->out("*                                                *");
        $this->out("**************************************************");


        $choice = readline("\n\rHit Enter To Continue: ");


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
        $sys = $this->system();
        $this->_fixtureTest = E003937Test::factory($config, $sys);
        $result =  $this->_fixtureTest->runTest();

        if ($result == true) {
            $this->_displayPassed();
        }
    }



    /**
    ************************************************************
    * Troubleshoot Endpoint Routine
    *
    * This function runs the tests in the endpoint test firmware
    * and returns the results.
    *
    * @return boolean $troubleshootResult
    *
    */
    private function _troubleshootEndpoint()
    {
        
        $Result = true;
   

        return $Result;
    }





}
?>
