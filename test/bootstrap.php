<?php
/**
 * This component is the user interface for the endpoints
 *
 * PHP Version 5
 *
 * <pre>
 * com_timeclock is a Joomla! 1.6 component
 * Copyright (C) 2008-2009, 2011 Hunt Utilities Group, LLC
 * Copyright 2009 Scott Price
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA  02110-1301, USA.
 * </pre>
 *
 * @category   Test
 * @package    HUGnetLibTest
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2011 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Comtimeclock
 */

define("CODE_BASE", realpath(dirname(__FILE__)."/..")."/");
define("TEST_BASE", realpath(dirname(__FILE__)."/suite/")."/");

PHP_CodeCoverage_Filter::getInstance()->addFileToBlacklist(__FILE__);
PHP_CodeCoverage_Filter::getInstance()->addFileToBlacklist(
    CODE_BASE."hugnet.inc.php"
);
PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(CODE_BASE."contrib");
PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(CODE_BASE."test");
PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(
    CODE_BASE."interfaces"
);
PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(HUGNET_INCLUDE_PATH);
if (file_exists("/home/hugnet/HUGnetLib/hugnet.inc.php")) {
    include_once "/home/hugnet/HUGnetLib/hugnet.inc.php";
} else {
    if (!@include_once$hugnet_config["HUGnetLib_dir"]."/hugnet.inc.php") {
        if (!@include_once "HUGnetLib/hugnet.inc.php") {
            if (!@include_once dirname(__FILE__)."/../../HUGnetLib/hugnet.inc.php") {
                include_once "hugnet.inc.php";
            }
        }
    }
}
require_once HUGNET_INCLUDE_PATH."/containers/ConfigContainer.php";

?>
