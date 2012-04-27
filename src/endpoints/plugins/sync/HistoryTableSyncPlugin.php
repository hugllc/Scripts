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
class HistoryTableSyncPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "HistoryTableSync",
        "Type" => "periodic",
        "Class" => "HistoryTableSyncPlugin",
    );
    /** @var This is when we were created */
    protected $firmware = 0;
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
        $this->enable &= $this->control->myConfig->poll["enable"];
        if (!$this->enable) {
            return;
        }
        $this->raw = new RawHistoryTable();
        // We don't want more than 10 records at a time;
        $this->raw->sqlLimit = 100;
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
        $this->enableRemote = $this->control->myConfig->servers->available("remote");
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        static $prev;
        // Get the devices
        $rows = $this->raw->select(
            "`Date` > ?",
            array((int)$last)
        );
        $remote = 0;
        $local = 0;
        // Go through the records
        foreach (array_keys((array)$rows) as $key) {
            $now = $rows[$key]->Date;
            $id = $rows[$key]->id;
            $hist = &$rows[$key]->toHistoryTable($prev[$id]);
            if ($hist->insertRow(true)) {
                $local++;
                if ($this->enableRemote) {
                    $class = get_class($hist);
                    $r = new $class(array("group" => "remote"));
                    $r->fromArray($hist->toDB());
                    if ($r->insertRow(false)) {
                        $remote++;
                    }
                }
            } else {
                $bad++;
            }
            $prev[$id] = $rows[$key]->raw;
        }
        unset($rows);
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                "Found $bad bad raw history records",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                "Decoded $local raw history records",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($remote > 0) {
            // State we did some uploading
            self::vprint(
                "Uploaded $remote history records",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if (!empty($now)) {
            $this->last = (int)$now;
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
        // Run every minute
        return false; //$this->enable;
    }

}


?>
