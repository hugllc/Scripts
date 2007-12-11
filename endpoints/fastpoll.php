<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007 Hunt Utilities Group, LLC
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
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Scripts
 * @subpackage Poll
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */

    $dfportal_no_session = true;
    include_once("blankhead.inc.php");

    print '$Id$'."\n";
    print "Starting...\n";
    $GatewayKey = false;
    
    // This tries to automatically determine what Gateway to use, since none was supplied.
    if ($argc < 2) {
        $gw = $endpoint->device->gateway->Find(true);
        if (is_array($gw)) {
            $GatewayKey = $gw["GatewayKey"];
        }
    } else {
        // We were passed a gateway key, so use it.
        $GatewayKey = $argv[1];
    }

    if (($GatewayKey == false) | ($GatewayKey == 0)) die("You must supply a gateway key\n");
    
    do {
        $endpoint->device->gateway->lookup($GatewayKey, "GatewayKey, GatewayName");
        if ($endpoint->device->gateway->lookup[0]["BackupKey"] != 0) {
            $GatewayKey = $endpoint->device->gateway->lookup[0]["BackupKey"];
        }
    } while ($endpoint->device->gateway->lookup[0]["BackupKey"] != 0);
    $gw = array();
    $gw[0] = $endpoint->device->gateway->lookup[0];
    $endpoint->device->gateway->lookup($GatewayKey, "BackupKey");
    foreach ($endpoint->device->gateway->lookup as $thegw) {
        $gw[] = $thegw;
    }
    
    if (!is_array($gw[0])) die("Gateway Not found");

    
    $servers = array();
    $servers[0]["Host"] = "localhost"; // Set to your server name or$
    $servers[0]["User"] = "PortalW";  // Set to the database username
    $servers[0]["Password"] = "Por*tal"; // Set to the database password
    $servers[0]["AccessType"] = "RW";  // R for Read, W for Write, RW for both
    $servers[0]["db"] = "HUGnetLocal";
    $history = new container($servers, "History", "HUGnetLocal");
    $history->AutoSETS();

    $gwindex = array();
    $ep = array();
    $PollTime = array();
    $Failures = array();
    $lastminute = -1;
    while (1) {
        $forceregen = false;
        
        // Regenerate our endpoint information
        if ((($lastminute % 10) == 0) || (count($ep) < 1) || $forceregen) {
            print "Getting endpoints for Gateway #".$GatewayKey."\n";
            $endpoint->device->lookup($GatewayKey, "GatewayKey");
            if (count($endpoint->device->lookup) > 0) {
                $ep = $endpoint->device->lookup;
                shuffle($ep);  // This randomizes it.
            }
            $gwindex = array();
        }

            
        foreach ($ep as $key => $val) {
            if ($val["PollInterval"] <= 0) {
                // THis removes any that aren't being polled.
                unset($ep[$key]);  
            } else {
                if (!isset($PollTime[$val["DeviceKey"]])) {
                    $PollTime[$val["DeviceKey"]] = GetNextPoll($val["LastPoll"], $val["PollInterval"]);
                }
                if (!isset($gwindex[$val["DeviceKey"]])) {
                    $gwindex[$val["DeviceKey"]] = 0;
                }
            }
        }

        // Close the socket (this is the logical end of our loop
//        $endpoint->socket->close();

        /*
            This section pauses until the next minute.        
         */
/*
        print "Pausing...\n";
        while(date("i") == $lastminute) {
            sleep(1);
        }
*/
        /*
            This section pings the servers and checks to see if we should run.  It sets
            $dopoll to true if we shoudl poll.
         */
/*
        print "Checking in with the Gateways... ";
        foreach ($gw as $key => $gate) {
            if (!isset($gate["PingKey"])) {
                $PingKey = 255;
            } else {
                $PingKey = $gate["PingKey"];
            }
            print $gate["GatewayName"]." : ";
            $Poll[$key] = $endpoint->device->gateway->PingStat($gate, "poll.php__".$GatewayKey, $PingKey);
            if (isset($Poll[$key]["pingkey"])) {
                $gw[$key]["PingKey"] = $Poll[$key]["pingkey"];
            }
            $dopoll = true;
        }
        print "Done \r\n";
*/        
        $dopoll = true;

        // Here is the actual polling
        $lastminute = date("i");
        if ($dopoll) {
            foreach ($ep as $key => $dev) {
                print $dev["DeviceID"]." (".$dev["Driver"].") -> ".date("Y-m-d H:i:s", $PollTime[$dev["DeviceKey"]])." <-> ".date("Y-m-d H:i:s");
//                if ($PollTime[$dev["DeviceKey"]] <= time()) { 
                    $count = 0;
                    do {
                        $count++;
                        $sensors = array();
                        $dev["GatewayIP"] = $gw[$gwindex[$dev["DeviceKey"]]]["GatewayIP"];    
                        $dev["GatewayPort"] = $gw[$gwindex[$dev["DeviceKey"]]]["GatewayPort"];
                        print  " [".$gw[$gwindex[$dev["DeviceKey"]]]["GatewayName"]."] ->";
                        $sensors = $endpoint->ReadSensors($dev);
                        if (($sensors !== false) && (count($sensors) > 0)) {
                            $failures[$dev["DeviceKey"]] = 0;
                            if ($lastindex[$sensors["DeviceKey"]] != $sensors["DataIndex"]) {
                                if ($history->Add($sensors)) {
                                    print " Success ";
                                    $PollTime[$dev["DeviceKey"]] = GetNextPoll(time(), $dev["PollInterval"]);
                                    //This rotates through the array, so we are not always doing things in the same order
                                    $ep[] = $ep[$key];
                                    unset($ep[$key]);
                                } else {
                                    print " Failed to store data (".$history->wdb->Errno."): ".$history->wdb->Error;
                                    print strip_tags(get_stuff($history->wdb));                            
                                }
                            } else {
                                print " Data Index Identical ";
                            }
                        } else {
                            print " No data returned (".$failures[$dev["DeviceKey"]].")";
                            $gwindex[$dev["DeviceKey"]]++;
                            if (!isset($gw[$gwindex[$dev["DeviceKey"]]])) $gwindex[$dev["DeviceKey"]] = 0;
                        }
                    } while (($count < count($gw)) && ($sensors === false));
                    if ($sensors === false) {
                        $failures[$dev["DeviceKey"]]++;
                        $PollTime[$dev["DeviceKey"]] = GetNextPoll(time(), $dev["PollInterval"], $failures[$dev["DeviceKey"]]);
                    }
                    $lastindex[$sensors["DeviceKey"]] = $sensors["DataIndex"];    
                    print "\n";
//                } else {
//                    print " Not Time\n";
//                }
                if (date("i") != $lastminute) {
                    print "Minute expended.  Going to the next one.\n";
                    break;
                }
            }

            // This section updates the database 
            if (function_exists(pcntl_waitpid)) {
                $ccheck = 0;
                if (isset($child)) {
                    $ccheck = pcntl_waitpid($child, $status, WNOHANG);
                }
                if ($ccheck >= 0) {
                    $child = pcntl_fork();
                    if ($child == -1) {
                    } else if ($child == 0) {
                        include("updatedb.php");
                        die();
                    }
                }
            } else {
                print "Automatic updating not enabled.  Please enable 'pcntl' in PHP\r\n";
            }
                    
        } else {
            print "Skipping Poll.  Polling being done by ".$Poll["Ident"]." on ".$Poll["IP"]."\r\n";
        }
    }    

    include_once("blanktail.inc.php");
    print "Finished\n";

function GetNextPoll($time, $Interval, $failures=false) {
    if (!is_numeric($time)) {
        $time = strtotime($time);
    }

    $sec = 0; //date("s", $time);
    $min = date("i", $time);
    $hour = date("H", $time);
    $mon = date("m", $time);
    $day = date("d", $time);
    $year = date("Y", $time);

    if ($failures === false) {
        $nexttime = mktime($hour, ($min + $Interval), $sec, $mon, $day, $year);
    } else {
        $NewInt = (int)($failures / 5) * $Interval; 
        if ($NewInt > 240) $NewInt = 240;
        $nexttime = mktime($hour, ($min + $NewInt), $sec, $mon, $day, $year);    
    }
    return($nexttime);
}

?>    

        
        
        
