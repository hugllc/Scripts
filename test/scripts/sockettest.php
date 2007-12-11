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
    $dfportal_no_session = true;
    $extra_includes[] = "process.inc.php";
    $extra_includes[] = "packet.inc.php";
    $extra_includes[] = "socket.inc.php";
    include_once("blankhead.inc.php");

    $server = $argv[1];

    $endpoint->device->lookup("0000AC", "DeviceID");
    $dev = $endpoint->device->lookup[0];

    $packet = new EPacket(true, $dev["GatewayIP"]);

    $packet->socket->SetPort(0);
    print ($packet->Ping($dev));
    $packet->socket->Close();
    die();
    
    $string = "";
    $buffers = array();
    while(1) {
        $packet->socket->PeriodicCheck();
        $string = $packet->socket->Read();
        if (stristr(trim($string), ":") !== false) {
            $pair = explode(":", trim($string));
            if (is_numeric($pair[0])) {
                $index = (int) $pair[0];
                $buffers[$index] .= strtoupper($pair[1]);
                $pkt = stristr($buffers[$index], "5A5A");
                if (strlen($pkt) > 11) {
                    while (substr($pkt, 0, 2) == "5A") $pkt = substr($pkt, 2);
                    $len = hexdec(substr($pkt, 14, 2));
                    if (strlen($pkt) >= ((9 + $len)*2)) {
                        $pkt = substr($pkt, 0, (9+$len)*2);

                        $chksum = 0;
                        for ($i = 0; $i < ((9+$len)*2); $i+=2) {
                            $chksum ^= hexdec(substr($pkt, $i, 2));
                        }
                        print $pkt." - ".$chksum."\r\n";
                        $buffers[$index] = "";
        
                    }
                }
            } else {
                if (trim($string) != "") {
                    print "Got: ".$string."\r\n";
                }
            }
        } else {
            if (trim($string) != "") {
                print "Got: ".$string."\r\n";
            }
        }
    }

    $packet->socket->Close();
/**
 * @endcond
*/
?>
