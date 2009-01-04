<?php
/**
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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
$pktData = "";
require_once(dirname(__FILE__).'/../head.inc.php');

if (empty($DeviceID)) {
    die("DeviceID must be specified!\r\n");    
}

$pkt = array();
$Info["DeviceID"] = $DeviceID;
$pkt["To"] = strtoupper($DeviceID);
if (isset($pktCommand)) {
    $pkt["Command"] = $pktCommand;
} else {
    $pkt["Command"] = "02";
}
$pkt["Data"] = $pktData;

print "Using GatewayKey ".$GatewayKey."\n";

unset($hugnet_config["servers"]);
$hugnet_config['GatewayIP']   = $GatewayIP;
$hugnet_config['GatewayPort'] = $GatewayPort;
$hugnet_config['GatewayName'] = $GatewayIP;
$hugnet_config['GatewayKey']  = $GatewayKey;
$hugnet_config['socketType'] = "db";
$hugnet_config['socketTable'] = "PacketLog";
$hugnet_config['packetSNCheck'] = false;

$endpoint =& HUGnetDriver::getInstance($hugnet_config);

$pkt = $endpoint->packet->SendPacket($Info, $pkt);

if (is_array($pkt)) {
    foreach ($pkt as $p) {
        if (($p["From"] == $p["SentTo"]) || $p["group"]) {
            print_r($p);
            if (is_array($p["Data"])) {
                foreach ($p["Data"] as $key => $val) {
                    print $key ."\t=> ".$val."\t=> ".dechex($val)."\t=> ".str_pad(decbin($val), 8, "0", STR_PAD_LEFT)."\n";
                }
            }
        }
    }
} else {
    print "Nothing Returned\r\n";
}
$endpoint->packet->Close($Info);
die();

?>
