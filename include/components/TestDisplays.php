<?php 

/**
 * This file houses the TestDisplay class
 *
 * PHP Version 5
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2013 Hunt Utilities Group, LLC
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
 * @copyright  2013 Hunt Utilities Group, LLC
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
 * This code tests, serializes and programs HUGnetLab endpoints with 
 * bootloader code.
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
 * @copyright  2013 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    Release: 0.9.7
 * @link       http://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class TestDisplays
{

    private $_system;

    /*
    * Sets our configuration
    *
    * @param mixed &$config The configuration to use
    */
    protected function __construct(&$config, &$sys)
    {
        $this->_system = &$sys; 

    }

    /**
    * Creates the object
    *
    * @param array &$config The configuration to use
    *
    * @return object
    */
    static public function &factory(&$config = array(), &$sys)
    {
        $obj = new TestDisplays($config, $sys);
        return $obj;
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
    ********************************************************
    * Print Header Routine
    *
    * This function prints out the heading passed to it
    * inside a box of stars.
    *
    * @param $heading string contain heading text
    *
    * @return void
    */
    public function printHeader($heading)
    {
        $length = strlen($heading);

        /* if not divisible by 2, then add a space */
        if (($length % 2) != 0) {
            $heading .= " ";
            $length++;
        }

        $remainder = 60 - $length;

        $blankspc = $remainder/2 -1;

        $outstring = "*";
        for ($i=0;$i<$blankspc;$i++) {
            $outstring .= " ";
        }

        $outstring .= $heading;
        for ($i=0;$i<$blankspc;$i++) {
            $outstring .= " ";
        }
        $outstring .= "*";

        $this->_system->out(str_repeat("*", 60));
        $this->_system->out("*                                                          *");
        $this->_system->out($outstring);
        $this->_system->out("*                                                          *");
        $this->_system->out(str_repeat("*", 60));

        $this->_system->out("\n\r\n\r");

    }

    /**
    ************************************************************
    * Display Header and Menu Routine
    *
    * This function displays the test and program tool header
    * and a menu which allows you to exit the program.
    *
    * @return string $choice menu selection
    */
    public function displayMenu($heading, $menuArray)
    {
        $this->printHeader($heading);

        $items = count($menuArray);

        for ($i = 0;$i < $items; $i++) {
            /* convert numbers to capital letters */
            $menuChar = chr($i+65);

            $menuItem = $menuChar." ) ".$menuArray[$i];
            $this->_system->out($menuItem);
        }
        
        /* convert number to capital letter */
        $menuChar = chr($i+65);
        $menuItem = $menuChar." ) Exit";
        $this->_system->out($menuItem."\n\r");

        
        $choice = readline("\n\rEnter Choice(A - ".$menuChar."): ");
        
        return $choice;
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
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   P A S S E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out ("\n\r");

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
        $this->_system->out("\n\r");

        $this->_system->out("**************************************************");
        $this->_system->out("*                                                *");
        $this->_system->out("*      B O A R D   T E S T   F A I L E D !       *");
        $this->_system->out("*                                                *");
        $this->_system->out("**************************************************");

        $this->_system->out ("\n\r");

    }



}


?>
 