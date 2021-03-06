# _HUGnet Scripts_

This is the code that is behind the scenes, running the HUGnet system.  These scripts
do polling of data, checking configurations, updating the master server with changes,
and various other things.


## Project Setup

### Directory Structure
This project is broken up into only a couple of directories:

- The include/ directory must be put into the include path as HUGnetScripts/
- The bin/ directory contains all of the normally installed scripts
- The misc/ directory contains scripts that don't have any other place
- The test/ directory contains the unit tests
- The contrib/ directory contains other useful stuff not written by or directly for this project
- The build/ directory contains build scripts, and other things useful for building the project
- The deb/ directory contains the base files for the debs
- The util/ directory contains utilities that are not normally installed scripts.

### Requirements

The scripts requires HUGnetLib/src/php to be installed in the php include path as HUGnetLib/.
All of the dependencies for HUGnetLib must be installed.

- PHP 5.3 or 5.4 CLI

## Testing

There is currently no unit testing for this project.  This is one of the items on the todo list


## Deploying

### Ubuntu
Currently there are only build scripts for building .deb files for Ubuntu.  They are
created by running 'ant deb'.  The debs will be in the ./rel directory.


## Troubleshooting & Useful Tools

Most of the code actually resides in HUGnetLib.  These scripts are just a command line
front end for HUGnetLib.

## Contributing Changes

_All commit messages need to reference bugs in the Mantis bug tracker (see below)_

Changes can be contributed by either:

1. Using git to create patches and emailing them to patches@hugllc.com
2. Creating another github repository to make your changes to and submitting pull requests.

## Git Checkins
All git checkins MUST REFERENCE A BUG in Mantis.  This can be done in a number of ways.
The commit message should contain one of the following forms:

- bug #XXXX
- fixed #XXXX
- fixes #XXXX

## Filing Bug Reports
The bug tracker for this project is at http://dev.hugllc.com/bugs/ .  If you want an
account on that site, please email prices@hugllc.com.

## License
This is released under the GNU GPL V3.  You can find the complete text in the
LICENSE file, or at http://opensource.org/licenses/gpl-3.0.html