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
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Scripts
 * @subpackage Misc
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */
    require_once(dirname(__FILE__).'/../head.inc.php');

    require_once('firmware.inc.php');
    
    if (empty($argv[1])) {
        die("Usage: ".$argv[0]." <firmwarePart> [ <clean> [ <parallel port> ] ]\n"); 
    }
    if (empty($argv[3])) {
        $pPort = '/dev/parport0';
    } else {
        $pPort = $argv[3];
    }

    if (trim(strtolower($argv[1])) == 'clean') {
        unlink('/tmp/uisp*');
        die();    
    }


    if ((trim(strtolower($argv[2])) != 'clean') && file_exists("/tmp/uisp".$argv[1])) {
        print "Reading information from cache: ";
        $ret = file("/tmp/uisp".$argv[1]);
        $ret = implode("", $ret);
        $ret = trim($ret);
        $ret = unserialize($ret);
        print "  Found Version: ".$ret['FirmwareVersion']."\n";
    }
    if (!isset($ret['FirmwareCode'])) {
        print "Reading information from database: ";
        $dsn = "mysql://Portal:".urlencode("Por*tal")."@floyd.int.hugllc.com/HUGNet";
        $db = NewADOConnection($dsn);
        $firmware = new firmware($db);
        $ret = $firmware->GetLatestFirmware($argv[1]);

        print "  Found Version: ".$ret['FirmwareVersion']."\n";

        print "Caching...";
     * @unlink("/tmp/uisp".$ret["FWPartNum"]);
        $fp = fopen("/tmp/uisp".$ret["FWPartNum"], 'w');
        fwrite($fp, serialize($ret));
        fclose($fp);
        print "Done\n";
    }

    if (!is_null($ret)) {
    //    $Prog = 'uisp -dprog=dapa -v -dpart='.$ret['Target'].' -dlpt='.$pPort.' -dvoltage=5.0';
        $Prog = 'avrdude -c avrisp2 -p '.$ret['Target'].' -P usb ';
    
        // Program the flash
        $tempname = tempnam("/tmp", "uisp");
        
        $fp = fopen($tempname, "w");
        fwrite($fp, $ret['FirmwareCode']);
        fclose($fp);
    //    $flash = ' --segment=flash --erase --upload --verify if='.$tempname;
        $flash = ' -e -U flash:w:'.$tempname.':s';
        print "Using: ".$Prog.$flash."\n";
        passthru($Prog.$flash);
        unlink($tempname);
    
        // Program the E2
        $tempname = tempnam("/tmp", "uisp");
        
        $fp = fopen($tempname, "w");
        fwrite($fp, $ret['FirmwareData']);
        fclose($fp);
//        $eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
        $eeprom = ' -V -U eeprom:w:'.$tempname.':s';
    
        print "Using: ".$Prog.$eeprom."\n";
        passthru($Prog.$eeprom);
    
        unlink($tempname);
    } else {
        print "Firmware not found. \n";
    }

/**
 * @endcond
 */
?>
