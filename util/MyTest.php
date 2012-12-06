<?php
/**
 * Tests, serializes, and loads bootloader into HUGnetLab endpoints
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2012 Hunt Utilities Group, LLC
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


/***************************************************************************/
/*                                                                         */
/*                              M A I N                                    */
/*                                                                         */
/***************************************************************************/

/***************************************************************************
* PHP doesn't seem to have a "main" like C so I just added this comment to 
* indicate the beginning of the main.
*/


print "==== Loading HUGnetLab Test Firmware and Beginning Test ====\n";

/* Load the test firmware through the JTAG emulator */
$Prog = "~/code/HOS/toolchain/bin/openocd -f ~/code/HOS/src/003937test/program.cfg";

exec($Prog, $out, $return);


/******************************************************
* ping the endpoint with serial number 0x000020.
* this will eventually be a function call to test
* board.
*/

$testResult = pingEndpoint();
if ($testResult == true) {
    print "Board Test passed!\n";

    /* program Serial and Hardware numbers */
    $retVal = write_SerialNum_HardwareVer();
    
    if ($retVal == true) {

        $waitForReset = readline("\nHit the resest and press enter!\n");

        $Prog = "~/code/HOS/toolchain/bin/openocd -f ~/code/HOS/src/003937boot/program.cfg";

        exec($Prog, $out, $return);
    } else {
        print"     === Board SN & HW Programming Failed ===\n";
        print"Please verify serial number and hardware partnumber.\n";
    }


} else {
    print "       === Board Test Failed ===\n";
    print "Please repair board before retesting.\n";
}


print "Test and Program End!\n";
exit  (0);



/***************************************************************************/
/*                                                                         */
/*                       F U N C T I O N S                                 */
/*                                                                         */
/***************************************************************************/

/************************************************************
* This function reads the input serial number and Hardware
* version, programs them into flash and then verifies them
* the programming by reading them out.
*/

function write_SerialNum_HardwareVer()
{

    $result = false;
 

   
    $SNresponse = readline("\nEnter the serial number for this board: ");
    $SNresponse = str_pad($SNresponse, 10, "0", STR_PAD_LEFT);

    $HWresponse = readline("\nEnter Hardware version (A,B or C): ");

    if ($HWresponse == "A") {
        $HWresponse = "0039370141";
    } else if ($HWresponse == "B") {
        $HWresponse = "0039370142";
    } else if ($HWresponse == "C") {
        $HWresponse = "0039370143";
    } else {
        $exit = true;
    }

    $response = $SNresponse.$HWresponse;

    if (strlen($response) == 20) {
        $GoProg = readline("\nProgram data is : ".$response." continue (Y/N)? ");
        if (($GoProg == 'Y') || ($GoProg == 'y')) {
            $idNum = 0x20;
            $cmdNum = 0x1c;
            $dataVal = $response;
            $replyData = Send_Packet($idNum, $cmdNum, $dataVal);
            print "\nReply Data : ".$replyData."\n";
            $result = true;
        } else {
            print "Program serial and hardware numbers aborted!\n";
        }
    } else {
        print "Invalid program data, programming aborted!\n";
    }

    $idNum = 0x20;
    $cmdNum = 0x0c;
    $dataVal = "76000A";

    $SerialandHW = Send_Packet($idNum, $cmdNum, $dataVal);

    print "Serial and HW number = ".$SerialandHW."\n";

    return ($result);

}


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

/********************************************
* this function will read the serial number
* and hardware number from flash.
*/

function Send_Packet($Sn, $Cmd, $DataVal)
{

    require_once 'HUGnetLib/HUGnetLib.php';

    $config = HUGnetLib::Args(
        array(
            "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
            "D" => array("name" => "Data", "type" => "string", "args" => true),
            "C" => array(
                "name" => "Command", "type" => "string", "args" => true,
                "default" => "FINDPING"
            ),
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

    Sends arbitrary packets to endpoints.

    Usage: ".$cli->system()->get("program")." -i <DeviceID> [-C <Command>] [-D <data>] [-v] [-f <file>]
    Arguments:
        -i <DeviceID>   The device ID to use.  Should be a hex value up to 6 digits
        -C <Command>    Command to send the endpoint.  FINDPING is the default
        -D <data>       ASCII hex data to send in the ping.  Up to 255 bytes.
        -v              Increment the verbosity
        -f <file>       The config file to use",
        $config->h
    );

    $config->i = $Sn;
    $config->C = $Cmd;
    $config->D = $DataVal;

    print "Data is ".$config->D."\n";


    $dev = $cli->system()->device($config->i);
    $pkt = $cli->system()->device($config->i)->action()->send(
        array(
            "Command" => $config->C,
            "Data" => $config->D,
        ),
        null,
        array(
            "timeout" => $dev->get("packetTimeout")
        )
    );

    if (is_object($pkt)) {
        print "From: ".$pkt->From();
        print " -> To: ".$pkt->To();
        print "  Command: ".$pkt->Command();
        print "  Type: ".$pkt->Type();
        print "\r\n";
        $data = $pkt->Data();
        if (!empty($data)) {
            print "Data: ".$data."\r\n";
        }
        $data = $pkt->Reply();
        if (is_null($data)) {
            print "No Reply\r\n";
            $data = "No Reply";
        } else if (!empty($data)) {
            print "Reply Data: ".$data."\r\n";
        } else {
            print "Empty Reply\r\n";
            $data = "Empty Reply";
        }
    }

    return ($data);
}

?>