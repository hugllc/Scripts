#!/bin/sh

start() {
        /usr/bin/php-cli /home/hugnet/Scripts/endpoints/updatedb.php
}

stop() {
        kill `cat /tmp/updatedb.php.pid`
}
