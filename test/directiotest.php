<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @subpackage Test
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
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
	$dfportal_no_session = TRUE;
	$extra_includes[] = "process.inc.php";
	$extra_includes[] = "directio.inc.php";
	include_once("blankhead.inc.php");

	$endpoint = new endpoint_direct();
	$endpoint->verbose = TRUE;
	$endpoint->connect();

	while(1) {
		$val = $endpoint->rawread();
		if ($val === FALSE) {
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
