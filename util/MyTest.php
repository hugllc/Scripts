<?php
/**
 * Tests, serializes, and loads bootloader into HUGnetLab endpoints
 *
 * PHP Version 5
 *
 * List below are the objectives for this PHP script:
 *    I.   Initialize the JTAG emulator
 *    II.  Load and run test firmware
 *           A. Test Serial number has been fixed in the test program
 *              itself to 0x000020.
 *           B. Initial test is simple response to a ping.
 *    III. Program endpoint with serial number and hardware version
 *           A. This will be done through a command to test firmware
 *              which will cause a flash_write to the correct location.
 *    IV.  Load the bootloader program
 *           A. This should use current 003937boot install program.cfg
 *
 *
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
 

 require_once 'HUGnetLib/HUGnetLib.php';


/***************************************************************************/
/*                                                                         */
/*                              M A I N                                    */
/*                                                                         */
/***************************************************************************/

/***************************************************************************
* PHP doesn't seem to have a "main" like "C" so I just added this comment to 
* indicate the beginning of the main.
*/

$selection = Main_Menu();

if (($selection == "A") || ($selection == "a")){

   $StartSN = Get_Serial_Number();
   print "==== Loading HUGnetLab Test Firmware and Beginning Test ====\n";

    /* Load the test firmware through the JTAG emulator */
   /* $Prog = "~/code/HOS/toolchain/bin/openocd -f ~/code/HOS/src/003937test/program.cfg";

    exec($Prog, $out, $return);*/

    /**
    ******************************************************
    * ping the endpoint with serial number 0x000020.
    * this will eventually be a function call to test
    * board.
    */

   /* $testResult = pingEndpoint();

    if ($testResult == true) {
        print "Board Test passed!\n"; */

        /* program Serial and Hardware numbers */
     /*   $retVal = write_SerialNum_HardwareVer();
        
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

    */
    print "Test and Program End!\n";
} else {
    print "Exit Test Tool\n\r";
}

exit  (0);



/***************************************************************************/
/*                                                                         */
/*                       F U N C T I O N S                                 */
/*                                                                         */
/***************************************************************************/

/**
************************************************************
* @brief Display Header and Menu Routine
*
* This function displays the test and program tool header
* and a menu which allows you to exit the program.
*
* @return void
*/

function Main_Menu()
{
    Clear_Screen();
    print_header();
    print "\n\r";
    print "A ) Test, Serialize and Program\n\r";
    print "B ) Exit\n\r";
    print "\n\r";
    $choice = readline("\n\rEnter Choice(A or B): ");
    
    return ($choice);


}

/**
************************************************************
* @brief Clear Screen Routine
* 
* This function clears screen area by outputting 24 carriage 
* returns and line feeds.
*
* @return void
* 
*/

function Clear_Screen()
{

    for ($i=0; $i<24; $i++){
        print "\n\r";
    }
}


/**
************************************************************
* @brief Print Header Routine
*
* The function prints the header box and title.
*
* @return void
*
*/

function print_header()
{
   for ($i=0; $i<41; $i++){
        print "*";
    }
    print "\n\r";
    print "*                                       *\n\r";
    print "*    HUGnetLab Test & Program Tool      *\n\r";
    print "*                                       *\n\r";

    for ($i=0; $i<41; $i++) {
        print "*";
    }
    print "\n\r";

}
    

/**
************************************************************
* @brief Get Starting Serial Number Routine
*
* This function asks user to input a starting serial number
* in the correct format.  The program then uses the serial
* number for the first board and is able to increment the 
* value for additional boards.
*
* @return $SN the serial number in integer form.
*/

function Get_Serial_Number()
{
    do {
        Clear_Screen();
        print "Enter a hex value for the starting serial number\n\r";
        $SNresponse = readline("in the following format- 0xhhhh: ");
        print "\n\r";
        print "Your starting serial number is: ".$SNresponse."\n\r";
        $response = readline("Is this correct (Y/N): ");
    } while (($response <> 'Y') && ($response <> 'y'));

    $SN = hexdec($SNresponse);

    return ($SN);
}


/**
 ***********************************************************
 * @brief Write Serial Number and Hardware Version Routine
 * 
 * This function reads the input serial number and hardware
 * version, programs them into flash, and then verifies the
 * the programming by reading the numbers out.
 *
 * @return boolean result, true if pass, false if fail.
 *
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
            $result = false;
        }
    } else {
        print "Invalid program data, programming aborted!\n";
        $result = false;
    }

    $idNum = 0x20;
    $cmdNum = 0x0c;
    $dataVal = "76000A";

    $SerialandHW = Send_Packet($idNum, $cmdNum, $dataVal);

    print "Serial and HW number = ".$SerialandHW."\n";

    return ($result);

}



/**
 ***********************************************************
 * @brief Send a Ping routine
 *
 * This function pings the endpoint
 * with the test serial number and 
 * checks to see that the endpoint
 * responds.
 *
 * @return true, if endpoint responds
 *               otherwise false
 *
 */

function pingEndpoint( )
{
    /** Packet log include stuff */

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

    for ($i = 0; $i < 5; $i++) {
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
            $i = 5; /* exit loop if we get a response */
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


/**
 ***********************************************************
 * @brief Send a Packet routine
 *
 * This function will send a packet
 * to an endpoint with a command 
 * and data.
 *
 * @param  $Sn       endpoint serial
 * @param  $Cmd      hex command number
 * @param  $DataVal  ascii hex data string  
 *
 * @return reply data from endpoint
 *
 */

function Send_Packet($Sn, $Cmd, $DataVal)
{

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