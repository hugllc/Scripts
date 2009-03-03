<?php
/**
 * The main endpoint polling code
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
class EpPoll extends EndpointBase
{

    /** @var array The endpoints we are working with */
    var $ep = array();
    /** @var bool Whether to do the polling or not */
    var $doPoll = false;
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
        $config["partNum"] = POLL_PARTNUMBER;
        $this->config      = $config;
        $this->uproc       =& HUGnetDB::getInstance("Process", $config);
        $this->uproc->createTable();

        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config);
        $this->stats->createTable();
        $this->stats->clearStats();
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test       = (bool) $config["test"];
        $this->verbose    = (bool) $config["verbose"];
        $cutoff           = time() - (86400 * $this->cutoffdays);
        $this->cutoffdate = date("Y-m-d H:i:s", $cutoff);
        $this->endpoint   =& HUGnetDriver::getInstance($config);
        $this->endpoint->packet->getAll(true);

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config);
        $this->plog->createTable();

        unset($config["table"]);
        $this->device  =& HUGnetDB::getInstance("Device", $config);
$this->gateway =& HUGnetDB::getInstance("Gateway", $config);

        $config["table"] = "DebugPacketLog";
        $this->debugplog =& HUGnetDB::getInstance("Plog", $config);
        $this->debugplog->createTable();

        $config["table"] = "PacketSend";
        $this->psend     =& HUGnetDB::getInstance("Plog", $config);
        $this->psend->createTable("PacketSend");
        $this->packet =& $this->endpoint->packet;

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
        $this->packet->packetSetCallBack('checkPacket', $this);
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
                    print "Skipping the poll.";
                    print "$id is polling with priority $priority\n";
                    $this->stats->setStat('polling', $id);
                    $this->doPoll = false;
                } else {
                    $this->doPoll = true;
                    $this->stats->setStat('polling', $this->myInfo["DeviceID"]);
                }
                $this->lastminute = date("i");
            }
            $count = $this->poll();

            if ($count == 0) {
                $this->wait();
            }
        }
        $this->uproc->unregister();

    }

    /**
    * Figures out the next time we should poll
    *
    * @param string $key           The key to use
    * @param int    $forceInterval If set the interval is forced to this
    *
    * @return null
    */
    function getNextPoll($key, $forceInterval = null)
    {
        $devInfo =& $this->_devInfo[$key];
        $dev     =& $this->ep[$key];
        if ($dev["PollInterval"] <= 0) {
            return;
        }

        if (!isset($devInfo["gwIndex"])) {
            $devInfo["gwIndex"] = 0;
        }
        if (($devInfo['failures'] > $this->failureLimit) || !empty($forceInterval)) {
            $time = time();
        } else if (empty($devInfo["LastPoll"])) {
            $time = strtotime($dev["LastPoll"]);
        } else {
            $time = strtotime($devInfo["LastPoll"]);
        }

        $mult = (int) ($devInfo['failures'] / $this->failureLimit) + 1;
        if ($mult > 25) {
            $mult = 25;
        }
        if (empty($forceInterval)) {
            $Interval = $this->ep[$key]["PollInterval"];
        } else {
            $Interval = $forceInterval;
        }
        // Poll interval is in minutes that is where the 60 comes from
        $newtime = $time + (60 * $mult * $Interval);

        if ($devInfo["PollTime"] < $newtime) {
            $devInfo["PollTime"] = $newtime;
        }
    }


    /**
    * Gets the devices that it will get the configuration for
    *
    * @return null
    */
    function getAllDevices()
    {

        print "Getting endpoints for Gateway #".$this->config["GatewayKey"];


        $query = "GatewayKey = ? AND PollInterval > 0";
        $res   = $this->device->getWhere($query, array($this->config["GatewayKey"]));

        if (!is_array($res) || (count($res) == 0)) {
            $this->stats->incStat("Device Cache Failed");
        }
        if (is_array($res) && (count($res) > 0)) {
            $this->ep = array();
            foreach ($res as $key => $val) {
                if ($val['DeviceID'] !== "000000") {
                    $dev            = $this->endpoint->DriverInfo($val);
                    $key            = $val['DeviceID'];
                    $this->ep[$key] = $dev;
                    $this->getNextPoll($key);
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
     * Deals with packets to me.
     *
     * @param array $pkt The packet array
     *
     * @return null
     */
    function checkPacket($pkt)
    {
        if (!$pkt['toMe']) {
            return;
        }
        $this->stats->incStat("To Me");
        $sendCommand = trim(strtoupper($pkt['sendCommand']));
        if ($sendCommand == PACKET_COMMAND_GETSETUP) {
            $this->interpConfig(array($pkt));
            return;
        }
        $Command = trim(strtoupper($pkt['Command']));
        switch($Command) {
        case PACKET_COMMAND_GETSETUP:
            // Get our setup
            $config = e00392601::getConfigStr($this->myInfo);
            $ret    = $this->packet->sendReply($pkt, $pkt['From'], $config);
            break;
        case PACKET_COMMAND_ECHOREQUEST:
        case PACKET_COMMAND_FINDECHOREQUEST:
            // Reply to a ping request
            $ret = $this->packet->sendReply($pkt, $pkt['From'], $pkt["Data"]);
            break;
        default:
            break;
        }

    }


    /**
     * This function polls the endpoints
     *
     * @return int
     * @todo This function needs to be split up into smaller, more managable bits
     */
    function poll()
    {
        if (!$this->doPoll) {
            return;
        }
        $epkeys = array_keys($this->ep);
        shuffle($epkeys);
        $count = 0;
        foreach ($epkeys as $key) {
            if ($GLOBALS["exit"]) {
                return 1;
            }
            $dev     =& $this->ep[$key];
            $devInfo =& $this->_devInfo[$key];
            if ($dev["PollInterval"] > 0) {
                print $dev["DeviceID"]." (".$dev["Driver"].")";
                print " -> ".date("Y-m-d H:i:s", $devInfo["PollTime"]);
                if ($devInfo["PollTime"] <= time()) {
                    $count ++;
                    $this->stats->incStat("Polls");
                    // print  " [".$dev["GatewayName"]."] ->";
                    $sensorRead = $this->endpoint->readSensors($dev);
                    $gotReply   = $this->_pollSensorData($devInfo, $sensorRead);

                    if ($gotReply === false) {
                        $devInfo["failures"]++;
                        $this->GetNextPoll($key);
                        $DevCount++;
                        print " No data returned (".$devInfo["failures"].")";
                    } else {
                        $this->stats->incStat("Poll Success");
                    }
                    print " Next:".date("Y-m-d H:i:s", $devInfo["PollTime"]);
                } else {
                    $t = round(($devInfo["PollTime"] - time())/60, 2);
                    print " Waiting ($t/".$dev["PollInterval"]." minutes)...";
                    if ($devInfo["failures"] > 0) {
                        print " ".$devInfo["failures"]." failures ";
                    }
                }
                print "\n";
            }
            if ($this->lastminute != date("i")) {
                break;
            }
        }
        return $count;
    }

    /**
    * This function deals with the polling data
    *
    * @param array &$devInfo   The devInfo array for the device
    * @param array $sensorRead The data from the sensor read
    *
    * @return bool Whether a valid reply was received
    */
    private function _pollSensorData(&$devInfo, $sensorRead)
    {
        if (!is_array($sensorRead) || (count($sensorRead) == 0)) {
            return false;
        }
        foreach ($sensorRead as $sensors) {
            if ($sensors['Reply'] != true) {
                continue;
            }
            $gotReply = true;
            if (is_array($sensors) &&
                (count($sensors) > 0) &&
                isset($sensors['RawData'])) {

                $sensors['DeviceKey'] = $dev['DeviceKey'];
                $devInfo["failures"]  = 0;
                if (!isset($sensors['DataIndex']) ||
                    ($devInfo['DataIndex'] != $sensors["DataIndex"])) {

                    $job     = (20+$this->myInfo["Job"]);
                    $sensors = plog::packetLogSetup($sensors, $dev, "POLL", $job);
                    $ret     = $this->plog->add($sensors);

                    if ($ret) {
                        print " Success ";
                        print "(".number_format($sensors["ReplyTime"], 2).")";
                        $devInfo['DataIndex'] = $sensors["DataIndex"];
                        $dev['LastPoll']      = $sensors['Date'];
                        $devInfo["LastPoll"]  = $sensors['Date'];
                        $this->GetNextPoll($key);
                        $this->lastContactTime = time();
                    } else {
                        $DevCount++;
                        print " Failed to store data \r\n";
                        $this->stats->incStat("Poll Store Failed");
                                    //print strip_tags(get_stuff($history));
                    }
                } else {
                    $this->stats->incStat("Poll Data Index Ident");
                    print " Data Index (".$sensors['DataIndex'].")";
                    print "Identical (".number_format($sensors["ReplyTime"], 2).")";
                    $this->GetNextPoll($key, 1);
                }
            }
        }
        return $gotReply;
    }
}

?>
