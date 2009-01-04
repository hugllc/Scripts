#!/usr/bin/php-cli
<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
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
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

define("ANALYSIS_PARTNUMBER", "0039-26-03-P");  //0039-26-01-P
define("ANALYSIS_SVN", '$Id$');

define('ANALYSIS_HISTORY_COUNT', 1000);

require_once dirname(__FILE__).'/../head.inc.php';
require_once HUGNET_INCLUDE_PATH.'/database/Analysis.php';
require_once dirname(__FILE__).'/lib/epAnalysis.php';

if (!(bool)$hugnet_config["analysis_enable"]) {
    print "Analysis disabled... Sleeping\n";
    sleep(60);
    die();
}

for ($i = 0; $i < count($newArgv); $i++) {
    switch($newArgv[$i]) {
        // Gateway IP address
        case "-D":
            $i++;
            $hugnet_config["forceStart"] = $newArgv[$i];
            break;
    }
}

print 'analysis.php Version '.ANALYSIS_SVN."\n";
print "Starting...\n";

if (empty($hugnet_config["analysisPluginDir"])) $hugnet_config["analysisPluginDir"] = dirname(__FILE__)."/analysis/";
$hugnet_config["partNum"] = ANALYSIS_PARTNUMBER;

$epAnalysis = new epAnalysis($hugnet_config);

$epAnalysis->main();

print "Exiting...\n";









/*
if ($testMode) $endpoint->db->debug = true;
for ($i = 0; $i < count($newArgv); $i++) {
    switch($newArgv[$i]) {
        // Gateway IP address
        case "-D":
            $i++;
            $forceStart = $newArgv[$i];
            break;
    }
}


$uproc = new process();
$uproc->Register();
$uproc->CheckRegistered(true);

$plugins = new Plugins(dirname(__FILE__)."/analysis/", "dfp.php", dirname(__FILE__)."/plugins");

if (is_array($plugins->plugins["Functions"]["preAnalysis"])) {
    foreach ($plugins->plugins["Functions"]["preAnalysis"] as $plug) {
        print "Found preAnalysis Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
    }
}

for ($i = 0; $i < 10; $i++) {
    if (is_array($plugins->plugins["Functions"]["Analysis".$i])) {
        foreach ($plugins->plugins["Functions"]["Analysis".$i] as $plug) {
            print "Found Analysis".$i." Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
        }
    }
}
if (is_array($plugins->plugins["Functions"]["Analysis"])) {
    foreach ($plugins->plugins["Functions"]["Analysis"] as $plug) {
        print "Found Analysis Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
    }
}
for ($i = 10; $i < 20; $i++) {
    if (is_array($plugins->plugins["Functions"]["Analysis".$i])) {
        foreach ($plugins->plugins["Functions"]["Analysis".$i] as $plug) {
            print "Found Analysis".$i." Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
        }
    }
}
$_SESSION['Deep'] = false;
$where = "";
if (!empty($DeviceID)) {
//        $endpoint->device->setWhere("DeviceID='".$argv[1]."'");
    $where .= " DeviceID = '".$DeviceID."'";
    $_SESSION['Deep'] = true;
    
}

//    $endpoint->device->setIndex('DeviceKey');
//    $devices = $endpoint->device->getAll();
$query = "SELECT * FROM devices ";
if (!empty($where)) {
    $query .= " WHERE ".$where;
}
$devices = $endpoint->db->getArray($query);

foreach ($devices as $key => $dev) {
    $devices[$key] = $endpoint->DriverInfo($dev);
}
$processed = 0;
foreach ($devices as $dev) {

    $temp = $plugins->runFilter($temp, "preAnalysis", $dev);

    // If this is an unassigned device don't do any analysis on it
    if ($dev['GatewayKey'] == 0) continue;

    print "Working with device ".$dev['DeviceID']."\r\n";

    $_SESSION['devInfo'] =& $dev;
    if ($_SESSION['Deep']) $dev['LastAnalysis'] = '0000-00-00 00:00:00';




    $rawbasequery = "SELECT * FROM ".$endpoint->raw_history_table.
                 " WHERE ".
                 " DeviceKey=".$dev['DeviceKey'];
    $orderby = " ORDER BY Date ASC "; 
    $_SESSION['devCache'] =& $dev;
    $basequery = str_replace($endpoint->raw_history_table, $endpoint->getHistoryTable($dev), $rawbasequery);

    if (isset($forceStart)) {
        $res = strtotime($forceStart);            
    } else {
        $query = str_replace("*", "Date", $basequery)." AND  Date >= '".$dev['LastAnalysis']."'".$orderby." LIMIT 0,1";
        $res = $endpoint->db->getArray($query);  
        if (count($res) == 0) continue;
        $res = strtotime($res[0]['Date']);
    }
    foreach (array("Y", "m", "d") as $val) {
        $startdate[$val] = (int) date($val, $res);
    }

    $start = 0;
    $dev['date'] = $res;
    $lastpoll = strtotime($dev['LastPoll']);

    for ($day = 0; ($dev['date'] < time()) && ($dev['date'] < $lastpoll); $day++) {
        $dev['date'] = mktime(0, 0, 0, $startdate['m'], $startdate['d']+$day, $startdate['Y']);
        $dev['daystart'] = date("Y-m-d 00:00:00", $dev['date']);
        $dev['dayend'] = date("Y-m-d 23:59:59", $dev['date']);
        $datewhere = "Date >= ".$endpoint->db->qstr($dev['daystart'])." AND Date <= ".$endpoint->db->qstr($dev['dayend']);
        $dev['datewhere'] = $datewhere;

               print "Looking up ".date("Y-m-d", $dev['date'])." Records... ";
//                $rhistory->setLimit($start, ANALYSIS_HISTORY_COUNT);
        $_SESSION['rawHistoryCache'] = $endpoint->db->getArray($rawbasequery . " AND (" . $datewhere . ") " . $orderby);
        $_SESSION['historyCache'] = $endpoint->db->getArray($basequery . " AND (" . $datewhere . ") " . $orderby);

        if ($_SESSION['historyCache'] === false) break;
        $_SESSION['analysisOut'] = array(
            "DeviceKey" => $dev['DeviceKey'],
            "Date" => date("Y-m-d", $dev['date']),
        );
        $count = count($_SESSION['historyCache']);
        $rawcount = count($_SESSION['rawHistoryCache']);
        print 'found: '.$count." Raw: ".$rawcount;
        if ($rawcount > 0) {
            $filterout = array();
            if ($verbose) print "\r\n"; 
            for ($i = 0; $i < 10; $i++) {
                $plugins->runFilter($filterout, "Analysis".$i, $dev);
            }
            $plugins->runFilter($filterout, "Analysis", $dev);
            for ($i = 10; $i < 20; $i++) {
                $plugins->runFilter($filterout, "Analysis".$i, $dev);
            }
            $processed += $count;
                        
            $endpoint->db->Execute("DELETE FROM analysis WHERE DeviceKey=".$dev['DeviceKey']. " AND Date=".$endpoint->db->qstr($_SESSION['analysisOut']['Date']));
            $ret  = $endpoint->db->AutoExecute("analysis", $_SESSION['analysisOut'], 'INSERT');
            if ($ret) {
                $update = array(
                    "LastAnalysis" => date("Y-m-d H:i:s", $dev['date']),
               );
                $ret  = $endpoint->db->AutoExecute($endpoint->device_table, $update, 'UPDATE', "DeviceKey=".$dev['DeviceKey']);
                
            } else {
                
            }
            
        }
//                print " Processed ".$processed." records\r\n";
        print " Done \r\n";
    }
}
$uproc->Unregister();
$uproc->CheckUnregistered(true);
*/
?>
