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
$conf["network"]["block"] = true;
$cli = &\HUGnet\cli\CLI::factory($conf);
/*
$daemon->system()->network()->monitor(
    function (&$pkt)
    {
        if (is_object($pkt)) {
            print "From: ".$pkt->From();
            print " -> To: ".$pkt->To();
            print "  Command: ".$pkt->Command();
            print "  Type: ".$pkt->Type();
            print "\r\n";
            $data = $pkt->Data();
            if (!empty($data)) {
                print "Data: ".$data."\r\n";
            }
        }
    }
);*/
$pkt = $cli->system()->network()->send(
    array(
        "To" => $config->i,
        "Command" => $config->C,
        "Data" => $config->D,
    )
);
if (is_object($pkt)) {
    print "From: ".$pkt->From();
    print " -> To: ".$pkt->To();
    print "  Command: ".$pkt->Command();
    print "  Type: ".$pkt->Type();
    print "\r\n";
    $data = $pkt->Data();
    if (!empty($data)) {
        print "Data: ".$data."\r\n";
    }
    $data = $pkt->Reply();
    if (!empty($data)) {
        print "Reply Data: ".$data."\r\n";
    }
}
print "Finished\n";

?>
