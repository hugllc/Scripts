<?php
/**
 *   This runs all of the tests associated with HUGnetLib.
 *
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
 *   @package HUGnetLib
 *   @subpackage Test
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id: HUGnetLibTests.php 445 2007-11-13 16:53:06Z prices $    
 *
 */

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'ScriptTests::main');
}

require_once "hugnet.inc.php";

require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once 'endpoints/ScriptEndpointTests.php';

/**
 *  This class runs all of the tests.  This must be done with no errors
 * before the software is ever released.
 */
class ScriptTests
{
    public static function main()
    {
        PHPUnit_Util_Filter::addDirectoryToFilter('HUGnetLib/', '.php');
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
 
    public static function suite()
    {
        PHPUnit_Util_Filter::addDirectoryToFilter('adodb/', '.php');
        PHPUnit_Util_Filter::addDirectoryToFilter('Scripts/test/', '.php');
        $suite = new PHPUnit_Framework_TestSuite('Scripts');

        //$suite->addTestSuite('otherTest');
        
    	$suite->addTest(ScriptEndpointTests::suite());
 
        return $suite;
    }
}
 
if (PHPUnit_MAIN_METHOD == 'ScriptTests::main') {
    ScriptTests::main();
}
?>
