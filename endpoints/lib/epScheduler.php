<?php
/**
 * Base class for scheduled tasks
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
 * @subpackage Endpoints
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2008-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
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
 * @copyright  2008-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
class EpScheduler
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
    /** array This is the stuff to check...  */
    protected $otherCheck = array(
        "r" => "dailyReport",
    );
    /**
     * The constructor
     *
     * @param array $config The configuration array
     */
    function __construct($config = array())
    {

        $this->config = $config;

        unset($config["servers"]);

        $this->uproc =& HUGnetDB::getInstance("Process", $config);
        $this->uproc->createTable();

        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config);
        $this->stats->createTable();
        $this->stats->clearStats();
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);


        $this->test    = $config["test"];
        $this->verbose = $config["verbose"];

        unset($config["table"]);
        $this->plog = & HUGnetDB::getInstance("Plog", $config);
        $this->plog->createTable();

        unset($config["table"]);
        $this->device =& HUGnetDB::getInstance("Device", $config);

        $this->error =& HUGnetDB::getInstance("Error", $config);
        $this->error->createTable();

        $this->getPlugins($config[$this->pluginDir]);

        $this->id = rand(0, 0xFFFFFFFF);

    }
    /**
     * This is the main routine that should be called by the script
     *
     * @return int The error code, if any
     */
    function main()
    {
        $this->uproc->register();
        $this->clearErrors();
        if (empty($this->config["do"])) {
            do {
                if ($this->config["loop"]) {
                    $this->sleep();
                }
                $this->check();
                $this->errorHandler();
                $this->wait();
            } while ($this->config["loop"]);
        } else {
            $do = trim($this->config["do"]);
            if (isset($this->check[$do])) {
                print "Doing ".$this->check[$do]." run\n";
                $this->plugins->runFilter(&$this, $this->check[$do]);
                print "Done\n";
            } if (isset($this->otherCheck[$do])) {
                $method = $this->otherCheck[$do];
                if (method_exists($this, $method)) {
                    print "Doing ".$this->otherCheck[$do]." run\n";
                    $this->$method();
                    print "Done\n";
                }
            }

        }
        $this->uproc->unregister();
        return 0;
    }
    /**
    * Waits a random amount of time.
    *
    * @return null
    */
    function wait()
    {
        $sleep = mt_rand(1, 120);
        sleep($sleep);
    }

    /**
    * sleeps
    *
    * @return null
    */
    function sleep()
    {
        if ($this->test) {
            return;
        }
        if ($this->verbose) {
            print "Waiting ".$this->wait." Seconds\n";
        }
        sleep($this->wait);
    }
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function clearErrors()
    {
        $this->error->removeWhere("id = ?", array($this->id));
    }

    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function markErrorsSent()
    {
        $this->markErrors("SENT");
    }
    /**
    * This marks error as different from "NEW"
    *
    * @param string $mark What to mark the errors with
    *
    * @return int The error code, if any
    */
    function markErrors($mark="OLD")
    {
        $this->error->updateWhere(array("status" => $mark),
                                  "id = ?", array($this->id));
    }

    /**
    * Handles the errors that might happen
    *
    * @return null
    */
    function errorHandler()
    {
        $crit = $this->getError("critical", "NEW");

        if (count($crit) > 0) {
            $this->sendErrorEmail();
            $this->markErrorsSent();
        }
        return;
    }

     /**
    * Handles the errors that might happen
    *
    * @return null
     */
    function sendErrorEmail()
    {
        if (empty($this->config["admin_email"])) {
            return;
        }
        $errors = $this->getError();
        $msg    = "";

        foreach ($errors as $err) {
            $msg .= $err["errorLastSeen"]." => ".$err["err"];
            $msg .= " First seen ".$err["errorDate"]." Seen ";
            $msg .= $err["errorCount"]." times\n\t".$err["msg"]."\n";
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
        if (empty($this->config["admin_email"])) {
            return;
        }
        $this->plugins->runFilter(&$this, "dailyReport");
        if (!$this->config["test"]) {
            mail(
                $this->config["admin_email"],
                "Daily Report on ".`hostname`,
                $this->_dailyReport
            );
        } else {
            print "Test Mode.  Email not sent.\n";
            print "Email text as follows: \n\n";
            print $this->_dailyReport;
            print "\n\n";
        }
        return;
    }

    /**
    * This checks the hourly scripts
    *
    * @param string $text The text to add to the dailyReport
    * @param string $title The title of the area of the dailyReport
    *
    * @return int The error code, if any
    */
    function dailyReportOutput($text, $title = "")
    {
        $this->_dailyReport .= $text;
    }
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    function check()
    {
        if (!is_array($this->check)) {
            return;
        }
        foreach ($this->check as $key => $name) {
            if (date($key) != $this->last[$key]) {
                print "Doing ".$key." ".date("Y-m-d H:i:s")."s\n";
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
        $this->plugins = new Plugins(
            $this->config[$this->pluginDir],
            "inc.php",
            dirname(__FILE__)."/plugins",
            array(),
            $this->config["verbose"]
        );
        if (!$this->config["verbose"]) {
            return;
        }
        if (is_array($this->plugins->plugins["Functions"])) {
            foreach ($this->plugins->plugins["Functions"] as $plugName => $plugDir) {
                if (array_search($plugName, $this->check) === false) {
                    if (array_search($plugName, $this->otherCheck) === false) {
                        print "Plugin type ".$plugName." is not a supported type!\n";
                    }
                }
                foreach ($plugDir as $plug) {
                    print "Found $plugName Plugin: ";
                    print $plug["Title"]." (".$plug["Name"].")\r\n";
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
        $info = array(
                       'id' => $this->id,
                       'err' => (int)$err,
                       'msg' => $errMsg,
                       'errorDate' => date("Y-m-d H:i:s"),
                       'program' => $plugin,
                       'type' => $type,
                     );
        $this->error->add($info);
    }

    /**
    * This function creates a critical alarm
    *
    * @param string $type    The type of error
    * @param string $status  The error status to get
    * @param string $err     The error (short description)
    * @param string $program The program the error was from
    *
    * @return null
    */
    function getError($type=null, $status=null, $err=null, $program=null)
    {
        $where = array();
        $data  = array();
        if (!empty($type)) {
            $where[] = "type = ?";
            $data[]  = $type;
        }
        if (!empty($status)) {
            $where[] = "status = ?";
            $data[]  = $status;
        }
        if (!empty($err)) {
            $where[] = "err = ?";
            $data[]  = $err;
        }
        if (!empty($program)) {
            $where[] = "program = ?";
            $data[]  = $program;
        }
        $where[] = "id = ?";
        $data[]  = $this->id;
        $where   = implode(" AND ", $where);
        return $this->error->getWhere($where, $data);
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
    /**
    *  Saves debug information.
    *
    * @param string $text  Text to add to the stack
    * @param int    $level 0-5 How much to log to the stack.
    *
    * @return null
    */
    function debug($text, $level = 1)
    {
        $level = (int) $level;
        if ($this->verbose >= $level) {
            print $text;
        }
    }

}
?>