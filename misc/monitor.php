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
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
/** HUGnet code */
require_once(dirname(__FILE__).'/../head.inc.php');
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
define("MONITOR_SVN", '$Id$');

print 'monitor.php Version '.MONITOR_SVN."\n";
print "Starting...\n";

// Make sure we only go with the sqlite driver.
unset($hugnet_config["servers"]);

$plog = new plog($hugnet_config);
$last = "0000-00-00 00:00:00";
print "Waiting for packets\r\n";
while (1) {

    $now = date("Y-m-d H:i:s");
    $packets = $plog->getWhere("Date >= ? AND Date < ? AND Type <> 'OUTGOING'", array($last, $now));
    foreach ($packets as $pkt) {
        print "From: ".$pkt['PacketFrom'];
        print " -> To: ".$pkt['PacketTo'];
        print "  Command: ".$pkt['Command']."\r\n";
        if (!empty($pkt['RawData'])) print "Data: ".$pkt['RawData']."\r\n";
    }
    $last = $now;
    sleep(1);
}    

include_once("blanktail.inc.php");
print "Finished\n";

?>
