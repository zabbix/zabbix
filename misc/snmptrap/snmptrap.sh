#!/bin/bash


read hostname
read ip
read uptime
read oid
read address
read community
read enterprise

oid=`echo $oid|cut -f2 -d' '`
address=`echo $address|cut -f2 -d' '`
community=`echo $community|cut -f2 -d' '`
enterprise=`echo $enterprise|cut -f2 -d' '`

oid=`echo $oid|cut -f11 -d'.'`
community=`echo $community|cut -f2 -d'"'`

str="$hostname $address $community $enterprise $oid"

#echo $oid >>/tmp/log
#echo $address >>/tmp/log
#echo $community >>/tmp/log
#echo $enterprise >>/tmp/log

>/tmp/log
echo $str >>/tmp/log
