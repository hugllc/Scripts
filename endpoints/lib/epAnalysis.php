<?php
/**
 * Does the actual analysis
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
class EpAnalysis extends EpScheduler
{
    /** String This specifies what the plugin directory is. */
    protected $pluginDir = "analysisPluginDir";
    /** int The number of seconds to pause before trying again */
    protected $wait = 600;

    /** bool Whether to go in depth on one endpoint or not */
    public $deep = false;

    /** array This is the stuff to check...  */
    protected $check = array(
                            "Analysis0" => "Analysis0",
                            "Analysis1" => "Analysis1",
                            "Analysis2" => "Analysis2",
                            "Analysis3" => "Analysis3",
                            "Analysis4" => "Analysis4",
                            "Analysis5" => "Analysis5",
                            "Analysis6" => "Analysis6",
                            "Analysis7" => "Analysis7",
                            "Analysis8" => "Analysis8",
                            "Analysis9" => "Analysis9",
                            );
    /**
    * The constructor
    *
    * @param array $config The configuration array
    */
    function __construct($config = array())
    {
        parent::__construct($config);
        $this->endpoint =& HUGnetDriver::getInstance($this->config);
        $this->device   =& HUGnetDB::getInstance("Device", $config);
        $where          = "1";
        if (!empty($config["DeviceID"])) {
            $where      = " DeviceID = ?";
            $data       = array($config["DeviceID"]);
            $this->deep = true;

        }
        if ($config["deep"]) {
            print "Enabling Deep Mode\n";
            $this->deep = true;
        }
        $this->devInfo    = $this->device->getWhere($where, $data);
        $this->rawHistory =& HUGnetDB::getInstance("RawHistory", $config);
        $this->analysis   =& HUGnetDB::getInstance("Analysis", $config);
        $this->plog       =& HUGnetDB::getInstance("plog", $config);
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
            if (!$this->config["loop"]) {
                $this->sleep();
            }
        } while (!$this->config["loop"]);
        return 0;
    }

    /**
    * Runs the plugins for a single device
    *
    * @param array &$devInfo The devInfo array
    *
    * @return null
    */
    function checkDevice(&$devInfo)
    {
        // If this is an unassigned device don't do any analysis on it
        if ($devInfo['GatewayKey'] == 0) {
            return;
        }
        $devInfo["orderby"] = " ORDER BY Date ASC ";
        print "Working with device ".$devInfo['DeviceID']."\r\n";

        if ($this->deep) {
            $devInfo['LastAnalysis'] = '0000-00-00 00:00:00';
        }
        if (isset($this->config["forceStart"])) {
            $res = strtotime($this->config["forceStart"]);
        } else {
            $where = "Date >= ? AND DeviceKey = ?";
            $data  = array($devInfo['LastAnalysis'], $devInfo["DeviceKey"]);
            $res   = $this->rawHistory->getWhere($where,
                                                 $data,
                                                 1,
                                                 0,
                                                 $devInfo["orderby"]);
            if (count($res) == 0) {
                return;
            }
            $res = strtotime($res[0]['Date']);
        }
        foreach (array("Y", "m", "d") as $val) {
            $startdate[$val] = (int) date($val, $res);
        }

        $start           = 0;
        $devInfo['date'] = $res;
        $lastpoll        = strtotime($devInfo['LastPoll']);
        $config          = $this->config;
        $config          = array("Type" => "history");
        $this->history   =& $this->endpoint->getHistoryInstance($config, $devInfo);
        $config          = array("Type" => "15MIN");
        $this->average   =& $this->endpoint->getHistoryInstance($config, $devInfo);


        for ($day = 0; $devInfo['date'] < time(); $day++) {
            $devInfo['date'] = mktime(0, 0, 0,
                                      $startdate['m'],
                                      $startdate['d']+$day,
                                      $startdate['Y']);
            // Check to make sure we have data for this day
            if ($devInfo['date'] > $lastpoll) {
                break;
            }

            print "Looking up ".date("Y-m-d", $devInfo['date'])." Records... ";
            $this->setupHistory($devInfo);
            $this->cacheHistory($devInfo);

            if ($this->historyCache === false) {
                break;
            }
            $this->analysisOut = array(
                                       "DeviceKey" => $devInfo['DeviceKey'],
                                       "Date" => date("Y-m-d", $devInfo['date']),
                                       );
            $count             = count($this->historyCache);
            $rawcount          = count($this->rawHistoryCache);
            print 'found: '.$count." Raw: ".$rawcount;

            for ($i = 0; $i < 10; $i++) {
                $this->plugins->runFilter($this, "Analysis".$i, $devInfo);
            }
            $update = array(
                'DeviceKey' => $devInfo["DeviceKey"],
                'LastAnalysis' => date("Y-m-d H:i:s", $devInfo["date"]),
                           );
            // Don't update for tomorrow, which it will get to.
            if ($devInfo['date'] < time()) {
                $this->device->update($update);
            }
            $this->analysis->add($this->analysisOut, true);

            print " Done \r\n";
        }
        unset($this->history);

    }
    /**
    * Runs the plugins for a single device
    *
    * @param array &$devInfo The devInfo array
    *
    * @return null
    */
    function cacheHistory(&$devInfo)
    {
        $this->rawHistoryCache = $this->rawHistory->getWhere($devInfo["where"],
                                                             $devInfo["whereData"],
                                                             null,
                                                             null,
                                                             $devInfo["orderby"]);
        $this->historyCache    = $this->history->getWhere($devInfo["where"],
                                                          $devInfo["whereData"],
                                                          null,
                                                          null,
                                                          $devInfo["orderby"]);
        $this->plogCache       = $this->plog->getWhere($devInfo["where"],
                                                       $devInfo["whereData"],
                                                       null,
                                                       null,
                                                       $devInfo["orderby"]);


    }

    /**
    * Runs the plugins for a single device
    *
    * @param array &$devInfo The devInfo array
    *
    * @return null
    */
    function setupHistory(&$devInfo)
    {
        $devInfo['daystart'] = date("Y-m-d 00:00:00", $devInfo['date']);
        $devInfo['dayend']   = date("Y-m-d 23:59:59", $devInfo['date']);
        $datewhere           = "(Date >= ? AND Date <= ?)";
        $datedata            = array($devInfo['daystart'], $devInfo['dayend']);

        $devInfo['datewhere'] = $datewhere;
        $devInfo['datedata']  = $datedata;

        $where  = $datewhere." AND DeviceKey = ?";
        $data   = $datedata;
        $data[] = $devInfo["DeviceKey"];

        $devInfo["where"]     = $where;
        $devInfo["whereData"] = $data;

    }

}
?>