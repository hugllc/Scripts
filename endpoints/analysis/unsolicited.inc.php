<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
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
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

/**
 * Gets interesting information out of the packet logs
 *
 * @param array &$analysis->analysisOut Here
 * @param array &$devInfo   The devInfo array for the device
 *
 * @return null
 */
function analysis_unsolicited(&$analysis, &$devInfo) {
    $sTime = microtime(true);

    if ($analysis->verbose > 1) print "analysis_unsolicited start\r\n";

    $analysis->analysisOut["Powerups"] = 0;
    $analysis->analysisOut["Boredom"] = 0;
    $analysis->analysisOut["Reconfigs"] = 0;
    
    foreach ($analysis->plogCache as $log) {
        switch($log["Command"]) {
            case "5D":
                $analysis->analysisOut["Reconfigs"]++;
                break;
            case "5E":
                $analysis->analysisOut["Powerups"]++;
                break;
            case "5F";
                $analysis->analysisOut["Boredom"]++;
                break;
            default:
                break;
        }
    }        
    
    $dTime = microtime(true) - $sTime;
    if ($analysis->verbose > 1) print "analysis_unsolicited end (".$dTime."s)\r\n";

}


$this->registerFunction("analysis_unsolicited", "Analysis9");

?>