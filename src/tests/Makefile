LIBS=../zabbix_server/escalator/libzbxescalator.a \
	../zabbix_server/scripts/libzbxscripts.a \
	../zabbix_server/poller/libzbxpoller.a \
	../libs/zbxsysinfo/libzbxserversysinfo.a \
	../libs/zbxsysinfo/common/libcommonsysinfo.a \
	../libs/zbxsysinfo/simple/libsimplesysinfo.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxserver/libzbxserver.a \
	../libs/zbxdbcache/libzbxdbcache.a \
	../libs/zbxmemory/libzbxmemory.a \
	../libs/zbxregexp/libzbxregexp.a \
	../libs/zbxself/libzbxself.a \
	../libs/zbxnix/libzbxnix.a \
	../libs/zbxalgo/libzbxalgo.a \
	../libs/zbxsys/libzbxsys.a \
	../libs/zbxconf/libzbxconf.a \
	../libs/zbxmedia/libzbxmedia.a \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxcrypto/libzbxcrypto.a \
	../libs/zbxcomms/libzbxcomms.a \
	../libs/zbxcommshigh/libzbxcommshigh.a \
	../libs/zbxjson/libzbxjson.a \
	../libs/zbxhttp/libzbxhttp.a \
	../libs/zbxexec/libzbxexec.a \
	../libs/zbxicmpping/libzbxicmpping.a \
	../libs/zbxdbupgrade/libzbxdbupgrade.a \
	../libs/zbxdbhigh/libzbxdbhigh.a \
	../libs/zbxdb/libzbxdb.a \
	../libs/zbxmodules/libzbxmodules.a \
	../libs/zbxtasks/libzbxtasks.a \
	../libs/zbxalgo/libzbxalgo.a \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxsysinfo/libzbxserversysinfo.a \
	../libs/zbxsysinfo/common/libcommonsysinfo.a \
	../libs/zbxsysinfo/simple/libsimplesysinfo.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxserver/libzbxserver.a \
	../libs/zbxmemory/libzbxmemory.a \
	../libs/zbxregexp/libzbxregexp.a \
	../libs/zbxself/libzbxself.a \
	../libs/zbxnix/libzbxnix.a \
	../libs/zbxalgo/libzbxalgo.a \
	../libs/zbxsys/libzbxsys.a \
	../libs/zbxconf/libzbxconf.a \
	../libs/zbxmedia/libzbxmedia.a \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxcrypto/libzbxcrypto.a \
	../libs/zbxcomms/libzbxcomms.a \
	../libs/zbxcommshigh/libzbxcommshigh.a \
	../libs/zbxjson/libzbxjson.a \
	../libs/zbxhttp/libzbxhttp.a \
	../libs/zbxexec/libzbxexec.a \
	../libs/zbxicmpping/libzbxicmpping.a \
	../libs/zbxdbupgrade/libzbxdbupgrade.a \
	../libs/zbxdbhigh/libzbxdbhigh.a \
	../libs/zbxdb/libzbxdb.a \
	../libs/zbxmodules/libzbxmodules.a \
	../libs/zbxtasks/libzbxtasks.a

INC=-I../../include -I../libs/zbxdbcache -I../zabbix_server/scripts -I../zabbix_server/escalator -I../zabbix_server -I/usr/include/mysql/

C=	unresolved.c \
	test_example.c \
	test_escalations.c \
	../zabbix_server/actions.c \
	../zabbix_server/events.c \
	../zabbix_server/operations.c \
	../libs/zbxdb/db.c

WRAP_FUNCS=--wrap=zbx_vc_get_value_range,--wrap=zbx_db_vselect,--wrap=zbx_db_fetch,--wrap=__zbx_DBexecute,--wrap=DBbegin,--wrap=DBcommit,--wrap=DBexecute_multiple_query,--wrap=DCconfig_get_triggers_by_triggerids,--wrap=DCconfig_get_functions_by_functionids,--wrap=DCconfig_get_items_by_itemids,--wrap=DCconfig_check_trigger_dependencies,--wrap=substitute_simple_macros,--wrap=check_action_condition

main:
	gcc $(C) $(LIBS) $(INC) -lm -ldl -lresolv -lpcreposix -lpcre -lmysqlclient -lcurl -lxml2 -lssh2 -lcmocka -Wl,$(WRAP_FUNCS) && ./a.out
