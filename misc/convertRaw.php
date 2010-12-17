<?php
/**
 * Converts old raw history into new raw history
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
require_once HUGNET_INCLUDE_PATH.'/tables/GenericTable.php';

$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($hugnet_config["verbose"]);

$oldRaw = new GenericTable(array("group" => "old"));
$oldRaw->forceTable("history_raw");
$oldRaw->sqlLimit = 1000;

$newRaw = new RawHistoryTable();

$oldDev = new GenericTable(array("group" => "old"));
$oldDev->sqlID = "DeviceKey";
$oldDev->forceTable("devices");

$dev = new DeviceContainer();
$start = 0;
$ret = true;
while (1) {
    $oldRaw->sqlStart = $start;
    print "Reading Database...\n";
    $ret = $oldRaw->selectInto(1);
    if (!$ret) {
        break;
    }
    print "Moving Raw History...\n";
    $count = 0;
    while ($ret) {
        $newRaw->clearData();
        $dev->clearData();
        $dev->fromSetupString($oldRaw->RawSetup);
        $time = $oldRaw->unixDate($oldRaw->Date, "UTC");
        $pkt = new PacketContainer(
            array(
               "To" =>  $dev->DeviceID,
               "Command" => $oldRaw->sendCommand,
               "Time" => $time - $oldRaw->ReplyTime,
               "Date" => $time - $oldRaw->ReplyTime,
               "Reply" => new PacketContainer(
                    array(
                       "From" => $dev->DeviceID,
                       "Command" => PacketContainer::COMMAND_REPLY,
                       "Data" => $oldRaw->RawData,
                       "Length" => strlen($oldRaw->RawData)/2,
                       "Time" => $time,
                       "Date" => $time,
                    )
                ),
            )
        );
        $newRaw->fromArray(
            array(
                "id" => hexdec($dev->DeviceID),
                "Date" => $oldRaw->unixDate($oldRaw->Date, "UTC"),
                "packet" => $pkt,
                "device" => $dev,
                "command" => $oldRaw->sendCommand,
                "dataIndex" => $dev->dataIndex($oldRaw->RawData),
            )
        );
        $count++;
        $prev = null;
        $hist =& $newRaw->toHistoryTable($prev);
        $newRaw->insertRow();
        $ret = $oldRaw->nextInto();
    }
    $start += $oldRaw->sqlLimit;
    print "Finished $count Records ($start Total)\n";
}



?>
