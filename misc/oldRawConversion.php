<?php
/**
 * Classes for dealing with devices
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
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
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */
die("\n\nDon't use this\n\n");


$pktData = "";
require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/tables/RawHistoryOldTable.php';
require_once HUGNET_INCLUDE_PATH.'/tables/RawHistoryTable.php';


ConfigContainer::config("/etc/hugnet/config.inc.php");
$raw = new RawHistoryTable();
$raw->sqlOrderBy = "Date asc";
$oldRaw = new RawHistoryOldTable();
$oldRaw->verbose(10);
$oldRaw->sqlOrderBy = "Date asc";

$id = hexdec($DeviceID);
$DeviceKey = $devicesArray[$id];
$DeviceID = "0000DD";
$id = 221;
$DeviceKey = 171;
print "Using:\n";
print "DeviceID: $DeviceID\n";
print "id: $id\n";
print "DeviceKey: $DeviceKey\n";
print "Database: $group\n";

$startTime = time();
$ret = $oldRaw->selectInto(
    "DeviceKey = ?",
    array($DeviceKey)
);
$count = 0;
$failed = 0;
$bad = 0;
$startTime = time();

$dev = new DeviceContainer(array("group" => $group));
$dev->getRow(361);


while ($ret) {
    $oldRaw->RawSetup = $dev->toSetupString();
    $raw = &$oldRaw->toRaw($group);
    if (is_object($raw)) {
        $ins = $raw->insertRow(true);
        if ($ins) {
            $count++;
            if ((($count % 100) == 0)) {
                print date("Y-m-d H:i:s", $raw->Date)."\n";
            }
        } else {
            $failed++;
        }
    } else {
        $bad++;
    }
    $ret = $oldRaw->nextInto();
}

if ($count > 0) {
    // State we did some uploading
    DeviceContainer::vprint(
        "Moved $count good raw history records ".
        date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last)." in "
        .(time() - $startTime)." s",
        HUGnetClass::VPRINT_NORMAL
    );
}
if ($failed > 0) {
    // State we did some uploading
    DeviceContainer::vprint(
        "$failed raw history records failed to insert ".
        date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
        HUGnetClass::VPRINT_NORMAL
    );
}
if ($bad > 0) {
    // State we did some uploading
    DeviceContainer::vprint(
        "$bad raw history records failed to insert ".
        date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
        HUGnetClass::VPRINT_NORMAL
    );
}


?>
