#!/usr/bin/php-cli
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
 * @subpackage UpdateDB
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
define("UPDATEDB_PARTNUMBER", "0039-26-02-P");  //0039-26-01-P
define("UPDATEDB_SVN", '$Id$');


require_once dirname(__FILE__).'/../head.inc.php';
print 'updatedb.php Version '.UPDATEDB_SVN."\n";
print "Starting...\n";

require_once HUGNET_INCLUDE_PATH.'/database/Process.php';
require_once "lib/epUpdatedb.php";

$hugnet_config['GatewayIP']   = $GatewayIP;
$hugnet_config['GatewayPort'] = $GatewayPort;
$hugnet_config['GatewayName'] = $GatewayIP;
$hugnet_config['GatewayKey']  = $GatewayKey;
$hugnet_config['socketType'] = "db";
$hugnet_config['socketTable'] = "PacketLog";

$refreshdev = true;

$updatedb = new epUpdatedb($hugnet_config);

$updatedb->main();

print "Finished\n";

?>
