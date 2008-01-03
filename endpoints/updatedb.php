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
 * @category   Scripts
 * @package    Scripts
 * @subpackage UpdateDB
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
    define("UPDATEDB_VERSION", "0.2.5");
    define("UPDATEDB_PARTNUMBER", "0039260250");  //0039-26-01-P
    define("UPDATEDB_SVN", '$Id$');


    print '$Id$'."\n";
    print 'updatedb.php Version '.UPDATEDB_VERSION."\n";
    print "Starting...\n";
    
    require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/database/process.php');
    require_once("epUpdatedb.php");

//    if ($testMode) $endpoint->db->debug = true;

//    $mhistory = new history_raw($db, $conf['hugnetDb']);

//    $lplog = new plog();


    $refreshdev = true;

    $updatedb = new epUpdatedb($endpoint, $verbose);
    $updatedb->uproc->register();
    
    while(1) {
        if (!$endpoint->device->IsConnected()) {
            print "Trying to reconnect to the database ";
            $endpoint->db = DbBase::createPDO($serv["dsn"], $serv["User"], $serv["Password"]);
            if ($endpoint->db === false) {
                $updatedb->updatedbError($emptyVar, "Failed", "dbReconnectFail");
                print " - Sleeping";
                sleep(60);
            } else {
                $updatedb->updatedbError($emptyVar, "Succeeded", "dbReconnect");
            }
            print "\n";
        }

        $updatedb->getAllDevices();

        if ($updatedb->verbose) print "[".$updatedb->uproc->me["PID"]."] Starting database update...\n";
//        $updatedb->uproc->FastCheckin();

        // This section does the packetlog
        $updatedb->updatedb();
        $updatedb->getPacketSend();

        //        $lplog->reset();
        $updatedb->wait();

        // Check the PHP log to make sure it isn't too big.
        clearstatcache();
        if (file_exists("/var/log/php.log")) {
            if (filesize("/var/log/php.log") > (1024*1024)) {
                $fd = fopen("/var/log/php.log","w");
                @fclose($fd);
            }
        }
    }
    $updatedb->uproc->unregister();

    print "[".$this->uproc->me["PID"]."] Finished\n";

/**
 * @endcond
 */
?>
