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
 *   @subpackage Poll
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
 */

    define("POLL_VERSION", "0.2.10");
    define("POLL_PARTNUMBER", "0039260150");  //0039-26-01-P
    define("POLL_SVN", '$Id$');

	$GatewayKey = FALSE;
    $testMode = FALSE;

	require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.php');
    require_once('epPoll.php');

    print 'poll.php Version '.POLL_VERSION.'  $Id$'."\n";
	print "Starting...\n";

    define("CONTROLLER_CHECK", 10);

//	$proc = new process(NULL, "", "NORMAL", FALSE);
//	if ($proc->Register() === FALSE) {
//		die ("Already Running\r\n");	
//	}

	if (($GatewayKey == FALSE) | ($GatewayKey == 0)) die("You must supply a gateway key\n");
	
    $endpoint->packet->_getAll = TRUE;

//	$plog = new MDB_QueryWrapper($prefs['servers'], "HUGnetLocal", array('table' => "PacketLog", "dbWrite" => true));
    
	//Only try twice per server.
	$endpoint->socket->Retries = 2;
	$endpoint->socket->PacketTimeout = 6;


    $poll = new epPoll($endpoint, $testMode);
    $poll->uproc->register();

//    $poll->test = $testMode;
//    $poll->getGateways($GatewayKey);
    if (isset($GatewayIP)) {
        $gw = array(
            'GatewayIP' => $GatewayIP,
            'GatewayPort' => $GatewayPort,
            'GatewayName' => $GatewayIP,
            'GatewayKey' => $GatewayKey,
        );
/*       
        $TGatewayKey = $GatewayKey;
        $query = "SELECT * FROM gateways ".
                 "WHERE GatewayKey=".$gw['MasterGatewayKey'];
        $tgw = $endpoint->db->getArray($query);
                if ($tgw[0]["BackupKey"] != 0) {
                        $gw['MasterGatewayKey'] = $tgw[0]["BackupKey"];
                }
        } while ($tgw[0]["BackupKey"] != 0);
*/
        $poll->forceGateways($gw);
		print "Using Gateway ".$gw["GatewayIP"].":".$gw["GatewayPort"]."\n";
    } else {
        die("Gateway key must be supplied (-g)\r\n");
    }
    $poll->powerup();
    $poll->packet->packetSetCallBack('checkPacket', $poll);

	while (1) {

        print "Using: ".$endpoint->packet->SN." Priority: ".$poll->Priority."\r\n";
        $poll->checkOtherGW();
        $poll->getAllDevices();
        $poll->controllerCheck();
        $poll->poll();	
        
        $poll->wait();
	}
    $poll->uproc->unregister();

	print "Finished\n";
/**
 *	@endcond
 */


?>
