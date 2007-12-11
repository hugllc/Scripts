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
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Scripts
 * @subpackage Test
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */
    require_once(dirname(__FILE__).'/../../head.inc.php');
        $endpoint->packet->SNCheck(false);

    
    if (empty($DeviceID)) {
        die("DeviceID must be specified!\r\n");    
    }

    $dev = $endpoint->getDevice($DeviceID, "ID");

    $dev["GatewayIP"] = $GatewayIP;
    $dev["GatewayPort"] = $GatewayPort;

    $endpoint->packet->verbose = $verbose;
    $pkt = $endpoint->ReadSensors($dev);

//    $config = $endpoint->InterpConfig($pkt);
    var_dump($pkt);

    die();
/**
 * @endcond
*/

?>
