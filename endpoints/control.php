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
 * @subpackage Control
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

define("CONTROL_PARTNUMBER", "0039-26-05-P");  //0039-26-01-P
define("CONTROL_SVN", '$Id$');

$GatewayKey = false;
$testMode = false;

require_once(dirname(__FILE__).'/../head.inc.php');
require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');
require_once(HUGNET_INCLUDE_PATH.'/process.inc.php');

print 'control.php Version '.CONTROL_SVN."\n";
print "Starting...\n";

while (1) {

}
?>
