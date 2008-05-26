#!/usr/bin/php-cli
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
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: poll.php 1361 2008-05-25 00:53:23Z prices $    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

define("ENDPOINT_PARTNUMBER", "0039-26-04-P");  //0039-26-01-P
define("ENDPOINT_SVN", '$Id: poll.php 1361 2008-05-25 00:53:23Z prices $');

$GatewayKey = false;
$testMode = false;

$database_driver = "sqlite";

require_once(dirname(__FILE__).'/../head.inc.php');
require_once(HUGNET_INCLUDE_PATH.'/database/Plog.php');
require_once(HUGNET_INCLUDE_PATH.'/database/Process.php');
require_once('lib/endpoint.php');

if (!(bool)$hugnet_config["poll_enable"]) {
    print "Poll disabled... Sleeping\n";
    sleep(60);
    die();
}

print 'endpoint.php Version '.ENDPOINT_SVN."\n";
print "Starting...\n";

define("CONTROLLER_CHECK", 10);

if (empty($GatewayKey)) die("You must supply a gateway key\n");
print "Using GatewayKey ".$GatewayKey."\n";

$hugnet_config["gateway"] = array(
    'GatewayIP' => $GatewayIP,
    'GatewayPort' => $GatewayPort,
    'GatewayName' => $GatewayIP,
    'GatewayKey' => $GatewayKey,
);
// Make sure we only go with the sqlite driver.
$hugnet_config["driver"] = "sqlite";

print "Using Gateway ".$hugnet_config["gateway"]["GatewayIP"].":".$hugnet_config["gateway"]["GatewayPort"]."\n";

$ep = new endpoint($hugnet_config);
$ep->uproc->register();

$ep->main();

$ep->uproc->unregister();

print "Finished\n";


?>
