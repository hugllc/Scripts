<?php
/**
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

$fd = dio_open('/dev/cuaa0', O_RDWR | O_NOCTTY | O_NONBLOCK);

//dio_fcntl($fd, F_SETFL, O_SYNC);

dio_tcsetattr($fd, array(
  'baud' => 38400,
  'bits' => 8,
  'stop'  => 1,
  'parity' => 0
));

print "Starting...\r\n";
while (1) {

  $data = dio_read($fd, 256);

  if ($data) {
     echo $data;
  }
}


/*
    $dfportal_no_session = true;
    $extra_includes[] = "process.inc.php";
    $extra_includes[] = "directio.inc.php";
    include_once("blankhead.inc.php");

    $endpoint = new endpoint_direct();
    $endpoint->verbose = true;
    $endpoint->connect();

    while(1) {
        $val = $endpoint->rawread();
        if ($val === false) {
            print "Socket Not Open\r\n";
            break;
        } else {
            if (strlen($val) > 0) {
                print $val."\r\n";
            } else {
                usleep(10000);
            }
        }
    }

    $endpoint->close();
*/
/**
 * @endcond
*/
?>
