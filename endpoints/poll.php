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
 * @subpackage Poll
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */

    define("POLL_VERSION", "0.2.11");
    define("POLL_PARTNUMBER", "0039-26-01-50");  //0039-26-01-P
    define("POLL_SVN", '$Id$');

    $GatewayKey = false;
    $testMode = false;

    require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/database/plog.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.php');
    require_once('epPoll.php');

    print 'poll.php Version '.POLL_VERSION.'  $Id$'."\n";
    print "Starting...\n";

    define("CONTROLLER_CHECK", 10);

    if (empty($GatewayKey)) die("You must supply a gateway key\n");
    
    $gw = array(
        'GatewayIP' => $GatewayIP,
        'GatewayPort' => $GatewayPort,
        'GatewayName' => $GatewayIP,
        'GatewayKey' => $GatewayKey,
    );
    print "Using Gateway ".$gw["GatewayIP"].":".$gw["GatewayPort"]."\n";

    $poll = new epPoll($endpoint, $gw, $verbose, $testMode);
    $poll->uproc->register();

    $poll->main();
    
    $poll->uproc->unregister();

    print "Finished\n";
/**
 *
 */


?>
