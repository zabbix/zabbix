LIBS=../src/zabbix_server/escalator/libzbxescalator.a \
	../src/zabbix_server/scripts/libzbxscripts.a \
	../src/zabbix_server/poller/libzbxpoller.a \
	../src/zabbix_server/taskmanager/libzbxtaskmanager.a \
	../src/libs/zbxsysinfo/libzbxserversysinfo.a \
	../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	../src/libs/zbxlog/libzbxlog.a \
	../src/libs/zbxserver/libzbxserver.a \
	../src/libs/zbxdbcache/libzbxdbcache.a \
	../src/libs/zbxmemory/libzbxmemory.a \
	../src/libs/zbxregexp/libzbxregexp.a \
	../src/libs/zbxself/libzbxself.a \
	../src/libs/zbxnix/libzbxnix.a \
	../src/libs/zbxalgo/libzbxalgo.a \
	../src/libs/zbxsys/libzbxsys.a \
	../src/libs/zbxconf/libzbxconf.a \
	../src/libs/zbxmedia/libzbxmedia.a \
	../src/libs/zbxcommon/libzbxcommon.a \
	../src/libs/zbxcrypto/libzbxcrypto.a \
	../src/libs/zbxcomms/libzbxcomms.a \
	../src/libs/zbxcommshigh/libzbxcommshigh.a \
	../src/libs/zbxjson/libzbxjson.a \
	../src/libs/zbxhttp/libzbxhttp.a \
	../src/libs/zbxexec/libzbxexec.a \
	../src/libs/zbxicmpping/libzbxicmpping.a \
	../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	../src/libs/zbxdbhigh/libzbxdbhigh.a \
	../src/libs/zbxdb/libzbxdb.a \
	../src/libs/zbxmodules/libzbxmodules.a \
	../src/libs/zbxtasks/libzbxtasks.a \
	../src/libs/zbxalgo/libzbxalgo.a \
	../src/libs/zbxcommon/libzbxcommon.a \
	../src/libs/zbxlog/libzbxlog.a \
	../src/libs/zbxsysinfo/libzbxserversysinfo.a \
	../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	../src/libs/zbxlog/libzbxlog.a \
	../src/libs/zbxserver/libzbxserver.a \
	../src/libs/zbxmemory/libzbxmemory.a \
	../src/libs/zbxregexp/libzbxregexp.a \
	../src/libs/zbxself/libzbxself.a \
	../src/libs/zbxnix/libzbxnix.a \
	../src/libs/zbxalgo/libzbxalgo.a \
	../src/libs/zbxsys/libzbxsys.a \
	../src/libs/zbxconf/libzbxconf.a \
	../src/libs/zbxmedia/libzbxmedia.a \
	../src/libs/zbxcommon/libzbxcommon.a \
	../src/libs/zbxcrypto/libzbxcrypto.a \
	../src/libs/zbxcomms/libzbxcomms.a \
	../src/libs/zbxcommshigh/libzbxcommshigh.a \
	../src/libs/zbxjson/libzbxjson.a \
	../src/libs/zbxhttp/libzbxhttp.a \
	../src/libs/zbxexec/libzbxexec.a \
	../src/libs/zbxicmpping/libzbxicmpping.a \
	../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	../src/libs/zbxdbhigh/libzbxdbhigh.a \
	../src/libs/zbxdb/libzbxdb.a \
	../src/libs/zbxmodules/libzbxmodules.a \
	../src/libs/zbxtasks/libzbxtasks.a
INC=-I../../include -I../libs/zbxdbcache -I../zabbix_server/scripts -I../zabbix_server/escalator -I../zabbix_server -I/usr/include/mysql/

#C=	unresolved.c \
	#data.c \
	#db_simulation.c \
	#../src/zabbix_server/actions.c \
	#../src/zabbix_server/events.c \
	#../src/zabbix_server/operations.c \
	#../src/libs/zbxdb/db.c \
	#main.c
	
C=	unresolved.c \
	data.c \
	db_simulation.c \
	main.c

WRAP_FUNCS=--wrap=zbx_db_vselect,--wrap=zbx_db_fetch,--wrap=__zbx_DBexecute,--wrap=DBbegin,--wrap=DBcommit,--wrap=DBexecute_multiple_query,--wrap=DBfree_result,--wrap=DCconfig_lock_triggers_by_triggerids,--wrap=DCconfig_unlock_triggers
main:
	gcc $(C) $(LIBS) $(INC) -lm -ldl -lresolv -lpcreposix -lpcre -lmysqlclient -lcurl -lxml2 -lssh2 -lcmocka  -Wl,$(WRAP_FUNCS) && ./a.out
