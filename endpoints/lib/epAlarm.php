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
class epAlarm
{
    /**
     *
     */    
    function __construct($config = array()) 
    {
        
        unset($config["servers"]);
        unset($config["table"]);
        $config["partNum"] = CONFIG_PARTNUMBER;
        $this->config = $config;

        $this->uproc =& HUGnetDB::getInstance("Process", $config); 
        $this->uproc->createTable();

        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config); 
        $this->stats->createTable();
        $this->stats->clearStats();        
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test = $config["test"];
        $this->verbose = $config["verbose"];

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config); 
        $this->plog->createTable();

        unset($config["table"]);
        $this->device =& HUGnetDB::getInstance("Device", $config);
    
        $this->getPlugins($config["alarmPluginDir"]);

    }
    /**
     * This is the main routine that should be called by the script
     *
     * @return int The error code, if any
     */
    function main()
    {
        $this->checkHourly();
        $this->sleep();      
    
        return 0;
    }
    
    /**
    * sleeps
    *
    * @return null
    */
    function sleep()
    {
        if ($this->test) return;      
        $wait = 600;
        if ($this->verbose) print "Waiting $wait Seconds";            
        sleep($wait);
    }

    
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function checkHourly()
    {
        static $lastHour;
        if (date("H") != $lastHour) {
            if ($this->verbose) print "Running Hourly Scrips:\n";         
            $this->plugins->runFilter(&$this, "hourly");
        }         
    }
    
    /**
     * Registers plugins
     *
     * @return null
     */
    function getPlugins ()
    {
        $this->plugins = new Plugins($this->config["alarmPluginDir"], "inc.php", dirname(__FILE__)."/plugins", null, $this->verbose);
        if ($this->verbose) {
            if (is_array($this->plugins->plugins["Functions"])) {
                foreach ($this->plugins->plugins["Functions"] as $plugName => $plugDir) {
                    foreach ($plugDir as $plug) {
                        print "Found $plugName Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
                    }
                }
            }
        }
    }       
}
?>