#!/usr/bin/php-cli
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
 * @category   Scripts
 * @package    Scripts
 * @subpackage Alarm
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
require_once dirname(__FILE__).'/../head.inc.php';

$uproc = new process($prefs['servers'], HUGNET_DATABASE, "NORMAL", basename(__FILE__));
$uproc->Register();
$uproc->CheckRegistered(true);
/*
$servers = array();
$servers[0]["Host"] = "localhost"; // Set to your server name or$
$servers[0]["User"] = "Portal";  // Set to the database username
$servers[0]["Password"] = "Por*tal"; // Set to the database password
$servers[0]["AccessType"] = "R";  // R for Read, W for Write, RW for both
$servers[0]["db"] = "HUGnet";
*/
//    $alarms = new container("", "Alarms", "HUGNet");
//    $alarms->AutoSETS();

$alarms = new MDB_QueryWrapper($prefs['servers'], HUGNET_DATABASE, array('table' => "Alarms", 'primaryCol' => 'AlarmKey', "dbWrite" => true));

$endpoint = new driver($prefs['servers'], HUGNET_DATABASE, array());


$endpoint->device->reset();
$endpoint->device->setIndex('DeviceKey');
$devices = $endpoint->device->getAll();

$count = 0;
$LastChecked = array();
$LastEmail = array();
$AlarmArray = array();
while(1) {

    $uproc->Checkin();
    $lastminute = date("i");

    $ret = $alarms->getAll();
    $AlarmArray = (count($ret) > 0) ? $ret : array();
    $emailtext = array();
    foreach ($AlarmArray as $key => $alarm) {
        if (!isset($LastChecked[$alarm["AlarmKey"]])) $LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($alarm["LastChecked"]), $alarm["AlarmCheckTime"]);
        if (!isset($LastEmail[$alarm["AlarmKey"]])) $LastEmail[$alarm["AlarmKey"]] = GetNextPoll(strtotime($alarm["LastEmail"]), $alarm["AlarmEmailTime"]);

        print "[".$uproc->me["PID"]."] Checking Alarm ".$alarm["AlarmName"]." ";
        if ($alarm["Active"] == "YES") {
            if ($LastChecked[$alarm["AlarmKey"]] <= time()) {
                $baddev = $alarms->execute($alarm["AlarmSQL"]);

                if (count($baddev) > 0) {
                    print " Found ".count($baddev)." Problems ";

                    $info["AlarmKey"] = $alarm["AlarmKey"];
                    $info["LastFound"] = date("Y-m-d H:i:s");
                    $info["LastChecked"] = $info["LastFound"];
                    $LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmCheckTime"]);

                    if ($LastEmail[$alarm["AlarmKey"]] <= time()) {
                        $LastEmail[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmEmailTime"]);
                        $info["LastEmail"] = $info["LastFound"];
                        $emailtext[$alarm["AlarmEmail"]] .= "Found problems on ".$alarm["AlarmName"]."\r\n";
                        $devcounter = 0;
                        foreach ($baddev as $rec) {
                            if (isset($dev["DeviceKey"])) {
                                if (($devcounter > 500) || ($devcounter > $alarm["AlarmMaxHits"])) break;
                                if (isset($devices[$rec["DeviceKey"]]["DeviceID"])) {
                                    $devcounter++;
                                    $emailtext[$alarm["AlarmEmail"]] .= "Device ".$devices[$rec["DeviceKey"]]["DeviceID"]."\n";
                                    $emailtext[$alarm["AlarmEmail"]] .= "        Info: http://www.hugllc.com/HUGnet/info.php?DeviceID=".$devices[$rec["DeviceKey"]]["DeviceID"]."\r\n";
                                    $emailtext[$alarm["AlarmEmail"]] .= "        History: http://www.hugllc.com/HUGnet/history.php?DeviceID=".$devices[$rec["DeviceKey"]]["DeviceID"]."\r\n";
                                    $emailtext[$alarm["AlarmEmail"]] .= "\r\n";
                                }
                            }
                        }
                    }

                    if ($alarms->save($info)) {
                        print " Alarm Updated ";
                    } else {
                        print get_stuff($alarms);
                    }


                } else {
                    if ($alarms->Errno == 0) {
                        print " Fine ";
                        $info["AlarmKey"] = $alarm["AlarmKey"];
                        $info["LastChecked"] = date("Y-m-d H:i:s");
                        $LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmCheckTime"]);
                        if ($alarms->save($info)) {
                            print " Alarm Updated ";
                        } else {
                            print get_stuff($alarms);
                        }
                    } else {
                        print " Database Error (".$alarms->db->Errno."): ".$alarms->db->Error;
                    }
                }
            } else {
                print " Not Time ";
            }
        } else {
            print " Off ";
        }
        print "\n";
    }

    foreach ($emailtext as $email => $text) {
        if (mail($email, "HUGnet Problems Identified", $text)) {
            print "[".$uproc->me["PID"]."] Sent Email to ".$email."\n";
        } else {
            print "[".$uproc->me["PID"]."] Message to ".$email." Failed\n";
        }
    }
    /*
        This section pauses until the next minute.        
     */
    if ($count++ == 5) {
        $uproc->Checkin();
        $count = 0;
    }
    print  "[".$uproc->me["PID"]."] Pausing...\n";
    while(date("i") == $lastminute) {
        sleep(1);
    }

}

$uproc->Unregister();
$uproc->CheckUnregistered(true);

include_once("blanktail.inc.php");

/**
 * Figures out the next time we should poll
 *
 * @param mixed $time     The time
 * @param int   $Interval The alarm check interval
 *
 * @return The time of the next poll
*/
function GetNextPoll($time, $Interval) 
{
    if (!is_numeric($time)) {
        $time = strtotime($time);
    }

    $sec = 0; //date("s", $time);
    $min = date("i", $time);
    $hour = date("H", $time);
    $mon = date("m", $time);
    $day = date("d", $time);
    $year = date("Y", $time);

    $nexttime = mktime($hour, ($min + $Interval), $sec, $mon, $day, $year);
    return($nexttime);
}


?>
