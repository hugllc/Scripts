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
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: averages.dfp.php 873 2008-02-06 20:16:29Z prices $    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/**
 * Checks to see when the last sensor read was.  It it was mroe than an hour ago it creates an alarm.
 *
 * @param object $obj The alarm object to play with
 */
function checkLastSensorRead(&$obj)
{
    $last = $obj->stats->getWhere("sname = 'LastSENSORREAD'");
    if (((int)$last[0]["svalue"]) < (time() - 1800)) $obj->criticalError("checkLastSensorRead", "Last sensor read too old", "The last sensor read happened more than 30 minutes ago at ".date("Y-m-d H:i:s", $last[0]["svalue"])."!");
}

$this->registerFunction("checkLastSensorRead", "hourly", "Last Sensor Read");


/**
 * Checks to see when the last poll was in the database.
 *
 * @param object $obj The alarm object to play with
 */
function checkLastPoll(&$obj)
{
    $cutoff = date("Y-m-d H:i:s", time() - 3600);
    $old = $obj->device->getWhere("LastPoll < ? AND GatewayKey = ?", array($cutoff, $obj->config["script_gatewaykey"]));
    $current = $obj->device->getWhere("LastPoll >= ? AND GatewayKey = ?", array($cutoff, $obj->config["script_gatewaykey"]));

    if (count($current) == 0) $obj->criticalError("checkLastPoll", "No devices with current config", "All devices on this controller are more than an hour old!");
    
}

$this->registerFunction("checkLastPoll", "hourly", "Last Poll");


/**
 * Checks to see when the last poll was in the database.
 *
 * @param object $obj The alarm object to play with
 */
function checkLastConfig(&$obj)
{
    $cutoff = date("Y-m-d H:i:s", time() - 3600*48);   
    $old = $obj->device->getWhere("LastConfig < ? AND GatewayKey = ?", array($cutoff, $obj->config["script_gatewaykey"]));
    $current = $obj->device->getWhere("LastConfig >= ? AND GatewayKey = ?", array($cutoff, $obj->config["script_gatewaykey"]));

    if (count($current) == 0) $obj->criticalError("checkLastConfig", "No devices with current config", "All devices on this controller are more than two days old!");
    
}

$this->registerFunction("checkLastConfig", "hourly", "Last Config");


?>
