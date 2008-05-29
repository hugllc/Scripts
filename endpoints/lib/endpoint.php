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
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = false;
    var $failureLimit = 5;
    var $critTime = 60;

    /** List of gateways to check */
    public $gwCheck = array();
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

        $this->gw =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);
        $this->gw->createLocalTable("LocalGW");
        
        $config["table"] = "DebugPacketLog";
        $this->debugplog =& HUGnetDB::getInstance("Plog", $config); //new process(); = new plog($db, "DebugPacketLog");
        $this->debugplog->createTable();
        
        $config["table"] = "PacketSend";
        $this->psend =& HUGnetDB::getInstance("Plog", $config); // new plog($file, "PacketSend");
        $this->psend->createTable("PacketSend");
        $this->packet = &$this->endpoint->packet;

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
                $this->checkAllGW();
                $this->setupMyInfo();
                $this->stats->setStat("PacketSN", substr($this->DeviceID, 0, 6));
            }
            $lastminute = date("i");
            $packet = $this->checkPacketQ();
            if ($packet === false) $packet = $this->endpoint->packet->monitor($this->config, 1);

        } while ($while);
    
    }

    /**
     *  Sets everything up when we start
     */
    function powerup() 
    {
        // Send a powerup packet.
        $pkt = array(
            'to' => $this->endpoint->packet->unsolicitedID,
            'command' => PACKET_COMMAND_POWERUP,
        );
        $this->endpoint->packet->sendPacket($this->config, array($pkt), false);
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
    function checkPacket($pkt) 
    {
        if (is_array($pkt)) {
            $pkt["Type"] = $Type = plog::packetType($pkt);
            // Add it to the debug log
            $lpkt = plog::packetLogSetup($pkt, $this->myInfo, $Type);
            $this->plog->add($lpkt);
            if ($pkt['isGateway']) {
                $this->gwCheck[hexdec($pkt["From"])] = date("Y-m-d H:i:s");
            }
            // Do some printing if we are not otherwise working
            if ($this->myInfo['doPoll'] !== true) $v = true;
            if ($v) print "Got Pkt:".$pkt["id"]." F:".$pkt["From"]." - T:".$pkt["To"]." C:".$pkt["Command"];
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
        $Command = trim(strtoupper($pkt['Command']));
        $sent = true;
        switch($Command) {
        case PACKET_COMMAND_GETSETUP:
            // Get our setup            
            $ret = $this->packet->sendReply($pkt, $pkt['From'], e00392601::getConfigStr($this->myInfo));
            break;
        case PACKET_COMMAND_ECHOREQUEST:
        case PACKET_COMMAND_FINDECHOREQUEST:
            // Reply to a ping request
            $ret = $this->packet->sendReply($pkt, $pkt['From'], $pkt["Data"]);
            break;
        default:
            $sent = false;
            break;
        }
        if ($sent) print "\r\nSnt Pkt: F:".$this->DeviceID." - T:".$pkt['From']." C:01 - From Me!";
    
    }
    /**
     * Check packets to send out
     *
     * @return bool
     */
    function checkPacketQ() 
    {
        $now = date("Y-m-d H:i:s");
        $packets = $this->plog->getWhere("Type='OUTGOING'", array());
        foreach ($packets as $p) {
            $from = $p["PacketFrom"];
            $pk = array(
                'to' => $p['PacketTo'],
                'command' => $p['sendCommand'],
                'data' => $p['RawData'],
           );
            print "Snt Pkt:".$p["id"]." T:".$p['PacketTo']." C:".$p['sendCommand']."\n";
            $packet = $this->endpoint->packet->sendPacket($this->config, array($pk));
            $this->stats->incStat("Sent User Packet");
            if (is_array($packet)) {
                foreach ($packet as $pkt) {
                    $lpkt = plog::packetLogSetup($pkt, $p, "REPLY");
                    $lpkt["id"] = $p["id"];
                    $this->plog->update($lpkt);
                    print "Got Pkt:".$lpkt["id"]." F:".$lpkt["PacketFrom"]." T:".$lpkt["PacketTo"]. " C:".$lpkt["Command"]." RTime:".$lpkt["ReplyTime"]."\n";
                }
                $this->stats->incStat("Sent Packet Success");
            }
            if (empty($packet)) $this->plog->remove($p["id"]);
        }
        $this->last = $now;
        // This removes the packets from more than an hour ago.
        $this->plog->removeWhere("`Date` < ?", array(date("Y-m-d H:i:s", time() - 3600)));
        return (bool) count($packets);
    }


    /**
     * Checks other gateways
     *
     * @return bool
     */
    function checkAllGW() 
    {
        $this->_checkAllGWdb();
        if (!is_array($this->gwCheck)) return;
        foreach ($this->gwCheck as $gw => $val) {
            $p = array(
                "DeviceID" => devInfo::hexify($gw, 6),
                "GatewayKey" => $this->config["GatewayKey"],
            );
            $this->checkGW($p);
        }
        $this->gwCheck = array();
        
    }
    /**
     * Checks other gateways
     *
     * @return bool
     */
    private function _checkAllGWdb() 
    {
        // This gets rid of old stuff
        $this->gw->removeWhere("LastContact < ?", array(date("Y-m-d H:i:s", time() - $this->gwRemove)));
        // This gets everything
        $ret = $this->gw->getAll();
        if (!is_array($ret)) return;
        foreach ($ret as $gw) {
            // This saves us the time of actually contacting the gateway
            // It uses the date of the last packet we saw from this gateway
            // to set the LastContact date
            $sn = hexdec($gw["DeviceID"]);
            if (!empty($this->gwCheck[$sn])) {
                $p = array(
                    "DeviceID" => $gw["DeviceID"],
                    "LastContact" => $this->gwCheck[$sn],
                );
                $this->gw->update($p);
                unset($this->gwCheck[$sn]);
                print "Updated LastContact to ".$p["LastContact"]." on ".$p["DeviceID"]."\r\n";
                continue;
            }
            // Check the gateway
            $this->checkGW($gw);
        }
    }
    /**
     * Checks other gateways
     *
     * @param array $gw The gateway to check
     *
     * @return bool
     */
    function checkGW($gw) 
    {
        if (empty($gw["DeviceID"])) continue;
        $pkt = $this->packet->buildPacket($gw['DeviceID'], PACKET_COMMAND_GETSETUP);
        print "Snt Pkt: F:".$this->DeviceID." - T:".$pkt['To']." C:".$pkt["Command"]." - From Me!\r\n";
        $reply = $this->packet->sendPacket($this->config, array($pkt), true, 2);
        if (!is_array($reply)) return;
        foreach($reply as $p) print "Got Pkt: F:".$p["From"]." - T:".$p['To']." C:".$p["Command"]." - To Me!\r\n";

        $config = $this->interpConfig($reply);
        $config["LastContact"] = date("Y-m-d H:i:s");
        if ($this->gw->replace($config)) print "Gateway ".$gw["DeviceID"]." config saved\n";
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
        $this->myInfo['SerialNum'] = hexdec($this->packet->SN);

        $this->myInfo['HWPartNum'] = ENDPOINT_PARTNUMBER;
        $this->myInfo['FWPartNum'] = ENDPOINT_PARTNUMBER;
        $this->myInfo['FWVersion'] = SCRIPTS_VERSION;    
        $this->myInfo['GatewayKey'] = $this->config["GatewayKey"];

        $this->myInfo['Job'] = e00392601::getJob($this->myInfo);

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
    
    /**
     * Check packets to send out
     *
     * @param array $pkt The packet to interpret
     *
     * @return bool
     */
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
        }
        
        return $newConfig;
    }
    
    /**
     * Sets a critical failure
     *
     * @param string $reason The reason for the error
     *
     * @return null
     */
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
