<?php
/**
 * Tries longer and longer packets until it doesn't get anymore replies.
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
require "packet.inc.php";

if (empty($argv[1])) {
    die("DeviceID must be specified!\r\n");    
}
$pkt              = array();
$Info["DeviceID"] = $argv[1];
$pkt["To"]        = $argv[1];
$pkt["Command"]   = "02";


if (isset($argv[2])) {
    $Info["GatewayIP"] = $argv[2];
} else {
    $Info["GatewayIP"] = "127.0.0.1";
}
if (isset($argv[3])) {
    $Info["GatewayPort"] = $argv[3];
} else {
    $Info["GatewayPort"] = 1200;
}
$Info["GatewayKey"] = 1;

$packet = new EPacket($Info, true);

$index = 0;
do {
    $pkt["Data"] = str_repeat("00", $index++);
    $return      = $packet->SendPacket($Info, $pkt);
} while ($return !== false);
$index--;
print "Died at ".$index." Length\r\n";
$packet->socket->Close();
die();
    
?>
