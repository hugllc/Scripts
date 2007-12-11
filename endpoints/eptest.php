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
 * @subpackage Test
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */

    $GatewayKey=3;
    define("EPTEST_VERSION", "0.0.7");
    define("EPTEST_PARTNUMBER", "0039260450");  //0039-26-04-P

    print '$Id$'."\n";
    print 'eptest.php Version '.EPTEST_VERSION."\n";
    print "Starting...\n";

    require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');
    for ($i = 0; $i < count($newArgv); $i++) {
        switch($newArgv[$i]) {
            // Gateway IP address
            case "-P":
                print "Programming Only\n";
                $programOnly = true;
                break;
            // Packet Command
        }
    }

//    $endpoint->packet->_getAll = true;
    $endpoint->packet->SNCheck(false);

    //Only try twice per server.
    $endpoint->socket->Retries = 2;
    $endpoint->socket->PacketTimeout = 6;
    $firmware = new firmware($endpoint->db);

    $query = "SELECT * FROM endpoints WHERE Obsolete=0";      
    $res = $endpoint->db->getArray($query);

    while (empty($tester)) {
        fwrite(STDOUT, "Please enter your name:  ");
        $tester = trim(fgets(STDIN));
    }

    while(empty($hwPart)) {
        fwrite(STDOUT, "Hardware Part Number:  \n");
        foreach ($res as $k => $f) {
            fwrite(STDOUT, $k.".  ".$f['HWPartNum']."\n");
        }
        fwrite(STDOUT, "? (0 - ".$k.") ");
        $hwChoice = (int) fgets(STDIN);
        if (isset($res[$hwChoice])) {
            $hwPart = $res[$hwChoice];
        }
    }
    print "Using hardware part number '".$hwPart['HWPartNum']."'\n";
    foreach (explode("\n", $hwPart['Parameters']) as $val) {
        $value = explode(":", $val);
        $key = trim(strtolower($value[0]));
        $value = trim($value[1]);
        $hwPart['Param'][$key] = $value;
    }
    if (!isset($hwPart['Param']['firmware'])) {

        $res = GetFirmwareFor($hwPart['HWPartNum']);
        if (is_array($res) && (count($res) > 0)) {
            foreach ($res as $k => $f) {
                if (!isset($fw[$f['FWPartNum']])) {
                    $fw[$f['FWPartNum']] = $f;
                    $fwIndex[] = $f['FWPartNum'];
                }
            }
            if (count($fw) > 1) {
                while (empty($fwPart)) {
                    fwrite(STDOUT, "Choose a Firmware:  \n");
                    foreach ($fwIndex as $k => $f) {
                        fwrite(STDOUT, $k.".  ".$f."\n");
                    }
                    fwrite(STDOUT, "? (0 - ".$k.") ");
                    $fwChoice = (int) fgets(STDIN);
                    if (isset($fwIndex[$fwChoice])) {
                        $fwPart = $fw[$fwIndex[$fwChoice]];
                    }
                }
            } else {
                $fwPart = $fw[$fwIndex[0]];
            }
        } else {
            print "Part number '".$hwPart."' was not found\n";
            $hwPart = "";
        }
    } else {
        $fwPart = $firmware->    GetLatestFirmware($hwPart['Param']['firmware']);
    }
    print "Using firmware part number '".$fwPart['FWPartNum']."' v".$fwPart['FirmwareVersion']."\n";
    while (empty($startSN)) {
        fwrite(STDOUT, "Starting Serial Number (in Hexadecimal):  ");
        $startSN = hexdec(fgets(STDIN));
    }
    $SN = $startSN;

    if ($programOnly !== true) {
        $pInfo = array(
            'GatewayIP' => $GatewayIP,
            'GatewayPort' => $GatewayPort,
            'GatewayKey' => $GatewayKey,
        );
    
        print "Setting up the packet interface...";
        $endpoint->packet->connect($pInfo);
        print " Done \n";
        print "Checking the Controller(s)... ";
    
        $cont = getControllers();
        if (count($cont) > 0) {
            print implode(" ", array_keys($cont));
        }
        print " Done \n";
    }
    while (1) {
//          $Prog = 'uisp -dprog=dapa -v=0 -dpart='.$hwPart['Param']['cpu'].' -dlpt=/dev/parport0';
        $Prog = 'avrdude -p '.$hwPart['Param']['cpu'].' -c avrisp2 -P usb ';
        
        // Start the device Info array
        $DeviceID = strtoupper(dechex($SN));
        $DeviceID = str_pad($DeviceID, 6, '0', STR_PAD_LEFT);
        $DeviceID = substr($DeviceID, 0, 6);

        $dev = $pInfo;
        $dev['DeviceID'] = $DeviceID;

        print "\n\nUsing 0x".$DeviceID." for the next device\n";
        print "Insert the device in the tester.\n";
        print "Press <enter> to continue, q<enter> to quit\n";

        $input = fgets(STDIN);
        if (trim(strtolower($input)) == "q") break;
        $testStart = time();        


        
        // SET the fuses
        print "Setting Fuses...";
        $fuse = ' -U hfuse:w:'.$hwPart['Param']['fusehigh'].':m';
        if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
        exec($Prog.$fuse, $out, $pass['FuseHigh']);
        print " Low ";

        $fuse = ' -U lfuse:w:'.$hwPart['Param']['fuselow'].':m';
        if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
        exec($Prog.$fuse, $out, $pass['FuseLow']);
        print " High ";

        if (isset($hwPart['Param']['fuseextended'])) {
            $fuse = ' -U efuse:w:'.$hwPart['Param']['fuseextended'].':m';
            if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
            exec($Prog.$fuse, $out, $pass['FuseExtended']);
            print " Extended ";
        }
/*        
        if (isset($hwPart['Param']['lockbits'])) {
            $fuse = ' -U lock:w:'.$hwPart['Param']['lockbits'].':m';
            if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
            exec($Prog.$fuse, $out, $pass['LockBits']);
            print " Lock Bits ";
        }
*/
        print " Done \n";


        // Program the flash
        $tempname = tempnam("/tmp", "uisp");
        
        $fp = fopen($tempname, "w");
        fwrite($fp, $fwPart['FirmwareCode']);
        fclose($fp);
        $flash = ' -U flash:w:'.$tempname;

        if ($verbose) print "\nUsing: ".$Prog.$flash."\n";
        exec($Prog.$flash, $out, $pass['Program']);
        unlink($tempname);
        print " Done \n";
        print "Writing Firmware Data... ";
    
        // Program the E2
        $tempname = tempnam("/tmp", "uisp");
        
        $fp = fopen($tempname, "w");
        fwrite($fp, $fwPart['FirmwareData']);
        fclose($fp);
        $eeprom = ' -D -U eeprom:w:'.$tempname;
    
        if ($verbose) print "\nUsing: ".$Prog.$eeprom."\n";
        exec($Prog.$eeprom, $out, $pass['Data']);
        unlink($tempname);
        print " Done \n";

        sleep(2);

        // Write the serial Number
        print "Writing Serial Number 0x".$DeviceID."...";
        $tempname = writeSREC($SN, $hwPart['HWPartNum']);
        
        $eeprom = ' -U eeprom:w:'.$tempname;
        if ($verbose) print "\nUsing: ".$Prog.$eeprom."\n";
        exec($Prog.$eeprom, $out, $pass['Serialnum']);
        print " Done \n";
//        print "Writing Firmware Program... ";

        // Get the configuration
        if ($programOnly !== true) {
    
            $pass['Configuration'] = -1;
    
            print "Pinging ".$dev["DeviceID"]." ";
            $pkt = $endpoint->packet->ping($dev, true);
            print "Done \r\n";
            
            print "Checking the configuration of ".$dev["DeviceID"]." ";
            $pkt = $endpoint->readConfig($dev);
            if ($pkt !== false) {
                $newConfig = $endpoint->InterpConfig($pkt);
                if (is_array($newConfig)) { 
                    $dev = array_merge($dev, $newConfig);    
                    $pass['Configuration'] = 0;
                }
            } else {
                $pass['Configuration'] = 2;
            }
            print "Done \r\n";
            if (method_exists($endpoint->drivers[$dev['Driver']], "loadProgram")) {
                print "Loading program...\n";
                $pass['Load Program'] = !(bool)($endpoint->drivers[$dev['Driver']]->loadProgram($dev, $dev, $fwPart['FirmwareKey']));
                print "Done\r\n";
            }

            // Print the results
            $results = "Results:\n";
            $failed = false;
            foreach ($pass as $name => $p) {
                $results .= $name.": ";
                if ($p == 0) {
                    $results .= "Pass";
                } else {
                    $results .= "Failed";
                    $failed = true;
                }
                $results .= "\n";
            }        
    
            print "Saving Test Log...";
            $log = array(
                'HWPartNum' => $hwPart['HWPartNum'],
                'LogDate' => date("Y-m-d H:i:s"),
                'Log' => $results,
                'PassedTest' => !$failed,
                'Tester' => $tester,
                'testVersion' => EPTEST_VERSION,
            );
            if ($failed == false) {
                $log['Log'] .= "SN: ".$dev['DeviceID']."\n";
            }
            $log['Log'] .= "Time: ".(time() - $testStart)."\n";
            $return = $endpoint->db->AutoExecute('testLog', $log, 'INSERT');
    
    
            print "\n".$results;
        }

        

        if ($failed) {
            print "\n\nTHIS DEVICE FAILED.  PLEASE REMOVE IT AND REWORK IT!\n\n";
            print "Press <enter> to continue\n";
            $input = fgets(STDIN);
        } else {
            // UPdate the database
            print "Updating Database...";
            $return = $endpoint->db->AutoExecute($endpoint->device_table, $dev, 'INSERT');
            if ($return) {
                print " Done ";                    
            } else {
                print " Failed ";
            }
            print "\r\n";
    
            print "\n\nThis device Passed.  Please mark it with the serial number ".$dev['DeviceID']."\n\n";
            $SN++;
        }

    }    

    print "Finished\n";
    exit    (0);

function getControllers() {
    global $endpoint;

    $cPkt = array("to" => "FFFFFF", "command" => "DC");
       $pkt = $endpoint->packet->SendPacket($pInfo, $cPkt);
    $cont = array();
    if (is_array($pkt)) {
        foreach ($pkt as $p) {
            $c = $endpoint->InterpConfig(array($p));
            $cfg = $endpoint->readConfig($c);
        
            $config = $endpoint->InterpConfig($cfg);
            $cont[$p['From']] = $config;
               if (method_exists($endpoint->drivers[$config['Driver']], "checkProgram")) {
                print " Checking Program ";
                $ret = $endpoint->drivers[$config['Driver']]->checkProgram($config, $cfg, true);
                if ($ret) {
                    print " Done ";
                } else {
                    print " Failed ";
                }
            }

        }
    } else {
        return false;
    }
    return ($cont);
}


function writeSREC($sn, $pn, $file="/tmp/uispsn") {

    $sn = dechex($sn);
    $sn = str_pad($sn, 10, '0', STR_PAD_LEFT);
    $sn = substr(trim($sn), 0, 10);
    $sn = strtoupper($sn);

    $pn = trim(strtoupper($pn));
    $hexpn = str_replace("-", "", $pn);
    $let = substr($hexpn, strlen($hexpn)-1, 1);
    $hexpn = substr($hexpn, 0, strlen($hexpn)-1);
    $hexpn .= dechex(ord($let));
    
    $hexpn = str_pad(trim($hexpn), 10, '0', STR_PAD_LEFT);
    $hexpn = substr($hexpn, 0, 10);

    $len = 13;
    $hexlen = strtoupper(dechex($len));
    $hexlen = str_pad($hexlen, 2, '0', STR_PAD_LEFT);
    $hexlen = substr(trim($hexlen), 0, 2);

    $string = $hexlen."0000".$sn.$hexpn;
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
