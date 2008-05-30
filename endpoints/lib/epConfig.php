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
     *
     * @param bool &$while Loop until this changes
     *
     * @return null
     */    
    function main() 
    {
        static $lastminute;

        $this->powerup();
        $this->endpoint->packet->packetSetCallBack('checkPacket', $this);
        $this->setPriority();
        $this->uproc->register();

        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            if ($lastminute != date("i")) {
                print "Using: ".$this->myInfo['DeviceID']." Priority: ".$this->myInfo["Priority"]." - ".date("Y-m-d H:i:s")."\r\n";
                $this->getAllDevices();
            }
            $lastminute = date("i");
            $checked = $this->configCheck();
            if ($checked == 0) $this->wait();
        }
        $this->uproc->unregister();
    
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
                        $this->_devInfo[$k]["Controller"] = true;
                    }
                    if (!isset($this->_devInfo[$k]["GetConfig"])) $this->_devInfo[$k]["GetConfig"] = false;
                }
            }
        }
        print " (Found ".count($this->ep).")\n";
        return $this->ep;    
    }
        
    function wait() 
    {
        $sleep = mt_rand(1, 6);
        $this->endpoint->packet->monitor($this->config, $sleep);
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

    /**
     * Deals with packets to me.
     *
     * @param array $pkt The packet array
     *
     * @return null
     */
    public function checkPacket($pkt)
    {
        $this->checkPacketUnsolicited($pkt);
        if (!$pkt['toMe']) return;
        $this->stats->incStat("To Me");
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
     * Deals with packets to me.
     *
     * @param array $pkt The packet array
     *
     * @return null
     */
    public function checkPacketUnsolicited($pkt)
    {
        if (!$pkt["Unsolicited"]) return;
print "HERE";
        $Command = trim(strtoupper($pkt['Command']));
        switch($Command) {
        case PACKET_COMMAND_POWERUP:
            $type = "Powerup";
            $this->_devInfo[$pkt["From"]]["nextCheck"] = 0;
            break;
        case PACKET_COMMAND_RECONFIG:
            $type = "Reconfig";
            $this->_devInfo[$pkt["From"]]["nextCheck"] = 0;
            break;
        default:
            break;
        }
        if (!empty($type)) print "\n$type packet F:".$pkt["From"]."\n";

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
                        $logpkt = plog::packetLogSetup($p, $dev, "CONFIG", (20+$this->myInfo["Job"]));
                        if ($this->plog->add($logpkt)) {
                            print " Done (".number_format($p["ReplyTime"], 2).")";
                            $gotConfig = true;
                            $this->ep[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $next = ($this->_devInfo[$key]["Controller"]) ? $this->ccTimeout : $this->configInterval;
                            $this->_devInfo[$key]["nextCheck"] = time() + $next;

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
               }

        } else {
            print " Nothing Returned";
            $this->_devInfo[$key]['nextCheck'] = time()+300;
            print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);

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
        $checked = 0;
        $epkeys = array_keys($this->ep);
        shuffle($epkeys);
        $count = 0;
        foreach ($epkeys as $key) {
            $dev =& $this->ep[$key];
            if ($GLOBALS["exit"]) return 1;
            if (empty($dev['DeviceID'])) {
                unset($this->ep[$key]);
            }
            if ($this->_devInfo[$key]['nextCheck'] < time()) {
                $checked++;
                $this->checkDev($key);
            }
        }
        return $checked;
    }


}

?>
