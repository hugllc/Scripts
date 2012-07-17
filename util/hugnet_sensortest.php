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
require_once 'HUGnetLib/ui/Daemon.php';
require_once 'HUGnetLib/ui/Args.php';
require_once 'HUGnetLib/containers/DeviceContainer.php';


$config = &\HUGnet\ui\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
        "C" => array("name" => "Command", "type" => "string", "args" => true),
    )
);
$config->addLocation("/usr/share/HUGnet/config.ini");
$cli = &\HUGnet\ui\Daemon::factory($config);

//var_dump($config->config());

$cli->help(
    $cli->system()->get("program")."
Copyright Hunt Utilities Group, LLC

Routes HUGnet packets between interfaces

Usage: ".$cli->system()->get("program")." [-v] [-i <DeviceID>] [-f <file>]
Arguments:
    -i <DeviceID>   The device ID to use.  Should be a hex value up to 6 digits
    -v              Increment the verbosity
    -f <file>       The config file to use",
    $config->h
);
$cli->requireUUID();
$cli->requireINI();
$cli->out("Starting ".$cli->system()->get("program"));

$dev = hexdec($config->DeviceID);

if ($dev == 0) {
    $cli->help();
    $cli->out();
    $cli->out(
        "A DeviceID must be specified."
    );
    exit(1);
}

$device = $cli->system()->device(hexdec($config->DeviceID));
$pkt = $device->network()->poll(
    null,
    array(
        //"find" => false, "tries" => 1
    )
);
if (is_object($pkt) && strlen($pkt->Reply()) > 0) {
    $data = $device->decodeData(
        $pkt->Reply(),
        $pkt->Command(),
        0,
        (array)$prev[$dev]
    );
    print "Date: ".date("Y-m-d H:i:s ")."\n";
    print "DataIndex: ".$data["DataIndex"]."\n";
    for ($i = 0; $i < 9; $i++) {
        $var = "Data".$i;
        $val = $data[$i]['value'];
        print "Data $i: ";
        if (is_null($val)) {
            print "null \n";
        } else {
            printf("%f %s\n", $val, html_entity_decode($data[$i]['units']));
        }
    }
    print "\n";
}
print "Finished\n";


?>
