<?php
/**
 * Monitors incoming packets
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
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
/** HUGnet code */
require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
define("MONITOR_SVN", '$Id$');

print 'monitor.php Version '.MONITOR_SVN."\n";
print "Starting...\n";

print "Using GatewayKey ".$GatewayKey."\n";

unset($hugnet_config["servers"]);
$hugnet_config['GatewayIP']     = $GatewayIP;
$hugnet_config['GatewayPort']   = $GatewayPort;
$hugnet_config['GatewayName']   = $GatewayIP;
$hugnet_config['GatewayKey']    = $GatewayKey;
$hugnet_config['socketType']    = "db";
$hugnet_config['socketTable']   = "PacketLog";
$hugnet_config['packetSNCheck'] = false;

$endpoint =& HUGnetDriver::getInstance($hugnet_config);

while (1) {

    $packets = $endpoint->packet->monitor();
    foreach ($packets as $pkt) {
        if (!is_array($pkt)) {
            continue;
        }
        print "From: ".$pkt['From'];
        print " -> To: ".$pkt['To'];
        if (empty($pkt["Command"])) {
            print "  Command: ".$pkt['sendCommand'];
        } else {
            print "  Command: ".$pkt['Command'];
        }
        print "\r\n";
        if (!empty($pkt['RawData'])) {
            print "Data: ".$pkt['RawData']."\r\n";
        }
    }
    $last = $now;
    sleep(1);
}

print "Finished\n";

?>
