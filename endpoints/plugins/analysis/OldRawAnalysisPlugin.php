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
require_once HUGNET_INCLUDE_PATH."/base/DeviceProcessPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/tables/GenericTable.php";
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
class OldRawAnalysisPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "OldRawAnalysis",
        "Type" => "analysisPeriodic",
        "Class" => "OldRawAnalysisPlugin",
    );
    /** @var This is our configuration */
    protected $defConf = array(
        "enable"   => false,
    );
    private $_devices = array (
        3 => 33,
        4 => 34,
        6 => 38,
        8 => 40,
        10 => 42,
        11 => 43,
        12 => 44,
        13 => 45,
        14 => 46,
        15 => 47,
        16 => 48,
        25 => 57,
        17 => 49,
        18 => 50,
        19 => 51,
        20 => 52,
        21 => 53,
        22 => 54,
        23 => 55,
        24 => 56,
        28 => 58,
        29 => 59,
        31 => 61,
        32 => 62,
        33 => 63,
        34 => 64,
        35 => 66,
        36 => 65,
        37 => 67,
        38 => 68,
        40 => 69,
        41 => 72,
        42 => 73,
        43 => 76,
        44 => 77,
        45 => 86,
        46 => 78,
        47 => 79,
        48 => 80,
        49 => 81,
        50 => 82,
        51 => 84,
        52 => 83,
        53 => 85,
        54 => 95,
        55 => 99,
        56 => 100,
        57 => 93,
        58 => 98,
        59 => 96,
        60 => 90,
        61 => 101,
        62 => 92,
        63 => 97,
        64 => 102,
        65 => 91,
        66 => 74,
        67 => 71,
        70 => 88,
        71 => 89,
        72 => 94,
        74 => 120,
        75 => 106,
        77 => 170,
        76 => 171,
        79 => 115,
        80 => 119,
        81 => 169,
        82 => 118,
        83 => 114,
        84 => 117,
        85 => 70,
        86 => 113,
        87 => 123,
        88 => 122,
        89 => 112,
        90 => 121,
        92 => 107,
        93 => 167,
        95 => 108,
        97 => 87,
        99 => 157,
        98 => 168,
        100 => 159,
        101 => 158,
        102 => 156,
        103 => 116,
        104 => 151,
        105 => 176,
        106 => 173,
        107 => 175,
        108 => 174,
        109 => 178,
        110 => 179,
        113 => 180,
        114 => 155,
        117 => 103,
        118 => 164,
        119 => 185,
        120 => 163,
        122 => 152,
        123 => 182,
        125 => 186,
        127 => 161,
        128 => 162,
        129 => 165,
        130 => 136,
        131 => 137,
        132 => 138,
        133 => 139,
        134 => 140,
        135 => 141,
        136 => 142,
        137 => 143,
        138 => 144,
        139 => 145,
        140 => 146,
        141 => 147,
        143 => 189,
        144 => 110,
        145 => 195,
        146 => 191,
        147 => 197,
        148 => 198,
        149 => 199,
        150 => 200,
        151 => 201,
        153 => 203,
        154 => 204,
        155 => 205,
        157 => 207,
        158 => 208,
        159 => 209,
        160 => 210,
        161 => 211,
        164 => 214,
        165 => 215,
        166 => 216,
        167 => 217,
        168 => 218,
        169 => 219,
        170 => 220,
        171 => 221,
        172 => 222,
        173 => 223,
        174 => 224,
        175 => 225,
        176 => 226,
        177 => 227,
        178 => 228,
        179 => 229,
        180 => 230,
        181 => 231,
        182 => 232,
        183 => 233,
        184 => 234,
        185 => 235,
        186 => 236,
        187 => 237,
        188 => 238,
        189 => 239,
        190 => 240,
        191 => 241,
        192 => 242,
        193 => 243,
        194 => 244,
        195 => 245,
        196 => 246,
        197 => 247,
        198 => 248,
        199 => 249,
        200 => 250,
        203 => 253,
        205 => 255,
        206 => 256,
        207 => 257,
        208 => 258,
        209 => 259,
        210 => 260,
        211 => 261,
        212 => 262,
        213 => 263,
        229 => 264,
        233 => 274,
        237 => 265,
        241 => 272,
        253 => 270,
        245 => 268,
        497 => 311,
        257 => 273,
        261 => 277,
        265 => 278,
        269 => 266,
        273 => 267,
        277 => 271,
        281 => 275,
        285 => 276,
        289 => 279,
        305 => 283,
        309 => 284,
        313 => 285,
        317 => 286,
        321 => 287,
        325 => 289,
        329 => 290,
        333 => 291,
        337 => 292,
        341 => 293,
        345 => 294,
        349 => 295,
        353 => 296,
        361 => 282,
        365 => 188,
        369 => 297,
        373 => 1,
        377 => 187,
        381 => 298,
        414 => 3,
        418 => 4,
        422 => 5,
        426 => 6,
        430 => 7,
        434 => 8,
        435 => 9,
        439 => 10,
        443 => 11,
        445 => 193,
        454 => 300,
        458 => 301,
        462 => 302,
        466 => 303,
        474 => 305,
        478 => 306,
        482 => 307,
        495 => 12,
        501 => 312,
        503 => 13,
        526 => 324,
        530 => 325,
        534 => 326,
        538 => 327,
        542 => 328,
        546 => 329,
        550 => 330,
        554 => 331,
        558 => 332,
        562 => 333,
        565 => 336,
        569 => 337,
        573 => 338,
        581 => 340,
        593 => 343,
        597 => 344,
        609 => 347,
        618 => 352,
        626 => 354,
        634 => 356,
        638 => 357,
        642 => 358,
        646 => 359,
        654 => 361,
        658 => 362,
        666 => 364,
        670 => 365,
        674 => 366,
        678 => 367,
        718 => 377,
        722 => 378,
        726 => 379,
        727 => 14,
        741 => 386,
        749 => 149,
        753 => 388,
        777 => 391,
        1633 => 301,
        1637 => 349,
        1641 => 337,
        1645 => 375,
        1649 => 336,
        1653 => 374,
        1657 => 202,
        1661 => 373,
        1665 => 355,
        1669 => 346,
        1673 => 300,
        1677 => 370,
        1681 => 372,
        1685 => 181,
        1689 => 341,
        1693 => 342,
        1697 => 299,
        1701 => 392,
        1705 => 305,
        1709 => 376,
        1713 => 321,
        1717 => 338,
        1721 => 339,
        1725 => 371,
        1729 => 213,
        1733 => 320,
        1737 => 302,
        1741 => 348,
        1745 => 368,
        1749 => 306,
        1753 => 323,
        1757 => 8,
        1761 => 307,
        1765 => 344,
        1769 => 5921280,
        1773 => 345,
        1777 => 206,
        1781 => 369,
        1785 => 363,
        1789 => 310,
        1793 => 309,
        1797 => 322,
        1801 => 353,
        1805 => 308,
        1809 => 304,
        9122 => 109,
    );
    /**
    * This function sets up the driver object, and the database object.  The
    * database object is taken from the driver object.
    *
    * @param mixed         $config The configuration array
    * @param DeviceProcess &$obj   The controller object
    *
    * @return null
    */
    public function __construct($config, DeviceProcess &$obj)
    {
        parent::__construct($config, $obj);
        $this->enable &= $this->control->myConfig->servers->available("old");
        if (!$this->enable) {
            return;
        }
        if (empty($this->conf["maxRecords"])) {
            $maxRec = 1000;
        } else {
            $maxRec = $this->conf["maxRecords"];
        }
        $this->raw = new RawHistoryTable();
        // We don't want more than 10 records at a time;
        $this->raw->sqlLimit = $maxRec;
        $this->raw->sqlOrderBy = "Date asc";
        $this->oldRaw = new GenericTable(array("group" => "old"));
        $this->oldRaw->forceTable("history_raw");
        $this->oldRaw->sqlOrderBy = "Date asc";
        $this->oldRaw->sqlLimit = $maxRec;
        $this->pkt = new PacketContainer();
        /*
        $this->oldDev = new GenericTable(array("group" => "old"));
        $this->oldDev->sqlID = "DeviceKey";
        $this->oldDev->forceTable("devices");
        */
        $this->myDev = new DeviceContainer(array("group" => "default"));
        // State we are here
        self::vprint(
            "Registed class ".self::$registerPlugin["Class"],
            HUGnetClass::VPRINT_NORMAL
        );
        
    }
    /**
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function main(DeviceContainer &$dev)
    {
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        $old = $last;
        $startTime = time();
        $ret = $this->oldRaw->selectInto(
            "Date >= ?",
            array(date("Y-m-d H:i:s", (int)$last))
        );
        /*
        // State we did some uploading
        self::vprint(
            "Retrieved raw history in ".(time() - $startTime)." s",
            HUGnetClass::VPRINT_NORMAL
        );
        */
        $count = 0;
        $bad = 0;
        $local = 0;
        $failed  = 0;
        $startTime = time();
        while ($ret) {
            $this->raw->clearData();
            $this->myDev->clearData();
            $this->myDev->getRow($this->_getID($this->oldRaw->DeviceKey));
            if ($this->myDev->isEmpty()) {
                $bad++;
                continue;
            }
            $time = $this->oldRaw->unixDate($this->oldRaw->Date, "UTC");
            $this->pkt->clearData();
            $this->pkt->fromArray(
                array(
                    "To" =>  $this->myDev->DeviceID,
                    "Command" => $this->oldRaw->sendCommand,
                    "Time" => $time - $this->oldRaw->ReplyTime,
                    "Date" => $time - $this->oldRaw->ReplyTime,
                    "Reply" => new PacketContainer(
                        array(
                        "From" => $this->myDev->DeviceID,
                        "Command" => PacketContainer::COMMAND_REPLY,
                        "Data" => $this->oldRaw->RawData,
                        "Length" => strlen($this->oldRaw->RawData)/2,
                        "Time" => $time,
                        "Date" => $time,
                        )
                    ),
                )
            );
            $this->raw->fromArray(
                array(
                    "id" => hexdec($this->myDev->id),
                    "Date" => $this->oldRaw->unixDate($this->oldRaw->Date, "UTC"),
                    "packet" => $this->pkt,
                    "device" => $this->myDev,
                    "command" => $this->oldRaw->sendCommand,
                    "dataIndex" => $this->myDev->dataIndex($this->oldRaw->RawData),
                )
            );
            $ins = $this->raw->insert();
            if ($ins) {
                $hist =& $this->raw->toHistoryTable($prev);
                $count++;
                if ($this->conf["dots"] && (($count % 100) == 0)) {
                    print ".";
                }
                if ($hist->insertRow(true)) {
                    $local++;
                } else {
                    $bad++;
                    if ($this->conf["dots"] && (($bad % 100) == 0)) {
                        print "B";
                    }
                }
                $prev = $this->raw->raw;
            } else {
                $failed++;
                if ($this->conf["dots"] && (($failed % 100) == 0)) {
                    print "F";
                }
            }
            //$now = $this->raw->Date;
            if (!empty($this->raw->Date)) {
                $last = (int)$this->raw->Date;
            }
            $ret = $this->oldRaw->nextInto();
        }
        $this->raw->insertEnd();
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                "Moved $count good raw history records ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last)." in "
                .(time() - $startTime)." s",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                "Found $bad bad raw history records ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($failed > 0) {
            // State we did some uploading
            self::vprint(
                "$failed raw history records failed to insert ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                "Decoded $local raw history records ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
            );
        }/*
        if (!empty($now)) {
            $last = (int)$now;
        }*/
    }
    /**
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready(DeviceContainer &$dev)
    {
        return $this->enable;
    }
    /**
    * Method to set the id
    *
    * @param int $DeviceKey The devicekey to check
    *
    * @return null
    */
    private function _getID($DeviceKey)
    {
        return $this->_devices[$DeviceKey];
    }

}


?>
