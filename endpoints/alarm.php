#!/usr/bin/php-cli
<?php
/**
 * This runs the alarm functions on the core
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
 * @subpackage Alarm
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
 
define("ALARM_PARTNUMBER", "0039-26-03-P");  //0039-26-01-P

require_once dirname(__FILE__).'/../head.inc.php';
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
require_once HUGNET_INCLUDE_PATH.'/database/Process.php';
require_once 'lib/epAlarm.php';

if (!(bool)$hugnet_config["alarm_enable"]) {
    print "Alarm disabled... Sleeping\n";
    sleep(60);
    die();
}

print "Starting...\n";



if (empty($hugnet_config["pluginDir"])) {
    $hugnet_config["alarmPluginDir"] = dirname(__FILE__)."/alarm/";
}   
$hugnet_config["partNum"] = ALARM_PARTNUMBER;

$epAlarm = new epAlarm($hugnet_config);

$epAlarm->main();

print "Exiting...\n";

?>
