<?php
/**
	$Id$
	@file scripts/endpoints/updatedb.php
	@brief Pushes data from the polling machine to the database.
	
*/
/**
 * @cond	SCRIPT
*/
    define("UPDATEDB_VERSION", "0.2.5");
    define("UPDATEDB_PARTNUMBER", "0039260250");  //0039-26-01-P
    define("UPDATEDB_SVN", '$Id$');


	print '$Id$'."\n";
    print 'updatedb.php Version '.UPDATEDB_VERSION."\n";
	print "Starting...\n";
	
	require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.inc.php');

//	$mhistory = new history_raw($db, $conf['hugnetDb']);

//    $lplog = new plog();


	$refreshdev = TRUE;

    $updatedb = new ep_updatedb($endpoint);
    $updatedb->uproc->register();
    
	while(1) {

        $updatedb->getAllDevices();

		if ($updatedb->verbose) print "[".$updatedb->uproc->me["PID"]."] Starting database update...\n";
//		$updatedb->uproc->FastCheckin();

		// This section does the packetlog
		$updatedb->updatedb();
        $updatedb->getPacketSend();

		//		$lplog->reset();
        $updatedb->wait();

        // Check the PHP log to make sure it isn't too big.
        clearstatcache();
        if (filesize("/var/log/php.log") > (1024*1024)) {
            $fd = fopen("/var/log/php.log","w");
            @fclose($fd);
        }

	}
    $updatedb->uproc->unregister();

	print "[".$this->uproc->me["PID"]."] Finished\n";

	//$this->uproc->Unregister();
//	$this->uproc->CheckUnregistered(TRUE);
/**
 * @endcond
 */
class ep_updatedb {

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    var $doPoll = FALSE;
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $verbose = FALSE;

    function __construct(&$endpoint) {
        $this->endpoint = &$endpoint;
        $this->plog = new plog();
        $this->plog->createPacketLog();
        $this->psend = new plog("PacketSend");
//        $this->psend->createPacketLog("PacketSend");
        $this->uproc = new process();
        $this->uproc->clearStats();
        $this->uproc->setStat('start', time());
        $this->uproc->setStat('PID', $this->uproc->me['PID']);
        $this->devices = new deviceCache();

     }

    function getAllDevices() {
		// Regenerate our endpoint information
		if (((time() - $this->lastdev) > 120) || (count($this->ep) < 1)) {
			$this->lastdev = time();

			print "Getting endpoints\n";


        	$query = "SELECT * FROM ".$this->endpoint->device_table;
//        	         " LEFT JOIN ".$this->endpoint->gateway->table.
//        	         " ON ".$this->endpoint->device_table.".".$this->endpoint->gateway->id."=".$this->endpoint->gateway->table.".".$this->endpoint->gateway->id;
/*
        	         " WHERE ".
        	         " LastConfig>='".$this->cutoffdate."' ";
*/
            $res = $this->endpoint->db->getArray($query);
        	if (is_array($res) && (count($res) > 0)) {
                $this->oldep = $this->ep;
                $this->ep = array();
        		foreach($res as $key => $val) {
                    $dev = $this->endpoint->DriverInfo($val);
                    $dev['params'] = device::decodeParams($dev['params']);
//                    if (isset($this->oldep[$key])) $dev = array_merge($this->oldep[$key], $dev);
                    $val['DeviceID'] = trim(strtoupper($val['DeviceID']));
        			$this->ep[$val['DeviceID']] = $dev;				
                    $res = $this->devices->add($dev);
        		}
            }
        }
        return $this->ep;    
    }

    function getPacketSend() {
    	$query = "SELECT * FROM PacketSend WHERE Checked = 0";
        $res = $this->endpoint->db->getArray($query);
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Checking for Outgoing Packets\n";
    	if (is_array($res) && (count($res) > 0)) {
            foreach($res as $packet) {
                unset($packet['Checked']);
                $found = FALSE;
                if (isset($this->ep[$packet['DeviceID']])) {
                    $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                    $found = TRUE;
                } else {
            
                    foreach($this->ep as $key => $val) {
                        if (trim(strtoupper($val['DeviceID'])) == trim(strtoupper($packet['PacketTo']))) {
                            $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                            $found = TRUE;
                            break;
                        }
                    }
                }
                if ($found) {
                    print "[".$this->uproc->me["PID"]."] ".$packet["PacketTo"]." -> ".$packet["sendCommand"]." -> ";                        
                    if ($this->psend->add($packet)) {
                        print " Saved ";
                        $where = " (GatewayKey = '".$packet['GatewayKey']."'".
                                 " AND ".
                                 " Date = '".$packet['Date']."'".
                                 " AND ".
                                 " Command = '".$packet['Command']."'".
                                 " AND ".
                                 " sendCommand = '".$packet['sendCommand']."'".
                                 " AND ".
                                 " PacketFrom = '".$packet['PacketFrom']."'".
                                 " AND ".
                                 " PacketTo = '".$packet['PacketTo']."') ";
                        
                        $res = $this->endpoint->db->AutoExecute("PacketSend", array("Checked" => 1), 'UPDATE', $where);
                        if ($res) {
                            print " Updated ";
                        }
    //                    print $this->endpoint->db->ErrorMsg();
                    } else {
                        print " Failed ";
                    }
                    print "\n";
                }
            }
        }
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Done\n";
       
    }

    function wait() {
    	if ($this->verbose) print  "[".$this->uproc->me["PID"]."] Pausing...\n";
//        $cnt = 0;
//		while((date("i") == $this->lastminute) && ($cnt++ < 2)) {
		    sleep(2);
//		}
//        sleep(10);
        $this->lastminute = date(i);

    }

    function updatedb() {
    	$res = $this->plog->getAll(50);
		if ($this->verbose) print "[".$this->uproc->me["PID"]."] Found ".count($res)." Packets\n";
        if (!is_array($res)) return;
		foreach($res as $packet) {
            $this->uproc->incStat("Packets");					

			print "[".$this->uproc->me["PID"]."] ".$packet["PacketFrom"]." ".$packet["sendCommand"];

            $DeviceID = trim(strtoupper($packet['PacketFrom']));       
            if (is_array($this->ep[$DeviceID])) {
                $packet = array_merge($this->ep[$DeviceID], $packet);
            }
			$remove = FALSE;

			switch($packet['Type']) {
				case 'UNSOLICITED':
                    $this->uproc->incStat("Unsolicited");					
				    $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
					if ($return) {
						print " - Inserted ".$packet['sendCommand']."";					
						$remove = TRUE;
					} else {
                        $error = $this->endpoint->db->MetaError();
                        if ($error == DB_ERROR_ALREADY_EXISTS) {
							print " Duplicate ".$packet['Date']." ";
							$remove = TRUE;											
						} else {
                            $this->uproc->incStat("Unsolicited Failed");					
							print " - Failed ";
						}
					}
					break;
				case 'REPLY':
                    $this->uproc->incStat("Reply");
				    $return = $this->endpoint->db->AutoExecute("PacketSend", $packet, 'INSERT');
					if ($return) {
						print " - Inserted into PacketSend ".$packet['sendCommand']."";					
						$remove = TRUE;
					} else {
						print " - Failed ";
                        $this->uproc->incStat("Reply Failed");
					}
					break;
				case "CONFIG":
                    $this->uproc->incStat("Config");
				    $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
                    
					if ($return) {
						print " - Moved ";
						$remove = TRUE;
	
					} else {
                        $error = $this->endpoint->db->MetaError();
                        if ($error == DB_ERROR_ALREADY_EXISTS) {
							print " Duplicate ".$packet['Date']." ";
							$remove = TRUE;											
						} else {
							print " - Failed ";
                           $this->uproc->incStat("Config Failed");
						}
					}
					if ($this->endpoint->UpdateDevice(array($packet))) {
                        $this->uproc->incStat("Device Updated");
						print " - Updated ";					
						$refreshdev = TRUE;
					} else {
						print " - Update Failed ";

//	    			    $return = $this->endpoint->db->AutoExecute($this->endpoint->device_table, $packet, 'INSERT');
					}
					break;
				case 'POLL':
                    $this->uproc->incStat("Poll");
                    print " ".$packet['Driver']." ";
					$packet = $this->endpoint->InterpSensors($packet, array($packet));
					$packet = $packet[0];
					print " ".$packet["Date"]; 
					print " - decoded ".$packet['sendCommand']." ";
					$duplicate = FALSE;
					if (isset($packet['DataIndex'])) {

                        $query = " SELECT * FROM ".$this->endpoint->raw_history_table.
                                 " WHERE " .
                                 " DeviceKey=".$packet['DeviceKey'] .
                                 " AND " .
                                 " sendCommand='".$packet['sendCommand']."'" .
                                 " AND " .
                                 " Date='".$packet["Date"]."' " . 
                                 " ORDER BY 'Date' desc " .
                                 " LIMIT 0, 1 ";
                        $check = $this->endpoint->db->getArray($query);
						if (is_array($check)) {
							$check = $this->endpoint->InterpSensors($packet, $check);
							if ($check[0]['DataIndex'] == $packet['DataIndex']) {
								$duplicate = TRUE;
							}
						}
					}
					if ($duplicate === FALSE) {
						$return = $this->endpoint->db->AutoExecute($this->endpoint->raw_history_table, $packet, 'INSERT');

						if ($return) {
							$info = array();
							print " - raw history ";
			
                            $set = " LastPoll = '".$packet["Date"]."' " .
                                   ", GatewayKey = '".$packet['GatewayKey']."' ";

							$hist = $this->endpoint->saveSensorData($packet, array($packet));
							if ($hist) {
								$set .= ", LastHistory = '".$packet["Date"]."' ";
								print " - ".$packet["Driver"]." history ";
							} else {
								print " - History Failed";
								if ($testMode) {
								    if ($this->endpoint->db->MetaError() != 0) {
    								    print $this->endpoint->db->MetaErrorMsg();
    								}
								}
							}

                            $query = " UPDATE ".$this->endpoint->device_table.
                                     " SET " . $set .
                                     " WHERE " .
                                     " DeviceKey=".$packet['DeviceKey'];

							if ($this->endpoint->db->Execute($query)) {
								print " - Last Poll ";
							} else {
								print " - Last Poll Failed ";
							}
							$remove = TRUE;
						} else {
                            $error = $this->endpoint->db->MetaError();
                            if ($error == DB_ERROR_ALREADY_EXISTS) {
								print " Duplicate ".$packet['Date']." ";
								$remove = TRUE;											
							} else {
								print " - Raw History Failed ";
                                $this->uproc->incStat("Poll Failed");
							}
						}

					} else {
   						print "Duplicate";
  						$remove = TRUE;
					}
					break;
				default:
                    $this->uproc->incStat("Unknown");
					$remove = TRUE;
					break;
				
			}
			if ($remove) {
				if ($this->plog->remove($packet)) {
					print " - local deleted";
				} else {
    				print " - Delete Failed";
				}
			}
			print "\r\n";
		}

    }
}
?>
