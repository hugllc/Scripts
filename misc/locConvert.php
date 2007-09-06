<?php
/**
	$Id: monitor.php 121 2007-07-19 13:13:02Z prices $
	@file scripts/endpoints/unsolicited.php
	@brief Sits and waits for unsolicited packets to come in.
		
*/
/**
 * @cond SCRIPT	
*/
	print '$Id: monitor.php 121 2007-07-19 13:13:02Z prices $'."\n";
	print "Starting...\n";


	require_once(dirname(__FILE__).'/../head.inc.php');

    $query = "select * from location";
    $oldLoc = array();
    $loc = $endpoint->db->getArray($query);
    if (is_array($loc)) {
        foreach($loc as $row) {
            foreach($row as $key => $tLoc) {
                $key = trim($key);
                if (strtolower(substr($key, 0, 3)) == "loc") {
                    if (!empty($tLoc)) {
                        $nKey = (int) substr($key, 3);
                        $oldLoc[$row["DeviceKey"]][$nKey] = $tLoc;
                    }
                }
            }
        }
    }

    foreach ($oldLoc as $DeviceKey => $loc) {
        print $DeviceKey;
        $devInfo = $endpoint->device->getDevice($DeviceKey);
        foreach($loc as $key => $l) {
            $devInfo['params']['Loc'][$key] = $l;
        }
        $ret = $endpoint->device->setParams($DeviceKey, $devInfo['params']);
        if ($ret) {
            print " Done ";
        } else {
            print " Failed ";
        }
        print "\n";
    }

	print "Finished\n";
/**
 * @endcond	
*/


?>
