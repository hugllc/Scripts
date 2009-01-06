<?php
/**
 * Saves firmware to the database
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
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */

require_once dirname(__FILE__).'/../head.inc.php';
print "Starting...\n";

if (empty($argv[1]) || !file_exists($argv[2]) || !file_exists($argv[3])) {
    print "Usage: ".$argv[0]." <version> <Code File>";
    print "<Data File> <file Type> <firmwarePart> <hardwarePart>";
    print "<status> <cvstag></cvstag>\r\n";
    die();
}

$firmware = new firmware($prefs['servers'],
                         HUGNET_DATABASE,
                         array("dbWrite" => true));

$Info["FirmwareVersion"]  = $argv[1];
$Info["FirmwareCode"]     = implode("", file($argv[2]));
$Info["FirmwareData"]     = implode("", file($argv[3]));
$Info["FWPartNum"]        = $argv[5];
$Info["HWPartNum"]        = $argv[6];
$Info["Date"]             = date("Y-m-d H:i:s");
$Info["FirmwareFileType"] = $argv[4];
$Info["FirmwareStatus"]   = $argv[7];
$Info["FirmwareCVSTag"]   = $argv[8];
$Info["Target"]           = $argv[9];

$return = $endpoint->db->AutoExecute('firmware', $Info, 'INSERT');
if ($return) {
    print "Successfully Added\r\n";
    return(0);
} else {
    print "Error (".$firmware->Errno."):  ".$firmware->Error."\r\n";
    return($firmware->Errno);
}
?>
