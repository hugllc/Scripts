# _HUGnet Scripts_

This is the code that is behind the scenes, running the HUGnet system.  These scripts
do polling of data, checking configurations, updating the master server with changes,
and various other things.


## Project Setup

### Directory Structure
This project is broken up into only a couple of directories:

1. The include/ directory must be put into the include path as HUGnetScripts/
2. The bin/ directory contains all of the normally installed scripts
3. The misc/ directory contains scripts that don't have any other place
4. The test directory contains the unit tests

### Requirements

The scripts requires HUGnetLib/src/php to be installed in the php include path as HUGnetLib/.
All of the dependencies for HUGnetLib must be installed.

## Testing

There is currently no unit testing for this project.  This is one of the items on the todo list


## Deploying

### Ubuntu
Currently there are only build scripts for building .deb files for Ubuntu.  They are
created by running 'ant deb'.  The debs will be in the ./rel directory.


## Troubleshooting & Useful Tools

Most of the code actually resides in HUGnetLib.  These scripts are just a command line
front end for HUGnetLib.

## Contributing changes
Changes can be contributed by either:

1. Using git to create patches and emailing them to patches@hugllc.com
2. Creating another github repository to make your changes to and submitting pull requests.

## Filing Bug Reports
The bug tracker for this project is at http://dev.hugllc.com/bugs/ .  If you want an
account on that site, please email prices@hugllc.com.

## License
This is released under the GNU GPL V3.  You can find the complete text in the
LICENSE file, or at http://opensource.org/licenses/gpl-3.0.html