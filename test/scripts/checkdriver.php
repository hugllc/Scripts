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

    include_once("blankhead.inc.php");
    include_once("hugnetservers.inc.php");

    $mhistory = new history_raw();
    $mhistory->DefaultSortBy = "Date desc";

    $mhistory->lookup($argv[1], "DeviceKey");
    print "Found ".count($lhistory->lookup)." records\n";
    $packet=$mhistory->lookup[0];
    $endpoint->device->lookup($packet["DeviceKey"], "DeviceKey");

    $packet = array_merge($packet, $endpoint->device->lookup[0]);


    $packet = $endpoint->DecodeData($packet);

    print strip_tags(get_stuff($packet));
/**
 * @endcond    
*/