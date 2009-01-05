<?php
/**
 * Checks a sesor driver to see if it is functioning properly 
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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
$required  = array(
    "longName" => "Long Name",
    "unitType" => "Unit Type",
    "storageUnit" => "Storage Unit",
);
$suggested = array(
    "function" => "Decoding Function",
    "checkFunction" => "Record checking function",
);
$other     = array(
    "mult" => "Multiplier",
    "doTotal" => "Total",
    "extraText" => "Extra Text",
    "extraDefault" => "Extra Default",
);

require_once dirname(__FILE__).'/../head.inc.php';

if (is_array($endpoint->sensors->sensors)) {
    foreach ($endpoint->sensors->sensors as &$class) {
        print "Working with class: ".get_class($class)."\n";
        if (is_array($class->sensors)) {
            foreach ($class->sensors as $type => $sArray) {
                print "-> 0x".dechex($type)."\n";
                if (is_array($sArray)) {
                    foreach ($sArray as $shortName => $s) {
                        print "--> Short Name: '".$shortName."'\n";
                        foreach ($required as $key => $name) {
                            if (isset($s[$key])) {
                                print "---> ".$name.": '".$s[$key]."'\n";
                                unset($s[$key]);
                            } else {
                                die("** ERROR ** ".$key." is not defined!\n");
                            }
                        }
                        foreach ($suggested as $key => $name) {
                            if (isset($s[$key])) {
                                print "---> ".$name.": '".$s[$key]."'\n";
                                unset($s[$key]);
                            } else {
                                print "** WARNING ** ".$key." is not defined!\n";
                            }
                        }
                        foreach ($other as $key => $name) {
                            if (isset($s[$key])) {
                                print "---> ".$name.": '".$s[$key]."'\n";
                                unset($s[$key]);
                            } else {
                                print "---> ".$key;
                                print " is not defined. (This is okay)\n";
                            }
                        }
                    }
                }
            }
        }
    }
} else {
    print "No sensors defined. \n";
}

?>
