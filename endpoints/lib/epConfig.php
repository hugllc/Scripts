<?php
/**
 * Reads configurations out of the endpoints
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
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/** This is our base class */
require_once "endpointBase.php";
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
/** Packet log process stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Process.php';
/** Packet log stats stuff */
require_once HUGNET_INCLUDE_PATH.'/database/ProcStats.php';

/**
 * Class for polling endpoints
 *
 * @category   Test
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
class EpConfig extends EndpointBase
{

    /** @var array The endpoints we are working with */
    var $ep = array();
    /** @var bool Whether or not to actually do the configuration*/
    var $doConfig = false;
    /** @var int The interval in seconds between forced config attempt*/
    var $configInterval = 43200;
    /** @var int Seconds. How often to force check controllers*/
    var $ccTimeout = 600;
    /** @var int Seconds. How long before we stop trying to get to an endpoint*/
    var $cutoffdays = 14;
    /** @var bool Test mode*/
    var $test = false;
    /** @var bool How many tries before we start waiting longer between tries*/
    var $failureLimit = 5;
    /** @var int Our current priority 1-255*/
    var $Priority = 0;
    /** @var int Last time an endpoint was contacted*/
    var $lastContactTime = 0;
    /** @var int Last time an attempt was made to contact an endpoint*/
    var $lastContactAttempt = 0;
    /** @var int How long before timeouts become a critical error*/
    var $critTime = 60;

    /**
    * Construction
    *
    * @param array $config Configuration
    *
    * @return null
    */
    function __construct($config = array())
    {
        unset($config["servers"]);
        unset($config["table"]);
        $config["partNum"] = CONFIG_PARTNUMBER;
        $this->config      = $config;

        $this->uproc =& HUGnetDB::getInstance("Process", $config);
        $this->uproc->createTable();

        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config);
        $this->stats->createTable();
        $this->stats->clearStats();
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test       = $config["test"];
        $this->verbose    = $config["verbose"];
        $cutoff           = (time() - (86400 * $this->cutoffdays));
        $this->cutoffdate = date("Y-m-d H:i:s", $cutoff);
        $this->endpoint   =& HUGnetDriver::getInstance($config);

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config);
        $this->plog->createTable();

        unset($config["table"]);
        $this->device             =& HUGnetDB::getInstance("Device", $config);
        $this->device->addField("configCache", "TEXT", "", false);
        $this->lastContactTime    = time();
        $this->lastContactAttempt = time();
        $this->gateway            =& HUGnetDB::getInstance("Gateway", $config);

        $this->gw =& HUGnetDB::getInstance("Gateway", $config);
        $this->gw->createLocalTable("LocalGW");

        parent::__construct($config);
    }

    /**
     * Main routine for polling endpoints
     *
     * @return null
     */
    function main()
    {
        $this->powerup();
        $this->uproc->register();


        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            if ($this->lastminute != date("i")) {
                print "Using: ".$this->myInfo['DeviceID'];
                print " Priority: ".$this->myInfo["Priority"];
                print " - ".date("Y-m-d H:i:s")."\r\n";
                $this->setupMyInfo();
                $this->getAllDevices();
                $this->getOtherPriorities();
                if (!$this->checkPriority($id, $priority)) {
                    print "Skipping the config check.";
                    print "$id is checking configs with priority $priority\n";
                    $this->stats->setStat('ccheck', $id);
                    $this->doConfig = false;
                } else {
                    $this->stats->setStat('ccheck', $this->myInfo["DeviceID"]);
                    $this->doConfig = true;
                }
                $this->lastminute = date("i");
            }
            $this->checkUnsolicitedPackets();
            $checked = $this->configCheck();
            if ($checked == 0) {
                $this->wait();
            }
        }
        $this->uproc->unregister();

    }

    /**
    * Gets the devices that it will get the configuration for
    *
    * @return null
    */
    function getAllDevices()
    {

        print "Getting endpoints for Gateway #".$this->config["GatewayKey"];


        $query = "GatewayKey= ? ";
        $res   = $this->device->getWhere($query, array($this->config["GatewayKey"]));

        if (!is_array($res) || (count($res) == 0)) {
            $this->stats->incStat("Device Cache Failed");
        }
        if (is_array($res) && (count($res) > 0)) {
            $this->ep = array();
            foreach ($res as $key => $val) {
                if ($val['DeviceID'] !== "000000") {
                    $dev          = $this->endpoint->DriverInfo($val);
                    $k            = $val['DeviceID'];
                    $this->ep[$k] = $dev;
                    if ($this->endpoint->isController($dev)) {
                        $this->_devInfo[$k]["Controller"] = true;
                    }
                    if (!isset($this->_devInfo[$k]["GetConfig"])) {
                        $this->_devInfo[$k]["GetConfig"] = false;
                    }
                }
            }
        }
        $this->stats->setStat('Devices', count($this->ep));
        print " (Found ".count($this->ep).")\n";
        return $this->ep;
    }

    /**
    * Waits a random amount of time.
    *
    * @return null
    */
    function wait()
    {
        $sleep = mt_rand(1, 6);
        sleep($sleep);
    }


    /**
    * Finds a device in the device cache
    *
    * @param string $DeviceID 6 character ascii hex DeviceID
    *
    * @return null
    */
    function findDev($DeviceID)
    {
        if (isset($this->ep[$DeviceID])) {
            return $DeviceID;
        }
        foreach ($this->ep as $key => $ep) {
            if (trim(strtoupper($ep["DeviceID"])) == trim(strtoupper($DeviceID))) {
                return $key;
            }
        }
        return false;
    }

    /**
    * Deals with packets to me.
    *
    * @return null
    */
    public function checkUnsolicitedPackets()
    {
        $pkts = $this->getUnsolicited();
        foreach ($pkts as $p) {
            print "Unsolicited packet from ".$p["PacketFrom"];
            print " command ".$p["Command"]."\n";
            switch ($p["Command"]) {
            case PACKET_COMMAND_RECONFIG:
            case PACKET_COMMAND_POWERUP:
                $this->_devInfo[$p["PacketFrom"]]['nextCheck'] = 0;
                break;
            default:
                break;
            }
        }

    }

    /**
    * Checks the configuration for a device
    *
    * @param string $key This is the array key in the device cache to check
    *
    * @return null
    */
    function checkDev($key)
    {
        if (!is_array($this->ep[$key])) {
            return;
        }
        if (empty($key)) {
            return;
        }

        $this->lastContactAttempt = time();

        $dev = $this->ep[$key];
        if (is_array($this->_devInfo[$key])) {
            $dev = array_merge($dev, $this->_devInfo[$key]);
        }

        print "Checking ".$dev["DeviceID"]." ";
        $this->stats->incStat("Device Checked");
        $pkt       = $this->endpoint->readConfig($dev);
        $gotConfig = false;
        if ($pkt !== false) {
            $newConfig = $this->interpConfig($pkt);
            foreach ($pkt as $p) {
                if ($p !== false) {
                    if ($p["Reply"]) {
                        if (!isset($p['DeviceKey'])) {
                            $p['DeviceKey'] = $dev['DeviceKey'];
                        }
                        if (empty($dev['GatewayKey'])) {
                            $dev['GatewayKey'] = $this->GatewayKey;
                        }
                        $job    = (20+$this->myInfo["Job"]);
                        $logpkt = plog::packetLogSetup($p, $dev, "CONFIG", $job);
                        if ($this->plog->add($logpkt)) {
                            print " Done (".number_format($p["ReplyTime"], 2).")";
                            $gotConfig                    = true;
                            $now                          = date("Y-m-d H:i:s");
                            $this->ep[$key]["LastConfig"] = $now;
                            if ($this->_devInfo[$key]["Controller"]) {
                                $next = $this->ccTimeout;
                            } else {
                                $this->configInterval;
                            }
                            $this->_devInfo[$key]["nextCheck"]  = time() + $next;
                            $this->_devInfo[$key]["LastConfig"] = $now;
                            $this->_devInfo[$key]["GetConfig"]  = false;
                            $this->_devInfo[$key]['LastCheck']  = time();
                            unset($this->_devInfo[$key]['failedCheck']);
                            $this->lastContactTime = time();
                        } else {
                            //print "Error: ".$this->plog->_sqlite->lastError();
                        }
                    }
                }
            }
            if ($gotConfig) {
                // If it is a controller board check the program
                $this->stats->incStat("Device Checked Success");
                $driver =&  $this->endpoint->drivers[$newConfig['Driver']];
                if (method_exists($driver, "checkProgram")) {
                    $this->stats->incStat("Check Program");
                    print " Checking Program ";
                    $ret = $driver->checkProgram($newConfig, $pkt, true);
                    if ($ret) {
                        $this->stats->incStat("Check Program Success");
                        print " Done ";
                    } else {
                        print " Failed ";
                    }
                }
            } else {
                   print " Failed ";
                   $this->_devInfo[$key]['nextCheck'] = time()+120;
                   print " - Next Check ";
                   print date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);
            }

        } else {
            print " Nothing Returned";
            $this->_devInfo[$key]['nextCheck'] = time()+300;
            print " - Next Check ";
            print date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);

        }
        print "\r\n";

    }

    /**
     * Check all of the configurations
     *
     * @return int
     */
    function configCheck()
    {

        if (!$this->doConfig) {
            return;
        }
        $checked = 0;
        $epkeys  = array_keys($this->ep);
        shuffle($epkeys);
        $count = 0;
        foreach ($epkeys as $key) {
            $dev =& $this->ep[$key];
            if ($GLOBALS["exit"]) {
                return 1;
            }
            if (empty($dev['DeviceID'])) {
                unset($this->ep[$key]);
            }
            if ($this->_devInfo[$key]['nextCheck'] < time()) {
                $checked++;
                $this->checkDev($key);
            }
            if ($this->lastminute != date("i")) {
                break;
            }
        }
        return $checked;
    }


}

?>
