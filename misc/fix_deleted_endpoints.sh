#!/bin/sh

echo "INSERT INTO `devices` SELECT NULL , DeviceID, DeviceName, id, HWPartNum, FWPartNum, FWVersion, RawSetup, '', Active, GatewayKey, ControllerKey, ControllerIndex, DeviceLocation, DeviceJob, Driver, PollInterval, ActiveSensors, DeviceGroup, 80, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '15MIN', sensors, params, 0, id FROM `HUGnet`.`devices` WHERE GatewayKey =5" | mysql -p
