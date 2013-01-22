#!/bin/bash
#
#       /etc/rc.d/init.d/zabbix_server
#
# Starts the zabbix_server daemon
#
# chkconfig: - 95 5
# description: Zabbix Monitoring Server
# processname: zabbix_server
# pidfile: /tmp/zabbix_server.pid

# Modified for Zabbix 2.0.0
# May 2012, Zabbix SIA

# Source function library.

. /etc/init.d/functions

RETVAL=0
prog="Zabbix Server"
ZABBIX_BIN="/usr/local/sbin/zabbix_server"

if [ ! -x ${ZABBIX_BIN} ] ; then
        echo -n "${ZABBIX_BIN} not installed! "
        # Tell the user this has skipped
        exit 5
fi

start() {
        echo -n $"Starting $prog: "
        daemon $ZABBIX_BIN
        RETVAL=$?
        [ $RETVAL -eq 0 ] && touch /var/lock/subsys/zabbix_server
        echo
}

stop() {
        echo -n $"Stopping $prog: "
        killproc $ZABBIX_BIN
        RETVAL=$?
        [ $RETVAL -eq 0 ] && rm -f /var/lock/subsys/zabbix_server
        echo
}

case "$1" in
  start)
        start
        ;;
  stop)
        stop
        ;;
  reload|restart)
        stop
        sleep 10
        start
        RETVAL=$?
        ;;
  condrestart)
        if [ -f /var/lock/subsys/zabbix_server ]; then
            stop
            start
        fi
        ;;
  status)
        status $ZABBIX_BIN
        RETVAL=$?
        ;;
  *)
        echo $"Usage: $0 {condrestart|start|stop|restart|reload|status}"
        exit 1
esac

exit $RETVAL
