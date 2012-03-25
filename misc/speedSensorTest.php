#!/usr/bin/env php
<?php
/**
 * Monitors incoming packets
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
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
/** HUGnet code */
//require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGnetLib/ui/Daemon.php';
require_once HUGnetLib/ui/Args.php';

print "monitor.php\n";
print "Starting...\n";

$config = &\HUGnet\ui\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
        "C" => array("name" => "Command", "type" => "string", "args" => true),
    )
);
$conf = $config->config();
$conf["network"]["channels"] = 4;
$cli = &\HUGnet\ui\Daemon::factory($conf);

$devices = array(0x67, 0xFE, 0x17C, 0x68, 0xFC, 0x16E, 0xAC);

$reply = 0;
$sent = 0;
$packets = array();
$counts = array();
$start = time();
while ($cli->loop()) {
    shuffle($devices);
    foreach ($devices as $dev) {
        if (!isset($packets[$dev])) {
            $ret = $cli->system()->network()->send(
                array(
                    "To" => $dev,
                    "Command" => "55",
                ),
                function ($pkt)
                {
                    global $reply;
                    global $packets;
                    global $counts;
                    if (is_object($pkt)) {
                        if ($pkt->Reply()) {
                            $reply++;
                            printf("Reply from %s\n", $pkt->To());
                            $counts[hexdec($pkt->To())]++;
                        } else {
                            printf("Timeout on %s\n", $pkt->To());
                        }
                        unset($packets[hexdec($pkt->To())]);
                    }
                },
                array(
                    "find" => false, "tries" => 1
                )
            );
            if ($ret) {
                printf("Sent to %06X\n", $dev);
                $packets[$dev] = time();
                $sent++;
            }
        }
    }/*
    foreach ($packets as $dev => $time) {
        if ((time() - $time) > 15) {
            printf("Timeout on %06X\n", $dev);
            unset($packets[$dev]);
        }
    }*/
    $cli->system()->main();
}
$spent = time() - $start;
print "Sent $sent, got $reply => ".(($reply/$sent)*100)."%\n";
print "Lost ".($sent - $reply)." packets\n";
print "in ".$spent."s\n";
print "$reply polls at ".($spent/$reply)." seconds/packet\n";
foreach ($counts as $dev => $hits) {
    printf ("%06X -> $hits polls\n", $dev);
}
print "Finished\n";


?>
