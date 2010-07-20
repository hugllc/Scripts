<?php
/**
 * Classes for dealing with devices
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2010 Hunt Utilities Group, LLC
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
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */
/** Require the stuff we need */
require_once HUGNET_INCLUDE_PATH."/base/PeriodicPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/containers/DeviceContainer.php";
/**
 * Base class for all other classes
 *
 * This class uses the {@link http://www.php.net/pdo PDO} extension to php.
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DailyReportCheckPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DailyReportCheckPlugin",
        "Type" => "periodic",
        "Class" => "DailyReportCheckPlugin",
    );
    /** @var This is the array of devices in our gateway */
    protected $devs = null;
    /** @var This is the array of devices in our gateway */
    private $_to = null;
    /** @var This is the array of devices in our gateway */
    private $_subject = null;
    /** @var This says if we are enabled or not */
    protected $enabled = true;
    /**
    * This function sets up the driver object, and the database object.  The
    * database object is taken from the driver object.
    *
    * @param mixed           $config The configuration array
    * @param PeriodicPlugins &$obj   The controller object
    *
    * @return null
    */
    public function __construct($config, PeriodicPlugins &$obj)
    {
        parent::__construct($config, $obj);
        $this->enable = $this->control->myConfig->check["send_daily"];
        $this->_to = $this->control->myConfig->admin_email;
        $this->enable = $this->enable && !empty($this->_to);
        if (!$this->enable) {
            return;
        }
        $this->_subject = "Daily Report on ".`hostname`;
        $this->device = new DeviceContainer();
        $this->gatewayKey = $this->control->myConfig->script_gateway;
        // State we are here
        self::vprint(
            "Registed class ".self::$registerPlugin["Class"],
            HUGnetClass::VPRINT_NORMAL
        );
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @return bool True if ready to return, false otherwise
    */
    public function main()
    {
        // State we are looking for errors
        self::vprint(
            "Running the daily report",
            HUGnetClass::VPRINT_NORMAL
        );
        $this->_body = "";
        $this->devs = $this->device->select(
            "GatewayKey = ?",
            array($this->gatewayKey)
        );
        $this->last();
        $ret = $this->send();
        unset($this->devs);
        $this->last = time();
        return $ret;
    }

    /**
    * This function checks to see if it is ready to run again
    *
    * Run every 24 hours between midnight and 5
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        return (time() >= ($this->last + 42300)) && $this->enable
            && ((date("H") < 5) || $this->control->myConfig->test);
    }

    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    protected function send()
    {
        if ($this->control->myConfig->test) {
            $output = array(
                "To" => $this->_to,
                "Subject" => $this->_subject,
                "Body" => $this->_body,
            );
            return $output;
        }
        // @codeCoverageIgnoreStart
        // Can't test this.
        mail($this->_to, $this->_subject, $this->_body);
    }
    // @codeCoverageIgnoreEnd
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    protected function last()
    {
        $types = array(
            "LastPoll" => "Poll",
            "LastConfig" => "Config",
            "LastHistory" => "History",
            "LastAnalysis" => "Analysis",
        );
        $days = array("0.04", "0.25", "0.5", "1", "3", "10", "many");
        $stats = array();
        foreach (array_keys((array)$this->devs) as $key) {
            $row = &$this->devs[$key];
            if ($row->Active == 1) {
                foreach ($types as $key => $type) {
                    $date = $row->params->DriverInfo[$key];
                    $done = false;
                    foreach ($days as $d) {
                        if ($d == "many") {
                            $stats[$key][$d]++;
                            break;
                        }
                        $time = time() - (int)((float)$d * 86400);
                        if ($date > $time) {
                            $stats[$key][$d]++;
                            break;
                        }
                    }
                }
            }
        }
        $title = "Last Records";
        $text = "\t\t\t".implode("\t", $types)."\r\n";
        foreach ($days as $d) {
            if ($d == "many") {
                $text .= "Way Old:\t\t";
            } else if ($d < 1) {
                $text .= round($d*24)." hour(s) old:\t\t";
            } else {
                $text .= $d." day(s) old:\t\t";
            }
            foreach ($types as $key => $type) {
                $cnt = $stats[$key][$d];
                if (empty($cnt)) {
                    $cnt = 0;
                }
                $text .= $cnt."\t";
            }
            $text .= "\r\n";
        }
        $this->output($text, $title);

    }
    /**
    * This checks the hourly scripts
    *
    * @param string $text  The text to add to the dailyReport
    * @param string $title The title of the area of the dailyReport
    *
    * @return int The error code, if any
    */
    protected function output($text, $title = "")
    {
        if (!empty($title)) {
            $this->_body .= strtoupper($title)."\r\n";
        }
        $this->_body .= $text;
    }
}


?>
