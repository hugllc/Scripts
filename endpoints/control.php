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
 * @subpackage Control
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id: unitConversion.inc.php 369 2007-10-12 15:05:32Z prices $    
 *
 */

    define("CONTROL_VERSION", "0.0.1");
    define("CONTROL_PARTNUMBER", "0039260450");  //0039-26-01-P
    define("CONTROL_SVN", '$Id: poll.php 149 2007-08-01 17:15:59Z prices $');

    $GatewayKey = false;
    $testMode = false;

    require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.inc.php');

    print 'control.php Version '.CONTROL_VERSION.'  $Id: poll.php 149 2007-08-01 17:15:59Z prices $'."\n";
    print "Starting...\n";

    while (1) {
    
    }
?>