<?php
/**
 * Classes for dealing with devices
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
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
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */
/** Stuff we need */
require_once HUGNET_INCLUDE_PATH."/base/PeriodicPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/tables/ErrorTable.php";
/**
 * Base class for all other classes
 *
 * This class uses the {@link http://www.php.net/pdo PDO} extension to php.
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class CriticalErrorCheckPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "CriticalErrorCheckPlugin",
        "Type" => "periodic",
        "Class" => "CriticalErrorCheckPlugin",
    );
    /** @var This is the array of devices in our gateway */
    protected $errors = null;
    /** @var This is the array of devices in our gateway */
    protected $error = null;
    /** @var This is the array of devices in our gateway */
    private $_subject = null;
    /** @var This is our configuration */
    protected $defConf = array(
        "enable"   => true,
    );
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
        $this->enable = !empty($this->control->myConfig->admin_email);
        if (!$this->enable) {
            return;
        }
        $this->_subject = "Critical Error on ".`hostname`;
        $this->error = new ErrorTable();
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
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        // State we are looking for errors
        self::vprint(
            "Checking for new critical errors.  Last Check: "
            .date("Y-m-d H:i:s", $last),
            HUGnetClass::VPRINT_NORMAL
        );
        $this->_body = "";
        $now = time();
        $this->errors = $this->error->select(
            "severity >= ? AND Date >= ?",
            array(ErrorTable::SEVERITY_CRITICAL, (int)$last)
        );
        $this->critical();
        if (!empty($this->_body)) {
            $ret = $this->control->mail($this->_subject, $this->_body);
        }
        unset($this->errors);
        $this->last = $now;
        $last = $now;
        return $ret;
    }

    /**
    * This function checks to see if it is ready to run again
    *
    * Check every 10 minutes
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        return (time() >= ($this->last + 600)) && $this->enable;
    }
    /**
    * This checks the hourly scripts
    *
    * @return int The error code, if any
    */
    protected function critical()
    {
        if (count($this->errors) == 0) {
            return;
        }
        $text = "  #\t\tDate\t\t\tMessage\r\n";
        foreach ($this->errors as $error) {
            $text .= $error->errno."\t\t".date("Y-m-d H:i:s", $error->Date)."\t";
            $text .= $error->error."\r\n";
        }
        $this->output($text, "Critical Errors");
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
            $this->_body .= strtoupper($title)."\n\r";
        }
        $this->_body .= $text;
    }
}


?>
