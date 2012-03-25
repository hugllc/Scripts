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

print $argv[0]."\n";
print "Starting...\n";

$config = &\HUGnet\ui\Args::factory($argv, $argc);
$daemon = &\HUGnet\ui\Daemon::factory($config);
$daemon->system()->network()->monitor(
    function ($pkt)
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
);

while ($daemon->loop()) {
    $daemon->main();
}
print "Finished\n";

?>
