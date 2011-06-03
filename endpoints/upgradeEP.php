<?php
/**
 * Programs an endpoint using firmware from the database
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
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
 * @category   Scripts
 * @package    Scripts
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
require_once dirname(__FILE__).'/../head.inc.php';

print "Starting...\n";

require_once HUGNET_INCLUDE_PATH.'/tables/FirmwareTable.php';
require_once HUGNET_INCLUDE_PATH.'/containers/ConfigContainer.php';
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");

$firmware = new FirmwareTable();
$dev = new DeviceContainer();
$eepromName = tempnam("/tmp", "hugnet");
print "Finding device and reading information:";
// We just try the processors one at at time
$processors = array(
    "atmega16", "atmega324p", "attiny26", "attiny861", "atmega168"
);
$bootloader = false;
foreach ($processors as $target) {
    $Prog =  "avrdude -c avrisp2 -p $target -P usb ";
    $Prog .= " -B 10 -i 100 -q -q -U eeprom:r:$eepromName:s ";
    // This makes sure there is no output 
    $Prog .= "> /dev/null 2> /dev/null < /dev/null";
    @exec($Prog, $output, $ret);
    
    // If $ret === 0 we found the correct processor
    if ($ret === 0) {
        print " $target\n";
        $firmware->Data = file_get_contents($eepromName);
        $data = $firmware->getData();
        if (($target === "atmega16") || ($target === "atmega324p")) {
            $extra = stristr(substr($data, -80), "003920");
            if (!empty($extra)) {
                $data = substr($data, 0, 20).$extra;
            }
            $bootloader = true;
        }
        $dev->fromSetupString($data);
        break;
    }
}
@unlink($eepromName);

if (empty($dev->FWPartNum)) {
    die(" No endpoint found\n");
}

$firmware->clearData();
print "Device ".$dev->DeviceID." has firmware: ".$dev->FWPartNum."  Version: ".$dev->FWVersion."\n".
$firmware->fromArray(
    array(
        "FWPartNum" => $dev->FWPartNum,
        "Version" => $argv[1],
    )
);
$firmware->getLatest();

print "  Found Version: ".$firmware->Version."\n";

if (!empty($firmware->id)) {
    $Prog = 'avrdude -c avrisp2 -p '.$firmware->Target.' -P usb ';

    // Program the flash
    $tempname = tempnam("/tmp", "uisp");
    $fp = fopen($tempname, "w");
    fwrite($fp, $firmware->Code);
    fclose($fp);
    $flash = ' -e -U flash:w:'.$tempname;
    print "Using: ".$Prog.$flash."\n";
    passthru($Prog.$flash);
    unlink($tempname);

    // Program the E2
    $tempname = tempnam("/tmp", "uisp");

    $fp = fopen($tempname, "w");
    fwrite($fp, $firmware->Data);
    fclose($fp);
    $eeprom = ' -V -U eeprom:w:'.$tempname;

    print "Using: ".$Prog.$eeprom."\n";
    passthru($Prog.$eeprom);

    unlink($tempname);

    // Program the user data
    $dev->FWVersion = $firmware->Version;
    $tempname = tempnam("/tmp", "uisp");
    $data = $dev->toSetupString();
    if ($bootloader) {
        $data = substr($data, 0, 20);
    }
    writeSREC($data, $tempname);

    $eeprom = ' -V -U eeprom:w:'.$tempname;

    print "Using: ".$Prog.$eeprom."\n";
    passthru($Prog.$eeprom);

    unlink($tempname);

} else {
    print "Firmware ".$dev->FWPartNum." not found. \n";
}

/**
 * @endcond
 */
/**
* This writes the srecord
*  
* @param string $data The data to write
* @param string $file The file name to use
* 
* @return string the file name used
*/
function writeSREC($data, $file)
{


    $len = strlen($data)/2 + 3;
    $hexlen = strtoupper(dechex($len));
    $hexlen = str_pad($hexlen, 2, '0', STR_PAD_LEFT);
    $hexlen = substr(trim($hexlen), 0, 2);

    $string = $hexlen."0000".$data;
    $csum = 0;
    for ($i = 0; $i < $len; $i++) {
        $csum += hexdec(substr($string, $i*2, 2));
    }
    $csum = (~$csum) & 0xFF;
    $csum = strtoupper(dechex($csum));
    $csum = str_pad($csum, 2, '0', STR_PAD_LEFT);
    $csum = substr(trim($csum), 0, 2);
    $string .= $csum;

    @unlink($file);
    $fp = fopen($file, 'w');
    fwrite($fp, "S0090000736E2E656570AD\r\n");
    fwrite($fp, "S1".$string."\r\n");
    fwrite($fp, "S9030000FC\r\n");
    fclose($fp);

    return $file;

}

?>
