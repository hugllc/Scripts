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
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
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
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */ 
class epPoll extends EndpointBase
{

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    var $doPoll = false;
    var $doCCheck = false;
    var $doConfig = false;
    var $doUnsolicited = false;
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = false;
    var $failureLimit = 3;
    var $Priority = 0;
    var $lastContactTime = 0; //!< Last time an endpoint was contacted.
    var $critTime = 60;

    /**
     *
     */    
    function __construct($config = array()) 
    {
        unset($config["servers"]);
        unset($config["table"]);
        $config["partNum"] = POLL_PARTNUMBER;
        $this->config = $config;
        $this->uproc =& HUGnetDB::getInstance("Process", $config); //new process();
        $this->uproc->createTable();
        
        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config); //new process(); new ProcStats();
        $this->stats->createTable();
        $this->stats->clearStats();        
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test = (bool) $config["test"];
        $this->verbose = (bool) $config["verbose"];
        $this->cutoffdate = date("Y-m-d H:i:s", (time() - (86400 * $this->cutoffdays)));
        $this->endpoint =& HUGnetDriver::getInstance($config);
        $this->endpoint->packet->getAll(true);

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config); //new plog();
        $this->plog->createTable();

        unset($config["table"]);
        $this->device =& HUGnetDB::getInstance("Device", $config); // new device($file);
        $this->gateway =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);

        $config["table"] = "DebugPacketLog";
        $this->debugplog =& HUGnetDB::getInstance("Plog", $config); //new process(); = new plog($db, "DebugPacketLog");
        $this->debugplog->createTable();
        
        $config["table"] = "PacketSend";
        $this->psend =& HUGnetDB::getInstance("Plog", $config); // new plog($file, "PacketSend");
        $this->psend->createTable("PacketSend");
        $this->packet = &$this->endpoint->packet;
        $this->setPriority();

        parent::__construct($config);
     }
    
    /**
     * Main routine for polling endpoints
     * This routine will
     */    
    function main() 
    {
        static $lastminute;
        $this->powerup();
        $this->packet->packetSetCallBack('checkPacket', $this);
        $this->uproc->register();
    
        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            if ($lastminute == date("i")) {
                print "Using: ".$this->myInfo['DeviceID']." Priority: ".$this->myInfo["Priority"]." - ".date("Y-m-d H:i:s")."\r\n";
                $this->getAllDevices();
            }
            $lastminute = date("i");
            $count = $this->poll();    
            
            if ($count == 0) $this->wait();
        }
        $this->uproc->unregister();
    
    }

    /**
     * Figures out the next time we should poll    
     *
     * @param string $key The key to use
     *
     * @return null
     */
    function GetNextPoll($key) 
    {
//        $time = time();
        $devInfo =& $this->_devInfo[$key];
        $dev =& $this->ep[$key];
        if ($dev["PollInterval"] <= 0) return;

        if (!isset($devInfo["gwIndex"])) $devInfo["gwIndex"] = 0;

        if ($devInfo['failures'] > $this->failureLimit) {
            $time = time();
        } else if (empty($devInfo["LastPoll"])) {
            $time = strtotime($dev["LastPoll"]);
        } else {
            $time = strtotime($devInfo["LastPoll"]);
        }
        
        $mult = (int) ($devInfo['failures'] / $this->failureLimit) + 1;
        if ($mult > 25) $mult = 25;
        
        // Poll interval is in minutes that is where the 60 comes from
        $newtime = $time + (60 * $mult * $this->ep[$key]["PollInterval"]);

        if ($devInfo["PollTime"] < $newtime) $devInfo["PollTime"] = $newtime;
    }
    
    
    function getAllDevices() 
    {

        print "Getting endpoints for Gateway #".$this->config["GatewayKey"];


        $query = "GatewayKey = ? AND PollInterval > 0";
        $res = $this->device->getWhere($query, array($this->config["GatewayKey"]));

        if (!is_array($res) || (count($res) == 0)) {
            $this->stats->incStat("Device Cache Failed");
        }
        if (is_array($res) && (count($res) > 0)) {
            $this->ep = array();
            foreach ($res as $key => $val) {
                if ($val['DeviceID'] !== "000000") {
                    $dev = $this->endpoint->DriverInfo($val);
                    $key = $val['DeviceID'];
                    $this->ep[$key] = $dev;
                    $this->getNextPoll($key);
                }
            }
        }
        print " (Found ".count($this->ep).")\n";
        return $this->ep;    
    }
    
    
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
        if (!$pkt['toMe']) return;
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
            //$this->qPacket($this->config, $pkt['From'], PACKET_COMMAND_REPLY, e00392601::getConfigStr($this->myInfo));
            $ret = $this->packet->sendReply($pkt, $pkt['From'], e00392601::getConfigStr($this->myInfo));
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
     * @return null
     */
    function poll() 
    {
        $epkeys = array_keys($this->ep);
        shuffle($epkeys);
        $count = 0;
        foreach ($epkeys as $key) {
            if ($GLOBALS["exit"]) return 1;
            $dev =& $this->ep[$key];
            $devInfo =& $this->_devInfo[$key];
            if ($dev["PollInterval"] > 0) {
                if ($devInfo["PollTime"] <= time()) { 
                    $count ++;
                    $this->stats->incStat("Polls");
                    print $dev["DeviceID"]." (".$dev["Driver"].") -> ".date("Y-m-d H:i:s", $devInfo["PollTime"]);
                    // print  " [".$dev["GatewayName"]."] ->";                    
                    $sensorRead = $this->endpoint->readSensors($dev);
                    $gotReply = false;
                    if (is_array($sensorRead) && (count($sensorRead) > 0)) {
                        foreach ($sensorRead as $sensors) {
                            if ($sensors['Reply'] == true) {
                                $gotReply = true;
                                if (is_array($sensors) && (count($sensors) > 0) && isset($sensors['RawData'])) {
                                    $sensors['DeviceKey'] = $dev['DeviceKey'];
                                    $devInfo["failures"] = 0;
                                    if (!isset($sensors['DataIndex']) || ($devInfo['DataIndex'] != $sensors["DataIndex"])) {

                                        $sensors = plog::packetLogSetup($sensors, $dev, "POLL", (20+$this->myInfo["Job"]));
                                        $ret = $this->plog->add($sensors);

                                        if ($ret) {
                                            print " Success (".number_format($sensors["ReplyTime"], 2).")";
                                            $devInfo['DataIndex'] = $sensors["DataIndex"];
                                            $dev['LastPoll'] = $devInfo["LastPoll"] = $sensors['Date'];
                                            $this->GetNextPoll($key);
                                            $this->lastContactTime = time(); // Reset the last contact time
                                        } else {
                                            $DevCount++;
                                            print " Failed to store data \r\n"; //(".$history->Errno."): ".$history->Error;
                                            $this->stats->incStat("Poll Store Failed");
                                            //print strip_tags(get_stuff($history));
                                        }
                                    } else {
                                        $this->stats->incStat("Poll Data Index Ident");
                                        print " Data Index (".$sensors['DataIndex'].") Identical (".number_format($sensors["ReplyTime"], 2).")";
                                    }
                                }
                            }
                        }
                    }
                    if ($gotReply === false) {
                        $devInfo["failures"]++;
                        $this->GetNextPoll($key);
                        $DevCount++;
                        print " No data returned (".$devInfo["failures"].")";
                    } else {
                       $this->stats->incStat("Poll Success");                    
                    }
                    print " Next:".date("Y-m-d H:i:s", $devInfo["PollTime"])."\n";
                }
            }

        }
        return $count;
    }

}

?>
