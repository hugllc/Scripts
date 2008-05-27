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
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: epPoll.php 1203 2008-04-14 21:17:46Z prices $    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
/** Packet log process stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Process.php';
/** Packet log stats stuff */
require_once HUGNET_INCLUDE_PATH.'/database/ProcStats.php';

/**
 * Class for talking with endpoints
 *
 * @category   Test
 * @package    Scripts
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */ 
class endpoint
{

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = false;
    var $failureLimit = 5;
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
        $this->lastContactTime = time();
        $this->lastContactAttempt = time();
        $this->gateway =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);


        $config["table"] = "DebugPacketLog";
        $this->debugplog =& HUGnetDB::getInstance("Plog", $config); //new process(); = new plog($db, "DebugPacketLog");
        $this->debugplog->createTable();
        
        $config["table"] = "PacketSend";
        $this->psend =& HUGnetDB::getInstance("Plog", $config); // new plog($file, "PacketSend");
        $this->psend->createTable("PacketSend");
        $this->packet = &$this->endpoint->packet;

        if (!is_null($config["gateway"])) {
            $this->forceGateways($config["gateway"]);
        }

        $this->setupMyInfo();
     }
    
    /**
     * Main routine
     * 
     * @return none
     */    
    function main($while=1) 
    {
        $this->powerup();
        $this->packet->packetSetCallBack('checkPacket', $this);
    
        do {
            if ($lastminute != date("i")) {
                print "Using: ".$this->myInfo['DeviceID']."\r\n";
                $this->checkOtherGW();
                $this->setupMyInfo();
                $this->stats->setStat("Gateways", base64_encode(serialize($this->otherGW)));
                $this->stats->setStat("PacketSN", substr($this->DeviceID, 0, 6));
            }
            $lastminute = date("i");
            $packet = $this->checkPacketQ();
            if ($packet === false) $packet = $this->endpoint->packet->monitor($this->config, 1);

        } while ($while);
    
    }

    /**
     * Gets all of the gateways with $Key as their key or backup key
      */
    function getGateways($Key) 
    {
        $res = $this->gateway->get($Key);
        $this->GatewayKey = $Key;

        if (!is_array($res)) return false;

        $this->gw = $res;

    }
    /**
     *  Sets everything up when we start
      */
    function powerup() 
    {
        if ($this->gw[0] !== array()) {
            // Send a powerup packet.
            $pkt = array(
                'to' => $this->endpoint->packet->unsolicitedID,
                'command' => PACKET_COMMAND_POWERUP,
           );
            $this->endpoint->packet->sendPacket($this->gw[0], array($pkt), false);
        }
    }

    /**
     *  Force the gateway to be a specific one.  The array given
     *  must have the following keys:
     *  - GatewayKey is the database key for the gateway
     *  - GatewayIP is the IP address to contact the endpoints through
     *     defaults to 127.0.0.2 if not given
     *  - GatewayPort is the TCP port number to use to contact the gateways
     *     defaults to 2000 if not given
     *  - GatewayName is the name of the gateway (Optional)
     *
     * @param array $gw The gateway array
     *
     * @return null
     */
    function forceGateways($gw) 
    {
        if (empty($gw['GatewayIP'])) $gw['GatewayIP'] = '127.0.0.1';
        if (empty($gw['GatewayPort'])) $gw['GatewayPort'] = '2000';
        $this->gw = array(0 => $gw);
        $this->GatewayKey = $gw['GatewayKey'];
        $this->stats->setStat('GatewayKey', $this->GatewayKey);
        $this->stats->setStat('GatewayIP', $gw['GatewayIP']);
        $this->stats->setStat('GatewayPort', $gw['GatewayPort']);
        $this->packet->connect($this->gw[0]);
    }

    /**
     * Returns random timeout
     *
     * @return int
     */
    function devGateway($key) 
    {
        if (is_array($this->ep[$key])) {
            if (is_array($this->gw[$this->_devInfo[$key]["gwIndex"]])) {
                $this->ep[$key] = array_merge($this->ep[$key], $this->gw[$this->_devInfo[$key]["gwIndex"]]);
            } else if (is_array($this->gw[0])) {
                $this->ep[$key] = array_merge($this->ep[$key], $this->gw[0]);
                $this->_devInfo[$key]["gwIndex"] = 0;                    
            } else {
                // Leave it as is.  We don't know what gateway this is.
            }
        }
    }
    /**
     * Returns random timeout
     *
     * @return int
     */
         function randomizegwTimeout() 
    {
        return (time() + mt_rand(120, 420));
    }
    
    /**
     * Returns random timeout
     *
     * @return int
     */
    function qPacket($gw, $to, $command, $data="", $timeout=0) 
    {
  
        $pkt = $this->packet->buildPacket($to, $command, $data);
        $pkt['Timeout'] = $timeout;
        $pkt['gw'] = $gw;
        $this->packetQ[] = $pkt;
        return $ret;
    }
    

    /**
     * Returns random timeout
     *
     * @return int
     */
    function checkPacket($pkt) 
    {
        if (is_array($pkt)) {
            $pkt["Type"] = $Type = plog::packetType($pkt);
            // Add it to the debug log
            $lpkt = plog::packetLogSetup($pkt, $this->myInfo, $Type);
            $this->plog->add($lpkt);
            if ($pkt["Reply"] && !$pkt['isGateway']) $this->plog->add($lpkt);

            // Do some printing if we are not otherwise working
            if ($this->myInfo['doPoll'] !== true) $v = true;
            if ($v) print "Got Pkt: F:".$pkt["From"]." - T:".$pkt["To"]." -".$pkt["Command"];
            if ($pkt['toMe']) {
                if ($v) print " - To Me! ";
                $this->checkPacketToMe($pkt);
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
            //$this->qPacket($this->gw[0], $pkt['From'], PACKET_COMMAND_REPLY, e00392601::getConfigStr($this->myInfo));
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
     * Deals with unsolicited packets we get
     *
     * @param array $pkt The packet array
     *
     * @return null
     */
    protected function checkPacketUnsolicited($pkt)
    {
        if ($this->myInfo['doUnsolicited']) {
            $this->stats->incStat("Unsolicited");
            $Type = "UNSOLICITED";
            switch(trim(strtoupper($pkt['Command']))) {
                case PACKET_COMMAND_POWERUP:
                case PACKET_COMMAND_RECONFIG:
                    if (!$pkt['isGateway']) {
                        $pkt['DeviceID'] = trim(strtoupper($pkt['From']));
                        if (isset($this->ep[$pkt['DeviceID']])) {
                                $this->_devInfo[$pkt['DeviceID']]["failures"] = 0;
                                unset($this->_devInfo[$pkt['DeviceID']]['failedcCheck']);
                                unset($this->_devInfo[$pkt['DeviceID']]['failedCheck']);
                                unset($this->_devInfo[$pkt['DeviceID']]['nextCheck']);
                                $this->getNextPoll($pkt['DeviceID']);                                        
                                $pkt['DeviceKey'] = $this->ep[$pkt['DeviceID']]['DeviceKey'];
                                $this->_devInfo[$pkt['DeviceID']]['GetConfig'] = true;
                        } else {
                            $this->ep[$pkt['From']] = $pkt;
                            $this->ep[$pkt['From']]['DeviceID'] = $pkt['From'];
                            $this->_devInfo[$pkt['From']]['GetConfig'] = true;
                            $this->ep[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                            $this->_devInfo[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                        }
                    }
                    break;
            }
            $lpkt = plog::packetLogSetup($pkt, $this->gw[0], $Type);
            $this->plog->add($lpkt);
        }
    
    }


    function checkPacketQ() 
    {
        $now = date("Y-m-d H:i:s");
        $packets = $this->plog->getWhere("Date >= ? AND Date < ? AND Type='OUTGOING'", array($this->last, $now));
        foreach ($packets as $p) {
            $pk = array(
                'to' => $p['PacketTo'],
                'command' => $p['sendCommand'],
                'data' => $p['RawData'],
           );
            print $p['PacketTo']." -> Sending user Packet -> ".$p['sendCommand']." -> ";
            $packet = $this->endpoint->packet->sendPacket($this->gw[0], array($pk));
            $this->stats->incStat("Sent User Packet");
            if (is_array($packet)) {
                foreach ($packet as $pkt) {
                    $lpkt = plog::packetLogSetup($pkt, $p, "REPLY");
                    $this->plog->add($lpkt);
                }
                print "Success";
                $this->stats->incStat("Sent Packet Success");
            } else {
                print "Failed";
            }
            $this->psend->remove($p);
            print "\n";
            $this->last = $now;
        }
        return (bool) count($packets);
    }


    function checkOtherGW() 
    {

        $doPoll = true;
        $doConfig = true;
        $doCCheck = true;
        $doUnsolicited = true;
        $maxPriority = 0;
        foreach ($this->otherGW as $key => $gw) {
            if ($this->otherGW[$key]['failedCheckGW'] != date("i")) {
                $expired = false;
                if ($gw['RemoveTime'] < time()) {
                    unset($this->otherGW[$key]);
                    print "Removed ".$gw['DeviceID'];
                } else if ($key == "Wait") {
                    print "Waiting before Polling... ".date("Y-m-d H:i:s", $gw['RemoveTime']);
                    $doPoll = false;
                    $doConfig = false;
                    $doCCheck = false;
                    $doUnsolicited = false;
                    $maxPriority = 0xFFFF;  // Real priorities should never be this high.
                } else {
                    print "Checking in with Gateway ".$gw['DeviceID'].":\n"; 
                    $pkt = $this->packet->buildPacket($gw['DeviceID'], PACKET_COMMAND_GETSETUP);
                    $ret = $this->packet->sendPacket($this->gw[0], array($pkt), true, 2);
                    if (is_array($ret)) {
                        foreach ($ret as $p) {
                            $this->CheckPacket($p);
                        }
                        print " Done ";
                        unset($this->otherGW[$key]['failedCheckGW']);
        
                    } else {
                        print " Failed ";
                         $this->otherGW[$key]['failedCheckGW'] = date("i");
                        if ($gw['ConfigExpire'] < time()) {
                            $this->otherGW[$key]['doPoll'] = false;
                            $this->otherGW[$key]['doConfig'] = false;
                            $this->otherGW[$key]['doCCheck'] = false;
                            $this->otherGW[$key]['doUnsolicited'] = false;
                            $expired = true;
                            print " Expired ";
                        } else {
                            print date("Y-m-d H:i:s", $gw['ConfigExpire']);
                        }
        
                    }
                    print " GW:".$this->otherGW[$key]['GatewayKey'];
                    print " P:".$this->otherGW[$key]['Priority'];
                    print " IP:".$this->otherGW[$key]['IP'];
                    print " Name:".$this->otherGW[$key]['Name'];
                    if ($this->otherGW[$key]['Priority'] == $this->myInfo['Priority']) {
                        $this->setPriority();
                    }
                    if (($maxPriority < $this->otherGW[$key]['Priority']) && !$expired)  {
                        if ($this->otherGW[$key]["GatewayKey"] == $this->gw[0]["GatewayKey"]) {
                            $maxPriority = $this->otherGW[$key]['Priority'];
                        }
                    }
                    print " Jobs: ";

                    if ($this->otherGW[$key]['doPoll']) {
                        $doPoll = false;
                        print " Polling";
                    }            
                    if ($this->otherGW[$key]['doConfig']) {
                        $doConfig = false;
                        print " Config";
                    }            
                    if ($this->otherGW[$key]['doCCheck']) {
                        $doCCheck = false;
                        print " CCheck";
                    }            
                    if ($this->otherGW[$key]['doUnsolicited']) {
                        $doUnsolicited = false;
                        print " Unsolicited";
                    }            
        
                }
                print "\r\n";
            }
        }
        if ($maxPriority < $this->myInfo['Priority']) {
            $doPoll = true;
            $doConfig = true;
            $doCCheck = true;
            $doUnsolicited = true;
        } else {
            $doPoll = false;
            $doConfig = false;
            $doCCheck = false;
            $doUnsolicited = false;        
        }
        $this->myInfo['doPoll'] = $doPoll;
        $this->myInfo['doConfig'] = $doConfig;
        $this->myInfo['doCCheck'] = $doCCheck;
        $this->myInfo['doUnsolicited'] = $doUnsolicited;
    }

    /**
     * This sets up my info so that I look like an endpoint.
     *
     * @return none
     */
    function setupMyInfo() 
    {
        $this->myInfo['DeviceID'] = $this->packet->SN;
        $this->DeviceID = $this->myInfo['DeviceID'];
        $this->myInfo['SerialNum'] = $this->packet->SN;

        $this->myInfo['HWPartNum'] = POLL_PARTNUMBER;
        $this->myInfo['FWPartNum'] = POLL_PARTNUMBER;
        $this->myInfo['FWVersion'] = POLL_VERSION;    
        $this->myInfo['GatewayKey'] = $this->gw[0]["GatewayKey"];

        // I know this works on Linux
        $Info =`/sbin/ifconfig|grep Bcast`;
        $Info = explode("  ", $Info);
        foreach ($Info as $key => $val) {
            if (!empty($val)) {
                $t = explode(":", $val);
                $netInfo[trim($t[0])] = trim($t[1]);
            }
        }
        $this->myInfo['IP'] = $netInfo["inet addr"];

        $this->myInfo['Name'] = trim($this->uproc->me['Host']);
        if (!empty($this->uproc->me['Domain'])) $this->myInfo['Name'] .= ".".trim($this->uproc->me['Domain']);


    }

    function interpConfig($pkt) 
    {
        if (!is_array($pkt)) return;

        $newConfig = $this->endpoint->InterpConfig($pkt);
        if ($newConfig['isGateway']) {

            $newConfig['RemoveTime'] = time() + $this->gwRemove;
            $newConfig['ConfigExpire'] = $this->randomizegwTimeout();
            $newConfig['RemoveTimeDate'] = date("Y-m-d H:i:s", $newConfig['RemoveTime']);
            $newConfig['ConfigExpireDate'] = date("Y-m-d H:i:s", $newConfig['ConfigExpire']);
            $send = array("FWVersion", "Priority", "myGatewayKey", "NodeName", "NodeIP",
                          "doPoll", "doConfig", "doCCheck", "doUnsolicited", 'ConfigExpire');
            if (!is_array($this->otherGW[$newConfig['DeviceID']])) {
                $this->otherGW[$newConfig['DeviceID']] = $newConfig;
            } else {
                $this->otherGW[$newConfig['DeviceID']] = array_merge($this->otherGW[$newConfig['DeviceID']], $newConfig);
            }
        } else if ($newConfig['sendCommand'] == PACKET_COMMAND_GETSETUP) {
            if (!is_null($newConfig['DeviceKey'])) {
                $devKey = $newConfig['DeviceKey'];
            } else {
                $devKey = $this->findDev($pkt['From']);
            }
            if ($devkey === false) {
                $devKey = $newConfig['DeviceID'];
            }
            if (is_array($this->ep[$devKey])) $this->ep[$devKey] = array_merge($this->ep[$devKey], $newConfig);
            $this->GetNextPoll($devKey);
            $this->_devInfo[$devKey]['GetConfig'] = false;
        }
        
        return $newConfig;
    }
    
    function criticalFailure($reason) 
    {
        $last = (int) $this->stats->getStat("LastCriticalError", $this->uproc->me['Program']);
        
        if (is_null($last)) {
            $last = $this->last;
        }
        if ((time() - $last) > ($this->critTime * 60)) { 
            $to = "hugnet@hugllc.com";
            $from = "".$this->uproc->me['Host']."<noreply@hugllc.com>";
            $subject = "HUGnet Critical Failure on ".`hostname`."!";
            $message = $reason;
            mail ($to, $subject, $message);
            $this->last = time();

        }
        $this->stats->setStat("LastCriticalError", time());

    }

}

?>
