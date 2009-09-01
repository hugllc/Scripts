<?php
/**
 * Checks for the last sensor read, poll and config
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
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: averages.dfp.php 873 2008-02-06 20:16:29Z prices $
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/**
 * Checks to see when the last sensor read was.  It it was mroe than an
 * hour ago it creates an alarm.
 *
 * @param object &$obj The alarm object to play with
 *
 * @return none
 */
function checkLastSensorRead(&$obj)
{
    $value = $obj->stats->getStat("LastSENSORREAD", "endpoint.php");
    $msg   = "The last sensor read happened more than 30 minutes ago at ".$value."!";
    if (strtotime($value) < (time() - 1800)) {
        $obj->criticalError(
            "alarm.php:checkLastSensorRead",
            HUGNET_ERROR_OLD_SENSOR_READ,
            $msg
        );
        $obj->debug($msg."\n", 3);
    }
}

$this->registerFunction("checkLastSensorRead", "hourly", "Last Sensor Read");


/**
 * Checks to see when the last poll was in the database.
 *
 * @param object &$obj The alarm object to play with
 *
 * @return none
 */
function checkLastPoll(&$obj)
{
    $cutoff  = date("Y-m-d H:i:s", time() - 3600);
    $data    = array($cutoff, $obj->config["script_gatewaykey"]);
    $old     = $obj->device->getWhere("LastPoll < ? AND GatewayKey = ?", $data);
    $current = $obj->device->getWhere("LastPoll >= ? AND GatewayKey = ?", $data);
    $msg     = "The last poll was more than an hour ago!";
    if (count($current) == 0) {
        $obj->criticalError(
            "alarm.php:checkLastPoll",
            HUGNET_ERROR_OLD_POLL,
            $msg
        );
        $obj->debug($msg."\n", 3);
    }
}

$this->registerFunction("checkLastPoll", "hourly", "Last Poll");


/**
 * Checks to see when the last poll was in the database.
 *
 * @param object &$obj The alarm object to play with
 *
 * @return none
 */
function checkLastConfig(&$obj)
{
    $cutoff  = date("Y-m-d H:i:s", time() - 3600*48);
    $data    = array($cutoff, $obj->config["script_gatewaykey"]);
    $old     = $obj->device->getWhere("LastConfig < ? AND GatewayKey = ?", $data);
    $current = $obj->device->getWhere("LastConfig >= ? AND GatewayKey = ?", $data);
    $msg     = "All devices on this controller are more than two days old!";

    if (count($current) == 0) {
        $obj->criticalError(
            "alarm.php:checkLastConfig",
            HUGNET_ERROR_OLD_CONFIG,
            $msg
        );
        $obj->debug($msg."\n", 3);
    }
}

$this->registerFunction("checkLastConfig", "hourly", "Last Config");


?>