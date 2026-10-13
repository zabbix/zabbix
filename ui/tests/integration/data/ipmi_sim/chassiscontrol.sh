#!/bin/sh
#
# Minimal ipmi_sim "chassis_control" hook (from OpenIPMI's lanserv/ipmi_sim_chassiscontrol
# example). Without this hook, ipmi_sim rejects every Chassis Control command with
# "Invalid data field in request", so the standard OpenIPMI "power"/"reset" chassis
# controls (created in lib/chassis.c for any MC advertising chassis support) cannot be
# set. This stub just accepts get/set for power/reset/boot without doing anything real.
#
#  chassiscontrol.sh <device> get [parm [parm ...]]
#  chassiscontrol.sh <device> set [parm val [parm val ...]]

device=$1
if [ "x$device" = "x" ]; then
	echo "No device given"
	exit 1
fi
shift

op=$1
if [ "x$op" = "x" ]; then
	echo "No operation given"
	exit 1
fi
shift

do_get() {
	while [ "x$1" != "x" ]; do
		case $1 in
			power)
				val=0
				;;
			boot)
				val=default
				;;
			*)
				echo "Invalid parameter: $1"
				exit 1
				;;
		esac
		echo "$1:$val"
		shift
	done
}

do_set() {
	while [ "x$1" != "x" ]; do
		parm="$1"
		shift
		if [ "x$1" = "x" ]; then
			echo "No value present for parameter $parm"
			exit 1
		fi
		val="$1"
		shift

		case $parm in
			power|reset)
				;;
			boot)
				case $val in
					none|pxe|cdrom|bios|default)
						;;
					*)
						echo "Invalid boot value: $val"
						exit 1
						;;
				esac
				;;
			identify)
				shift
				;;
			*)
				echo "Invalid parameter: $parm"
				exit 1
				;;
		esac
	done
}

case $op in
	get)
		do_get "$@"
		;;
	set)
		do_set "$@"
		;;
	check)
		exit 1
		;;
	*)
		echo "Unknown operation: $op"
		exit 1
		;;
esac
