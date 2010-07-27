<?php
/**
 * Sends a ping to an endpoint
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
 */
$pktData = "";
require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/containers/PacketContainer.php';

if (empty($DeviceID)) {
    die("DeviceID must be specified!\r\n");
}
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($hugnet_config["verbose"]);

if (empty($Count)) {
    $Count = 1000000;
}
$pkt = new PacketContainer(
    array(
        "To" => $DeviceID,
        "Command" => isset($pktCommand) ? $pktCommand : "03",
        "Data" => $pktData,
        "GetReply" => true,
        "group" => "default",
    )
);

for ($i = 0; $i < $Count; $i++) {
    $ret = $pkt->ping(
        array(
            "Retries" => 1,
            "GetReply" => true,
            "group" => "default",
            "Data" => $config->stringSize(mt_rand(0, 16777215), 6)
                .$config->stringSize($i, 6),
        ),
        ($pktCommand != "02")
    );
    if ($ret) {
        print $pkt->Length." byte from ".$pkt->To." seq=$i ";
        print "ttl=".$pkt->Timeout." time=".round($pkt->replyTime(), 4)."\n";
    } else {
        print "No Reply seq $i\n";
    }
}
?>
