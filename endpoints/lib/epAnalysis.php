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
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2008 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: endpoint.php 1445 2008-06-17 22:25:17Z prices $    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
 
require_once "epScheduler.php";
/**
 * Class for checking on things to make sure they are working correctly
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2008 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */ 
class epAnalysis extends epScheduler
{
    /** String This specifies what the plugin directory is. */
    protected $pluginDir = "analysisPluginDir";
    /** int The number of seconds to pause before trying again */
    protected $wait = 600;

    /** bool Whether to go in depth on one endpoint or not */
    protected $deep = false;
             
    /** array This is the stuff to check...  */
    protected $check = array("Analysis" => "Analysis"
                            );
    /**
     *
     */    
    function __construct($config = array()) 
    {        
        parent::__construct($config);
        $this->endpoint =& HUGnetDriver::getInstance($this->config);
        $this->device =& HUGnetDB::getInstance("Device", $config); // new device($file);
        $where = "1";
        if (!empty($config["DeviceID"])) {
            $where = " DeviceID = ?";
            $data  = array($config["DeviceID"]);       
            $this->deep = true;
    
        }

        $this->devInfo = $this->device->getWhere($where, $data);    
        $this->rawHistory = & HUGnetDB::getInstance("RawHistory", $config);
    }

    /**
    * This is the main routine that should be called by the script
    *
    * @return int The error code, if any
    */
    function main()
    {

        if (empty($this->devInfo)) {
            print "No devices!\n";
            return 1;
        }                                 
        $this->clearErrors();
        do {
            foreach ($this->devInfo as $dev) {
                $this->checkDevice($dev);
            }
            if (!$this->config["loop"]) $this->sleep();
                        
        } while (!$this->config["loop"]);
        return 0;
    }

    /**
    * Runs the plugins for a single device
    *
    * @param array $devInfo The devInfo array
    *
    * @return null
    */                  
    function checkDevice($devInfo)
    {
        // If this is an unassigned device don't do any analysis on it
        if ($devInfo['GatewayKey'] == 0) return;

        $orderby = " ORDER BY Date ASC ";
        print "Working with device ".$devInfo['DeviceID']."\r\n";

        if ($this->deep) $devInfo['LastAnalysis'] = '0000-00-00 00:00:00';

        if (isset($this->config["forceStart"])) {
            $res = strtotime($this->config["forceStart"]);
        } else {
            $where = "Date >= ? AND DeviceKey = ?";
            $data = array($devInfo['LastAnalysis'], $devInfo["DeviceKey"]);
            $res = $this->rawHistory->getWhere($where, $data, 1, 0, $orderby);
            if (count($res) == 0) return;
            $res = strtotime($res[0]['Date']);
        }
        foreach (array("Y", "m", "d") as $val) {
            $startdate[$val] = (int) date($val, $res);
        }

        $start = 0;
        $devInfo['date'] = $res;
        $lastpoll = strtotime($devInfo['LastPoll']);
        $config = $this->config;
        $config = array("Type" => "history");
        $this->history =& $this->endpoint->getHistoryInstance($config, $devInfo);
        $config = array("Type" => "15MIN");
        $this->average =& $this->endpoint->getHistoryInstance($config, $devInfo);
        
        
        for ($day = 0; ($devInfo['date'] < time()) && ($devInfo['date'] < $lastpoll); $day++) {
            $devInfo['date'] = mktime(0, 0, 0, $startdate['m'], $startdate['d']+$day, $startdate['Y']);
            $devInfo['daystart'] = date("Y-m-d 00:00:00", $devInfo['date']);
            $devInfo['dayend'] = date("Y-m-d 23:59:59", $devInfo['date']);
            $datewhere = "(Date >= ? AND Date <= ?)";
            $datedata = array($devInfo['daystart'], $devInfo['dayend']);
            
            $devInfo['datewhere'] = $datewhere;
            $devInfo['datedata'] = $datedata;

            $where = $datewhere." AND DeviceKey = ?";
            $data = $datedata;
            $data[] = $devInfo["DeviceKey"];                           
            
            print "Looking up ".date("Y-m-d", $devInfo['date'])." Records... ";
            $this->rawHistoryCache = $this->rawHistory->getWhere($where, $data, null, null, $orderby);
            $this->historyCache = $this->history->getWhere($where, $data, null, null, $orderby);

            if ($this->historyCache === false) break;
            $this->analysisOut = array(
                                            "DeviceKey" => $devInfo['DeviceKey'],
                                            "Date" => date("Y-m-d", $devInfo['date']),
                                            );
            $count = count($this->historyCache);
            $rawcount = count($this->rawHistoryCache);
            print 'found: '.$count." Raw: ".$rawcount;

            for ($i = 0; $i < 10; $i++) {
                $this->plugins->runFilter(&$this, "Analysis".$i, &$devInfo);
            }
            $update = array(
                'DeviceKey' => $devInfo["DeviceKey"],
                'LastAnalysis' => date("Y-m-d H:i:s", $devInfo["date"]),
                           );
            // Don't update for tomorrow, which it will get to.
            if ($devInfo['date'] < time()) $this->device->update($update);
            print " Done \r\n";
        }
        unset($this->history);
                    
    }      
}
?>