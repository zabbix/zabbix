<?php
## Config: Zabbix:conf/zabbix.conf.php
# global $DB, $LLNW_TIMELIMIT;
# $LLNW_TIMELIMIT=<execution timeout>
# require_once dirname(__FILE__).'/../llnw/error-handler.php';

global $LLNW_TIMELIMIT;
$LLNW_TIMELIMIT = is_numeric($LLNW_TIMELIMIT) ? $LLNW_TIMELIMIT : 30;

// Limit request runtimes
set_time_limit($LLNW_TIMELIMIT);
// Register shutdown to force DB connections closed
register_shutdown_function("llnw_shutdown");

function llnw_shutdown() {
    DBend(false); // rollback
    DBclose();
}

