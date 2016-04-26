#!/bin/sh

# REQUIRE: DAEMON
# PROVIDE: zabbix_server

. /etc/rc.subr

name="zabbix_server"
rcvar=`set_rcvar`
command="${prefix:-"/usr/local"}/sbin/${name}"

load_rc_config ${name}
run_rc_command "$1"
