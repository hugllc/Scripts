<?php
/**
 * Tests, serializes, and loads bootloader into HUGnetLab endpoints
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
 * @subpackage Test
 * @author     Jeff Liesmaki <jeffl@hugllc.com>
 * @copyright  2007-2012 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
 
/****************************************************************************
* List below are the objectives for this PHP script:
*        I.  Initialize the JTAG emulator - okay
*        II. Load and run test firmware
*            A. Should initial test load enough firmware to send and 
*               and receive packets through serial port?
*        III Program endpoint with serial number and hardware versio
*            A. Does this require a modification of program.cfg for 
*                openocd?
*        IV  Load the bootloader program
*            A. This should use current 003937boot install program.cfg
*
*/


print "Hello World, lets try starting up the JTAG through openocd!\n";

$Prog = "~/code/HOS/toolchain/bin/openocd -f ~/code/HOS/src/003937test/program.cfg";

exec($Prog, $out, $return);

print "Press the reset on the emulator adaptor board.\j";
$response = readline( "\nIs the amber LED on? (y/n): ");




print "End of trial!\n";

?>
