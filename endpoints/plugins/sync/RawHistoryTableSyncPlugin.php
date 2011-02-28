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
class RawHistoryTableSyncPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "RawHistoryTableSync",
        "Type" => "periodic",
        "Class" => "RawHistoryTableSyncPlugin",
    );
    /** @var This is when we were created */
    protected $firmware = 0;
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
        if (!$this->enable) {
            return;
        }
        $this->local = new RawHistoryTable();
        $this->remote = new RawHistoryTable(array("group" => "remote"));
        // We don't want more than 1000 records at a time;
        $this->local->sqlLimit = 1000;
        $this->local->sqlOrderBy = "Date asc";
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
        if (!$this->control->myConfig->servers->available("remote")) {
            self::vprint(
                "Remote database not available",
                HUGnetClass::VPRINT_NORMAL
            );
            return;
        }
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];

        // Get the devices
        $rows = $this->local->select(
            "`Date` > ?",
            array((int)$last)
        );
        $count = 0;
        $fail = 0;
        // Go through the records
        foreach (array_keys((array)$rows) as $key) {
            $now = $rows[$key]->Date;
            $this->remote->clearData();
            $this->remote->group = "remote";
            $this->remote->fromArray($rows[$key]->toDB());
            if ($this->remote->insertRow(false)) {
                $count++;
            } else {
                $fail++;
            }
        }
        unset($rows);
        if ($count > 0) {
            // State we did some uploading
            self::vprint(
                "Uploaded $count raw history records from "
                .date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($fail > 0) {
            // State we did some uploading
            self::vprint(
                "$fail raw history records FAILED to upload from "
                .date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if (!empty($now)) {
            $last = (int)$now;
        }
    }
    /**
    * This function checks to see if it is ready to run again
    *
    * The default is to run every 24 hours.
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        return $this->enable;
        //return (time() >= ($this->last + 300)) && $this->enable;
    }

}


?>
