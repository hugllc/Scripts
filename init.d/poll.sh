#!/bin/sh

start() {
	/usr/bin/php-cli /home/hugnet/Scripts/endpoint/poll.php 
}

stop() {
	kill `cat /tmp/poll.php.pid`
}
