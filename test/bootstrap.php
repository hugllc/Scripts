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

define("SCRIPTS_CODE_BASE", realpath(dirname(__FILE__)."/../src")."/");
define("CODE_BASE", realpath(dirname(__FILE__)."/HUGnetLib/src")."/");
define("TEST_BASE", realpath(dirname(__FILE__)."/suite/")."/");
define("HUGNET_INCLUDE_PATH", realpath(dirname(__FILE__)."/HUGnetLib/src")."/");
define(
    "HUGNETLIB_STUB_PATH", realpath(dirname(__FILE__)."/HUGnetLib/test/stubs")."/"
);
//include_once dirname(__FILE__)."/HUGnetLib/src/hugnet.inc.php";
require_once HUGNET_INCLUDE_PATH."/system/System.php";
require_once HUGNET_INCLUDE_PATH."/containers/ConfigContainer.php";


$path = ini_get("include_path");
ini_set("include_path", $path.":".dirname(__FILE__)."/HUGnetLib/src");

?>
