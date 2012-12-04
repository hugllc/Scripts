<?php
/**
 * Tests, serializes, and loads bootloader into HUGnetLab endpoints
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Test
 * @author     Jeff Liesmaki <jeffl@hugllc.com>
 * @copyright  2007-2012 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
 
/****************************************************************************
* List below are the objectives for this PHP script:
*        I.  Initialize the JTAG emulator - okay
*        II. Load and run test firmware
*            A. Should initial test load enough firmware to send and 
*               and receive packets through serial port? Yes.
*            B. Test Serial number has been set in the test program
*               itself to 0x000020.
*        III Program endpoint with serial number and hardware version
*            A. This will be done through a command to test firmware
*               which will cause a flash_write to the correct location.
*        IV  Load the bootloader program
*            A. This should use current 003937boot install program.cfg
*
*/


print "Hello World, lets try starting up the JTAG through openocd!\n";

/******************************************************
* Load the test firmware through the JTAG emulator
*/
$Prog = "~/code/HOS/toolchain/bin/openocd -f ~/code/HOS/src/003937test/program.cfg";

exec($Prog, $out, $return);


/******************************************************
* ping the endpoint with serial number 0x000020
*/

if (pingEndpoint() == true) {
    print "Yeah! Test passed!\n";
} else {
    print "Boo! Test failed\n";
}

/******************************************************
* The next step is to see if I can program or erase 
* the flash area where the serial number and hardware
* numbers are stored.
/


/* print "Press the reset on the emulator adaptor board.\n";
$response = readline( "\nIs the amber LED on? (y/n): ");

if (($response[0] == 'y') || ($response[0] == 'Y')){
    print "yes!";
} else {
    print "no!";
} */



print "Finished!\n";
exit  (0);


/**************************************
* This function pings the endpoint 
* with the test serial number and 
* checks to see that the endpoint 
* responds.
*/

function pingEndpoint( )
{
/** Packet log include stuff */
require_once 'HUGnetLib/HUGnetLib.php';

$config = HUGnetLib::Args(
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
        "c" => array("name" => "count", "type" => "int", "args" => true, "default" => 10000),
        "F" => array("name" => "find", "type" => "bool", "default" => false),
    ),
    "args",
    $argv
);
$config->addLocation("/usr/share/HUGnet/config.ini");
$cli = HUGnetLib::ui($config, "Daemon");
$cli->help(
    $cli->system()->get("program")."
Copyright Hunt Utilities Group, LLC
HUGnet Scripts v".trim(file_get_contents("HUGnetScripts/VERSION.TXT", true))."  "
."HUGnetLib v".$cli->system()->get("version")."

Sends ping packets to endpoints.

Usage: ".$cli->system()->get("program")." -i <DeviceID> [-vF] [-f <file>] [-c <count>] [-D <data>]
Arguments:
    -i <DeviceID>   The device ID to use.  Should be a hex value up to 6 digits
    -v              Increment the verbosity
    -F              Use 'find ping' instead of standard ping
    -c <count>      Send <count> pings
    -D <data>       ASCII hex data to send in the ping.  Up to 255 bytes.
    -f <file>       The config file to use",
    $config->h
);

$config->i = 0x20;

$cli->out("Starting ".$cli->system()->get("program"));

$dev = $cli->system()->device($config->i);

for ($i = 0; $i < 10; $i++) {
    $time = microtime(true);
    $pkt = $dev->network()->ping(
        $config->F,
        null,
        null,
        array(
            "tries" => 1,
            "find" => false,
        )
    );
    $time = microtime(true) - $time;
    if (is_string($pkt->reply())) {
        print $pkt->length()." bytes from ".$pkt->to()." seq=$i ";
        print "ttl=".$dev->get("packetTimeout")." time=".round($time, 4)."\n";
        $result = true;
        $i = 10; /* exit loop if we get a response */
    } else {
        print "No Reply seq $i\n";
        $result = false;
    }
    $sleep = (1 - $time) * 1000000;
    if ($sleep > 0) {
        usleep($sleep);
    }
}

    return ($result);
}



?>