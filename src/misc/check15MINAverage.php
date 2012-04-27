<?php
/**
 * Converts old raw history into new raw history
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
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
 * @category   Scripts
 * @package    Scripts
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
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
$config->verbose($hugnet_config["verbose"]+10);

$dev = new DeviceContainer(array("group" => $hugnet_config["group"]));
$dev->getRow(hexdec($DeviceID));
$date = gmmktime(09,45,00,10,05,2011);

$hist = &$dev->historyFactory(array("group" => $hugnet_config["group"]), true);
$hist->group = $hugnet_config["group"];
$hist->sqlLimit = 10;
$hist->verbose(10);
$hist->sqlOrderBy = "Date asc";

$avg = &$dev->historyFactory(array("group" => $hugnet_config["group"]), false);
$avg->verbose(10);
$ret = $hist->getPeriod($date, $date + 3600, $dev->id, "id");
if ($ret) {
    // Go through the records
    while ($avg->calcAverage($hist, AverageTableBase::AVERAGE_15MIN)) {
        var_dump($avg->toArray(false));
    }
}

?>
