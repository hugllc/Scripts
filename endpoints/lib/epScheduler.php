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
class epScheduler
{
    /** String This specifies what the plugin directory is. */
    protected $pluginDir = "pluginDir";

    /** int The number of seconds to pause before trying again */
    protected $wait = 600;

    /** array This is the stuff to check...  */
    protected $check = array(
                            "H" => "hourly",
                            "d" => "daily",
                            "W" => "weekly",
                            "m" => "monthly",
                            "Y" => "yearly",
                            );                     
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
    
        $this->getPlugins($config[$this->pluginDir]);

    }
    /**
     * This is the main routine that should be called by the script
     *
     * @return int The error code, if any
     */
    function main()
    {
        do {
            if ($this->config["loop"]) $this->sleep();
            $this->clearErrors();
            $this->check();
            $this->errorHandler();
        } while ($this->config["loop"]);
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
        if ($this->verbose) print "Waiting ".$this->wait." Seconds\n";
        sleep($this->wait);
    }
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function clearErrors()
    {
        $this->error = array();
    }
    /**
    * Handles the errors that might happen
    *
    * @return null
    */
    function errorHandler()
    {
        if (!empty($this->error["critical"])) $this->sendErrorEmail();
        $this->error = array();
        return;            
    }
    
     /**
    * Handles the errors that might happen
    *
    * @return null
     */
    function sendErrorEmail()
    {
        if (empty($this->config["admin_email"])) return;
        $errors = array("critical" => "Critical Errors", "error" => "Errors", "warning" => "Warnings");
        $msg = "";
       
        foreach ($errors as $name => $text) {
            if (count($this->error[$name]) > 0) {
                $msg .= "\n".$text.":\n";
                foreach ($this->error[$name] as $code => $err) {
                    $msg .= $err["Date"]." => ".$code."\n\t".$err["Message"]."\n";
                }
            }
        }        
        mail($this->config["admin_email"], "Critical Error on ".`hostname`, $msg);
        return;
    }
   
     /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
     */
    function dailyReport()
    {
        
    }      
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function check()
    {
        if (!is_array($this->check)) return;
        foreach ($this->check as $key => $name) {            
            if (date($key) != $this->last[$key]) {
                $this->plugins->runFilter(&$this, $name);
                $this->last[$key] = date($key);
            }
        }
    }
       
    /**
     * Registers plugins
     *
     * @return null
     */
    function getPlugins ()
    {
        $this->plugins = new Plugins($this->config[$this->pluginDir], "inc.php", dirname(__FILE__)."/plugins", null, $this->verbose);
        if (!$this->verbose) return;
        if (is_array($this->plugins->plugins["Functions"])) {
            foreach ($this->plugins->plugins["Functions"] as $plugName => $plugDir) {
                if (array_search($plugName, $this->check) === false) print "Plugin type ".$plugName." is not a supported type!\n";
                foreach ($plugDir as $plug) {
                    print "Found $plugName Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
                }
            }
        }
    }
    
    /**
     * This function creates a critical alarm
     *
     * @param string $type   The type of error
     * @param string $plugin The name of the plugin that created the alarm
     * @param string $err    The error (short description)
     * @param string $errMsg The long error message
     *
     * @return null
     */
    function setError($type, $plugin, $err, $errMsg)
    {
        $this->error[$type][$err] = array("Message" => $errMsg, "Date" => date("Y-m-d H:i:s"), "Plugin" => $plugin);
        $this->daily[$type][$err]["Message"] = $errMsg;
        $this->daily[$type][$err]["Date"][] = date("Y-m-d H:i:s");
        $this->daily[$type][$err]["Plugin"] = $plugin;
    }

   /**
    * This function creates a critical error
    *
    * @param string $plugin The name of the plugin that created the alarm
    * @param string $err    The error (short description)
    * @param string $errMsg The long error message
    *
    * @return null
    */
    function criticalError($plugin, $err, $errMsg)
    {
        $this->setError("critical", $plugin, $err, $errMsg);
    }
   
    /**
    * This function creates a warning
    *
    * @param string $plugin The name of the plugin that created the alarm
    * @param string $err    The error (short description)
    * @param string $errMsg The long error message
    *
    * @return null
    */
    function warning($plugin, $err, $errMsg)
    {
        $this->setError("warning", $plugin, $err, $errMsg);
    }
   
   /**
    * This function creates an error
    *
    * @param string $plugin The name of the plugin that created the alarm
    * @param string $err    The error (short description)
    * @param string $errMsg The long error message
    *
    * @return null
   */
    function error($plugin, $err, $errMsg)
    {
        $this->setError("error", $plugin, $err, $errMsg);
    }

}
?>