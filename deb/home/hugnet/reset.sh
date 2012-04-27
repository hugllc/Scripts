#!/bin/bash

GPIODIR=/sys/class/gpio
BASEDIR=${GPIODIR}/gpio168
VALUE=${BASEDIR}/value
DIRECTION=${BASEDIR}/direction
EXPORT=${GPIODIR}/export

if [ ! -d "${BASEDIR}" ]; then
        echo 168 > ${EXPORT}
fi

if [ `grep -c out ${DIRECTION}` -eq 0 ]; then
        echo "out" > ${DIRECTION}
fi
echo 1 > ${VALUE}
sleep 1
echo 0 > ${VALUE}
sleep 1
echo 1 > ${VALUE}

