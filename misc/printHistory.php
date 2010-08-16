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
require_once HUGNET_INCLUDE_PATH.'/containers/PacketContainer.php';
require_once HUGNET_INCLUDE_PATH.'/containers/DeviceContainer.php';
require_once HUGNET_INCLUDE_PATH."/tables/RawHistoryTable.php";

print "printHistory.php\n";
print "Starting...\n";

print "Using GatewayKey ".$GatewayKey."\n";

ConfigContainer::config("/etc/hugnet/config.inc.php");
$raw = new RawHistoryTable();
$raw->sqlLimit = 2;
$raw->sqlOrderBy = "Date ASC";

if (trim(strtoupper($pktData)) == "NOW") {
    $time = time();
} else if (is_numeric($pktData)) {
    $time = (int) $pktData;
} else {
    $time = (int) strtotime($pktData);
}
// Get the devices
$rows = $raw->select(
    "`id` = ? AND `Date` > ?",
    array(hexdec($DeviceID), $time)
);

$hist = &$rows[1]->toHistoryTable($rows[0]->Date);
var_export($hist->toArray());

print "Finished\n";

?>
