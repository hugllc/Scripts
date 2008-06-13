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
class endpointBase
{

    var $lastminute = 0;
    
    /**
     * Construction
     *
     * @param array $config Configuration
     *
     * @return null
     */    
    function __construct($config = array()) 
    {

        $this->gw =& HUGnetDB::getInstance("Gateway", $config); // new gateway($file);
        $this->gw->createLocalTable("LocalGW");
        
        $this->setupMyInfo();
        $ret = $this->gw->removeWhere("`Job` = ? AND `IP` = ?", array($this->myInfo["Job"], $this->myInfo["IP"]));
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
     * Sets the priority we run at.
      */
    function getPriority() 
    {
        if ($this->test) {
            return 0xFF;
        } else {
            return mt_rand(1, 0xFE);
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
     * This sets up my info so that I look like an endpoint.
     *
     * @return none
     */
    function setupMyInfo() 
    {
        $this->myInfo['DeviceID'] = $this->endpoint->packet->SN;
        $this->DeviceID = $this->myInfo['DeviceID'];
        $this->myInfo['SerialNum'] = hexdec($this->endpoint->packet->SN);
        $this->myInfo['HWPartNum'] = $this->config["partNum"];
        $this->myInfo['FWPartNum'] = $this->config["partNum"];
        $this->myInfo['FWVersion'] = SCRIPTS_VERSION;    
        $this->myInfo['GatewayKey'] = $this->config["GatewayKey"];
        $this->myInfo['CurrentGatewayKey'] = $this->config["GatewayKey"];
        if (empty($this->myInfo["Priority"])) $this->myInfo['Priority'] = $this->getPriority();

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
        $this->myInfo['LastContact'] = date("Y-m-d H:i:s");
 
        $this->myInfo['Name'] = trim($this->uproc->me['Host']);
        if (!empty($this->uproc->me['Domain'])) $this->myInfo['Name'] .= ".".trim($this->uproc->me['Domain']);
        $this->myInfo["RawSetup"] = e00392601::getConfigStr($this->myInfo);

        $this->myInfo["Local"] = 1;
        $g = $this->gw->getWhere("Local = 1");
        foreach ($g as $gw) {
            $this->myInfo["Priorities"][$gw["Job"]] = $gw["Priority"];
        }
        $this->gw->replace($this->myInfo);

        foreach ($this->myInfo as $key => $value) {
            $this->stats->setStat($key, $value);
        }
    }

    /**
     * Check packets to send out
     *
     * @return bool
     */
    function getOtherPriorities() 
    {
        $this->gwPriorities = array();
        $g = $this->gw->getWhere("Local = 0");
        foreach ($g as $gw) {
            e00392601::interpConfig($gw);
            $this->gwPriorities[$gw["DeviceID"]] = $gw["Priorities"];
        }
    }
    /**
     * Returns true if we are the highest priority program of our job.  Returns the
     * deviceID if we are not the highest priority
     *
     * @return bool/string
     */
    function checkPriority(&$id, &$priority) 
    {
        foreach ($this->gwPriorities as $i => $p) {
            if ($p[$this->myInfo["Job"]] > $this->myInfo["Priority"]) {
                $id = $i;
                $priority = $p;
                return false;
            }
        }
        return true;
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

/**
 * Handles signals
 *    
 * @return none
 */
function endpoint_sig_kill($signo)
{
    print "Shutting Down\n";
    $GLOBALS["exit"] = true;    
}

if (function_exists(pcntl_signal)) pcntl_signal(SIGINT, "endpoint_sig_kill");
?>
