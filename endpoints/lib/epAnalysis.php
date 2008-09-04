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
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2008 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id: endpoint.php 1445 2008-06-17 22:25:17Z prices $    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
 
require_once "epScheduler.php";
/**
 * Class for checking on things to make sure they are working correctly
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2008 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */ 
class epAnalysis extends epScheduler
{
    /** String This specifies what the plugin directory is. */
    protected $pluginDir = "analysisPluginDir";
    /** int The number of seconds to pause before trying again */
    protected $wait = 600;

    /** array This is the stuff to check...  */
    protected $check = array(
                             "H_1" => "hourlyPre",
                             "H" => "hourly",
                             "H_3" => "hourlyPost",
                             "d_1" => "dailyPre",
                             "d" => "daily",
                             "d_3" => "dailyPost",
                             "W_1" => "weeklyPre",
                             "W" => "weekly",
                             "W_3" => "weeklyPost",
                             "m_1" => "monthlyPre",
                             "m" => "monthly",
                             "m_3" => "monthlyPost",
                             "Y_1" => "yearlyPre",
                             "Y" => "yearly",
                             "Y_3" => "yearlyPost",
                            );
    /**
     *
     */    
    function __construct($config = array()) 
    {
        parent::__construct($config);      
    }
}
?>