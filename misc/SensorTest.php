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
require_once dirname(__FILE__).'/../../HUGnetLib/src/cli/Daemon.php';
require_once dirname(__FILE__).'/../../HUGnetLib/src/cli/Args.php';

print "monitor.php\n";
print "Starting...\n";

$config = &\HUGnet\cli\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
        "C" => array("name" => "Command", "type" => "string", "args" => true),
    )
);
$conf = $config->config();
$conf["network"]["channels"] = 1;
$cli = &\HUGnet\cli\Daemon::factory($conf);

$devices = array(0x67, 0x68, 0xFE);

$devInfo = array();
$reply = 0;
$sent = 0;
$packets = array();
$counts = array();
foreach ($devices as $dev) {
    $ret = $cli->system()->network()->send(
        array(
            "To" => $dev,
            "Command" => "5C",
        ),
        null,
        array(
            "block" => 1
        )
    );
    if (!is_object($ret) || strlen($ret->Reply()) == 0) {
        die("Could not contact device ".sprintf("%06X", $dev));
    }
    $devInfo[$dev] = new DeviceContainer($ret->Reply());
}
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
                    if (!is_object($pkt) || strlen($pkt->Reply()) == 0) {
                        return;
                    }
                    global $packets;
                    global $prev, $devInfo;
                    $dev = hexdec($pkt->To());
                    $data = $devInfo[$dev]->decodeData(
                        $pkt->Reply(),
                        $pkt->Command(),
                        0,
                        (array)$prev[$dev]
                    );
                    $d = $devInfo[$dev]->historyFactory($data);
                    if ($data["DataIndex"] !== $prev[$dev]["DataIndex"]) {
                        printf("%06X ", $dev);
                        print date("Y-m-d H:i:s ").$data["DataIndex"]." ";
                        for ($i = 0; $i < 9; $i++) {
                            $var = "Data".$i;
                            printf("%f ", $d->$var);
                        }
                        print "\n";
                        $prev[$dev] = $data;
                    }
                },
                array(
                    //"find" => false, "tries" => 1
                )
            );
            if ($ret) {
                $packets[$dev] = time();
            }
        } else if ((time() - $packets[$dev]) > 10) {
            unset($packets[$dev]);
        }
    }
    $cli->system()->main();
}
print "Finished\n";


?>
