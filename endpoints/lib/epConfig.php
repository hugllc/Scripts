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
class epConfig extends endpointBase
{

    var $ep = array();
    var $doConfig = true;
    var $doCCheck = true;
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = false;
    var $failureLimit = 5;
    var $Priority = 0;
    var $lastContactTime = 0; //!< Last time an endpoint was contacted.
    var $lastContactAttempt = 0; //!< Last time an endpoint was contacted.
    var $critTime = 60;

    /**
     *
     */    
    function __construct($config = array()) 
    {
        unset($config["servers"]);
        unset($config["table"]);
        $config["partNum"] = CONFIG_PARTNUMBER;
        $this->config = $config;

        $this->uproc =& HUGnetDB::getInstance("Process", $config); //new process();
        $this->uproc->createTable();
        
        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config); //new process(); new ProcStats();
        $this->stats->createTable();
        $this->stats->clearStats();        
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test = $config["test"];
        $this->verbose = $config["verbose"];
        $this->cutoffdate = date("Y-m-d H:i:s", (time() - (86400 * $this->cutoffdays)));
        $this->endpoint =& HUGnetDriver::getInstance($config);
        $this->endpoint->packet->getAll(true);

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config); //new plog();
        $this->plog->createTable();

        unset($config["table"]);
        $this->device =& HUGnetDB::getInstance("Device", $config); // new device($file);
        $this->lastContactTime = time();
        $this->lastContactAttempt = time();
        $this->gateway =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);

        $this->gw =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);
        $this->gw->createLocalTable("LocalGW");

        parent::__construct($config);
     }
    
    /**
     * Main routine for polling endpoints
     * This routine will
     */    
    function main($while=1) 
    {
        $this->powerup();
        $this->endpoint->packet->packetSetCallBack('checkPacket', $this);
        $this->setPriority();

        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            $this->getAllDevices();
            print "Using: ".$this->myInfo['DeviceID']." Priority: ".$this->myInfo["Priority"]."\r\n";
            $this->controllerCheck();
            
//            $this->wait();
        }
    
    }
    /**
     * Sets the priority we run at.
      */
    function setPriority() 
    {
        if ($this->test) {
            $this->myInfo['Priority'] = 0xFF;
        } else {
            $this->myInfo['Priority'] = mt_rand(1, 0xFE);
        }
        $this->stats->setStat('Priority', $this->myInfo['Priority']);
    }


    /**
     * Figures out the next time we should poll    
     * @param $time
     * @param $Interval
     * @param $failures
     * @return The time of the next poll
     */
    function GetNextPoll($key, $time=null) 
    {
//        $time = time();
        if ($this->ep[$key]["PollInterval"] <= 0) return;

        if (!isset($this->_devInfo[$key]["gwIndex"])) $this->_devInfo[$key]["gwIndex"] = 0;

        if ($time == null) {
            $lastpoll1 = strtotime($this->ep[$key]["LastPoll"]);
            $lastpoll2 = strtotime($this->_devInfo[$key]["LastPoll"]);
            if ($lastpoll1 > $lastpoll2) {
                $time = $lastpoll1;
            } else {
                $time = $lastpoll2;            
            }
        }
        $Interval = $this->ep[$key]["PollInterval"];
        if ($this->_devInfo[$key]['failures'] > 0) {
            $Interval = ($this->_devInfo[$key]['failures'] / $this->failureLimit) * $Interval; 
        }
           if ($Interval > 240) $Interval = 240;

        $Interval = (int) $Interval;

        $sec = 0; //date("s", $time);
        $min = date("i", $time) + $Interval;
        $hour = date("H", $time);
        $mon = date("m", $time);
        $day = date("d", $time);
        $year = date("Y", $time);
    
        $newtime = mktime($hour, $min, $sec, $mon, $day, $year);
        if ($this->_devInfo[$key]["PollTime"] < $newtime) {
            $this->_devInfo[$key]["PollTime"] = $newtime;
        }
    }
    
    
    function getAllDevices() 
    {

        print "Getting endpoints for Gateway #".$this->config["GatewayKey"];


        $query = "GatewayKey= ? ";
        $res = $this->device->getWhere($query, array($this->config["GatewayKey"]));

        if (!is_array($res) || (count($res) == 0)) {
            $this->stats->incStat("Device Cache Failed");
        }
        if (is_array($res) && (count($res) > 0)) {
            $this->ep = array();
            foreach ($res as $key => $val) {
                if ($val['DeviceID'] !== "000000") {
                    $dev = $this->endpoint->DriverInfo($val);
                    $k = $val['DeviceID'];
                    $this->ep[$k] = $dev;
//                        $this->devGateway($key);
                    if ($this->endpoint->isController($dev)) {
                        $this->_devInfo[$k]['GetConfig'] = true;
                        $this->_devInfo[$k]["Controller"] = true;
                    }
                    if (!isset($this->_devInfo[$k]["GetConfig"])) $this->_devInfo[$k]["GetConfig"] = false;
                }
            }
        }
        print " (Found ".count($this->ep).")\n";
        return $this->ep;    
    }
    
    

    function controllerCheck() 
    {
//        if (!$this->myInfo['doCCheck']) return;

        print "Checking Controllers...\n";
        foreach ($this->ep as $key => $dev)
        {
            if ($this->_devInfo[$key]["Controller"]) {
                if (($this->_devInfo[$key]["LastCheck"] + $this->ccTimeout) < time()) {
                    $this->checkDev($key);
                    $this->_devInfo[$key]['getConfig'] = true;
                }
            }
        }
    }
    
    function wait() 
    {
        $this->setupMyInfo();
        $this->stats->setStat("doCCheck", $this->myInfo['doCCheck']);
        $this->stats->setStat("doConfig", $this->myInfo['doConfig']);
        $this->stats->setStat("Gateways", base64_encode(serialize($this->otherGW)));
        $this->stats->setStat("PacketSN", substr($this->DeviceID, 0, 6));

        if (($this->lastContactAttempt - $this->lastContactTime) > (30 * 60)) {
            $this->criticalFailure("Last Poll at ".date("Y-m-d H:i:s", $this->lastContantTime));
        }
        
        do {
//            $this->getConfig();
//            if ($packet === false) $packet = $this->endpoint->packet->monitor($this->config, 1);
        } while(date("i") == $this->lastminute);
        $this->lastminute = date("i");
        
        print "Checking... ".date("Y-m-d H:i:s")."\n";
    }


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

    function checkPacket($pkt) 
    {
        if (is_array($pkt)) {
            $pkt["Type"] = $Type = plog::packetType($pkt);
            // Add it to the debug log
            $lpkt = plog::packetLogSetup($pkt, $dev, $Type);
            $this->debugplog->add($lpkt);
            $this->debugplog->removeWhere("Date < ? ", array(date("Y-m-d H:i:s", time() - (86400 * 30))));
            if ($pkt["Reply"] && !$pkt['isGateway']) $this->plog->add($lpkt);

            // Do some printing if we are not otherwise working
            if ($this->myInfo['doPoll'] !== true) $v = true;
            if ($v) print "Got Pkt: F:".$pkt["From"]." - T:".$pkt["To"]." -".$pkt["Command"];
            if ($pkt['toMe']) {
                if ($v) print " - To Me! ";
                $this->checkPacketToMe($pkt);
            } else if ($pkt['Unsolicited'] === true) {
                if ($v) print " - Unsolicited ";
                $this->checkPacketUnsolicited($pkt);
            }
            if ($pkt['isGateway']) {
                if ($v) print " - Gateway ";
                if (!isset($this->otherGW[$pkt['From']])) {
                    $pkt['DeviceID'] = $pkt['From'];
                    $this->otherGW[$pkt['From']] = array('DeviceID' => $pkt['From'], 'RemoveTime' => (time() + $this->gwRemove));
                    $ret = $this->qPacket($pkt, $pkt['From'], PACKET_COMMAND_GETSETUP, 30);
                }
            }
            if ($v) print "\r\n";

        }
    }


    /**
     * Deals with packets to me.
     *
     * @param array $pkt The packet array
     *
     * @return null
     */
    protected function checkPacketToMe($pkt)
    {
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

    function checkDev($key) 
    {
        if (!is_array($this->ep[$key])) return;
        if (empty($key)) return;
        
        $this->lastContactAttempt = time();
        $dev = $this->ep[$key];
        if (is_array($this->_devInfo[$key])) {
            $dev = array_merge($dev, $this->_devInfo[$key]);
        }
        
        print "Checking ".$dev["DeviceID"]." ";
        $this->stats->incStat("Device Checked");
        $pkt = $this->endpoint->readConfig($dev);
        $gotConfig = false;
        if ($pkt !== false) {
            $newConfig = $this->interpConfig($pkt);
            foreach ($pkt as $p) {
                if ($p !== false) {
                    if ($p["Reply"]) {
                        if (!isset($p['DeviceKey'])) $p['DeviceKey'] = $dev['DeviceKey'];
                        if (empty($dev['GatewayKey'])) $dev['GatewayKey'] = $this->GatewayKey;
                                   $logpkt = plog::packetLogSetup($p, $dev, "CONFIG");
                        if ($this->plog->add($logpkt)) {
                            print " Done (".number_format($p["ReplyTime"], 2).")";
                            $gotConfig = true;
                            $this->ep[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["GetConfig"] = false;
                            $this->_devInfo[$key]['LastCheck'] = time();
                            unset($this->_devInfo[$key]['failedCheck']);
                            $this->lastContactTime = time();
                        } else {
//                            print "Error: ".$this->plog->_sqlite->lastError();
                        }
                    }
                }
            }
            if ($gotConfig) {
                // If it is a controller board check the program
                $this->stats->incStat("Device Checked Success");
                if (method_exists($this->endpoint->drivers[$newConfig['Driver']], "checkProgram")) {
                    $this->stats->incStat("Check Program");
                    print " Checking Program ";
                    $ret = $this->endpoint->drivers[$dev['Driver']]->checkProgram($newConfig, $pkt, true);
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
                print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);
//                               $this->_devInfo[$key]["gwIndex"]++;
               }

        } else {
            print " Nothing Returned ";
             $ping = $this->endpoint->packet->ping($dev, true);
            $this->_devInfo[$key]['nextCheck'] = time()+300;
            print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);

        }
        print "\r\n";
    
    }

    function getConfig() 
    {
        if ($this->myInfo['doConfig'] !== true) return;
        
        foreach ($this->ep as $key => $dev) {
            if (empty($dev['DeviceID'])) {
                unset($this->ep[$key]);
            }
            if ((date("s") > 55) || (date("i") != $this->lastminute)) break;

            if (!isset($this->_devInfo[$key]['nextCheck']) || ($this->_devInfo[$key]['nextCheck'] < time())) {
                if (((strtotime($this->_devInfo[$key]["LastConfig"]) + $this->configInterval) < time()) || $this->_devInfo[$key]['GetConfig']) {
                      if ($this->_devInfo[$key]['failedCheck'] != date("i")) {
    
                        $this->checkDev($key);
                    }
                }
            }
        }

    }


}

?>
