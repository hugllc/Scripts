<?php
/**
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
/** This is the HUGnet endpoint test */
require_once "E003928Test.php";

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
class TestDisplay
{




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
        $this->clearScreen();
        $this->_printHeader($heading);
        $this->out();

        $items = count($menuArray);

        for ($i = 0;$i < $items; $i++) {

            /* convert numbers to capital letters */
            $menuChar = chr($i+65);
           $menuItem = $menuChar." ) ".$menuArray[$i];
            $this->out($menuItem);
        }


        /* convert number to capital letter */
        $menuChar = chr($i+65);
        $menuItem = $menuChar." ) Exit";
        $this->out($menuItem);
        $this->out();

        
        $choice = readline("\n\rEnter Choice(A - ".$menuChar."): ");
        
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

        $outstring += .$heading;
        for ($i=0;$i<$blankspc;$i++) {
            $outstring .= " ";
        }

        $outstring .= "*";

        

        $this->out(str_repeat("*", 60));
       
        $this->out("*                                                          *");
        $this->out($outstring);
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
    public function displayFailed()
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




}
?>
