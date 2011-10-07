#!/bin/sh

# This puts us in the directory of this script
cd $(dirname $0)


if [[ -d HUGnetLib ]]; then
   cd HUGnetLib; git reset --hard; git pull
else
   git clone git://git.hugllc.com/HUGnet/HUGnetLib
fi
