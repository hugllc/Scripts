<?php
/**
	$Id: checkInterp.php 249 2007-09-21 14:45:49Z prices $
	@file scripts/test/sendpacket.php
	@brief generic script for sending packets to an endpoint
	
	
*/ 
/**
 * @cond	SCRIPT
*/
    $required = array(
        "longName" => "Long Name",
        "unitType" => "Unit Type",
        "storageUnit" => "Storage Unit",
    );
    $suggested = array(
        "function" => "Decoding Function",
        "checkFunction" => "Record checking function",
    );
    $other = array(
        "mult" => "Multiplier",
        "doTotal" => "Total",
        "extraText" => "Extra Text",
        "extraDefault" => "Extra Default",
    );

    require_once(dirname(__FILE__).'/../head.inc.php');
	
    if (is_array($endpoint->sensors->sensors)) {
        foreach($endpoint->sensors->sensors as &$class) {
            print "Working with class: ".get_class($class)."\n";
            if (is_array($class->sensors)) {
                foreach($class->sensors as $type => $sArray) {
                    print "-> 0x".dechex($type)."\n";
                    if (is_array($sArray)) {
                        foreach($sArray as $shortName => $s) {
                            print "--> Short Name: '".$shortName."'\n";
                            foreach($required as $key => $name) {
                                if (isset($s[$key])) {
                                    print "---> ".$name.": '".$s[$key]."'\n";
                                    unset($s[$key]);
                                } else {
                                    die("** ERROR ** ".$key." is not defined!\n");
                                }
                            }
                            foreach($suggested as $key => $name) {
                                if (isset($s[$key])) {
                                    print "---> ".$name.": '".$s[$key]."'\n";
                                    unset($s[$key]);
                                } else {
                                    print "** WARNING ** ".$key." is not defined!\n";
                                }
                            }
                            foreach($other as $key => $name) {
                                if (isset($s[$key])) {
                                    print "---> ".$name.": '".$s[$key]."'\n";
                                    unset($s[$key]);
                                } else {
                                    print "---> ".$key." is not defined. (This is okay)\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        print "No sensors defined. \n";
    }

?>
