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
class endpoint extends endpointBase
{

    var $lastminute = 0;
    
    var $gwRemove = 360;
    var $test = false;
    
    public $exit = false;

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
        $config["partNum"] = ENDPOINT_PARTNUMBER;
        $config["Retries"] = 1;
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

        parent::__construct($config);
     }
    
    /**
     * Main routine
     * 
     * @return none
     */    
    function main() 
    {
        $this->powerup();
        $this->packet->packetSetCallBack('checkPacket', $this);
    
        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            if ($lastminute != date("i")) {
                print "Using: ".$this->myInfo['DeviceID']."\r\n";
                $this->checkAllGW();
                $this->setupMyInfo();
                $this->stats->setStat("PacketSN", substr($this->DeviceID, 0, 6));
                $this->stats->setStat("GatewayKey", (int)$this->config["GatewayKey"]);
                $this->stats->setStat("GatewayIP", (string)$this->config["GatewayIP"]);
                $this->stats->setStat("GatewayPort", (int)$this->config["GatewayPort"]);
            }
            $lastminute = date("i");
            if ($GLOBALS["exit"] == true) break;
            $packet = $this->checkPacketQ();
            if ($GLOBALS["exit"] == true) break;
            if ($packet === false) $packet = $this->endpoint->packet->monitor($this->config, 1);
            if ($GLOBALS["exit"] == true) break;
        }
    
    }
        
    /**
     * Returns random timeout
     *
     * @return int
     */
    function checkPacket($pkt) 
    {
        if (is_array($pkt)) {
            $this->stats->incStat("Pkts Received");
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
        if ($sent) $this->stats->incStat("From Me");
    
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
            print "Snt Pkt: F:".$this->DeviceID." - T:".$p['PacketTo']." C:".$p['sendCommand']." Id:".$p["id"]."\n";
            $packet = $this->endpoint->packet->sendPacket($this->config, array($pk));
            $this->stats->incStat("Sent User Packet");
            if (is_array($packet)) {
                foreach ($packet as $pkt) {
                    $lpkt = plog::packetLogSetup($pkt, $p, "REPLY");
                    $lpkt["id"] = $p["id"];
                    $this->plog->update($lpkt);
                    print "Got Pkt: F:".$lpkt["PacketFrom"]." - T:".$lpkt["PacketTo"]. " C:".$lpkt["Command"]." Id:".$lpkt["id"]." RTime:".$lpkt["ReplyTime"]."\n";
                }
                $this->stats->incStat("Sent Packet Success");
            }
            if (empty($packet)) {
                $this->plog->remove($p["id"]);
                $this->findDev($p["PacketTo"]);
            }
        }
        $this->last = $now;
        // This removes the packets from more than an hour ago.
        $this->plog->removeWhere("`Date` < ? AND Checked < 10", array(date("Y-m-d H:i:s", time() - 3600)));
        return (bool) count($packets);
    }

    /**
     * This function attempts to find a device that is not responding
     *
     * @param string $id The deviceID of the device to find
     *
     * @return bool|array false on failure, packet on success
     */
    function findDev($id)
    {
        $dev = $this->config;
        $dev["DeviceID"] = $id; 
        print "Snt Pkt: F:".$this->DeviceID." - T:".$dev['DeviceID']." C:".PACKET_COMMAND_FINDECHOREQUEST." - From Me!\n";
        $ret = $this->endpoint->packet->ping($dev, true, false);        
        return $ret;
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
            $b = $this->gw->getWhere("`DeviceID` = ?", array(devInfo::hexify($gw, 6)));
            if (is_array($p[0])) $p = $p[0];
            if (!isset($p["DeviceID"])) $p["DeviceID"] = devInfo::hexify($gw, 6);
            if (!isset($p["GatewayKey"])) $p["GatewayKey"] = $this->config["GatewayKey"];
            print "Force check of gateway ".$p["DeviceID"]."\n";
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
        $this->gw->removeWhere("LastContact < ?", array(date("Y-m-d H:i:s", (time() - $this->gwRemove))));
        // This gets everything
        $ret = $this->gw->getWhere("`Job` = ? AND `Name` <> ?", array($this->myInfo["Job"], $this->myInfo["Name"]));
        if (!is_array($ret)) return;
        foreach ($ret as $gw) {
            print "Checking gateway ".$gw["DeviceID"]."\n";
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
        $this->stats->incStat("From Me");
        print "Snt Pkt: F:".$this->DeviceID." - T:".$pkt['To']." C:".$pkt["Command"]." - From Me!\r\n";
        $reply = $this->packet->sendPacket($this->config, array($pkt), true, 2);
        if (!is_array($reply)) return;
        foreach($reply as $p) {
            $this->stats->incStat("To Me");
            print "Got Pkt: F:".$p["From"]." - T:".$p['To']." C:".$p["Command"]." - To Me!\r\n";
        }
        $config = $this->interpConfig($reply);
        $config["LastContact"] = date("Y-m-d H:i:s");
        if ($this->gw->replace($config)) {
            print "Gateway ".$gw["DeviceID"]." config saved\n";
            $sn = hexdec($gw["DeviceID"]);
            unset($this->gwCheck[$sn]);
        }
    }


}

?>
