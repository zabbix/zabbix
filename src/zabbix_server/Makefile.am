## Process this file with automake to produce Makefile.in

SUBDIRS = \
	alerter \
	dbsyncer \
	dbconfig \
	discoverer \
	housekeeper \
	httppoller \
	pinger \
	poller \
	snmptrapper \
	timer \
	trapper \
	escalator \
	proxypoller \
	selfmon \
	vmware \
	taskmanager \
	ipmi \
	odbc \
	scripts \
	preprocessor

sbin_PROGRAMS = zabbix_server

noinst_LIBRARIES = libzbxserver.a

libzbxserver_a_SOURCES = \
	actions.c actions.h \
	operations.c operations.h \
	events.c events.h \
	postinit.c postinit.h

libzbxserver_a_CFLAGS = \
	-DZABBIX_DAEMON \
	-I@top_srcdir@/src/libs/zbxdbcache

zabbix_server_SOURCES = server.c

if ZBXCUNIT
nodist_zabbix_server_SOURCES = ../libs/zbxcunit/zbxcunit.c ../libs/zbxcunit/zbxcunit.h
endif

zabbix_server_LDADD = \
	alerter/libzbxalerter.a \
	dbsyncer/libzbxdbsyncer.a \
	dbconfig/libzbxdbconfig.a \
	discoverer/libzbxdiscoverer.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	housekeeper/libzbxhousekeeper.a \
	timer/libzbxtimer.a \
	trapper/libzbxtrapper.a \
	snmptrapper/libzbxsnmptrapper.a \
	httppoller/libzbxhttppoller.a \
	escalator/libzbxescalator.a \
	proxypoller/libzbxproxypoller.a \
	selfmon/libzbxselfmon.a \
	vmware/libzbxvmware.a \
	taskmanager/libzbxtaskmanager.a \
	odbc/libzbxodbc.a \
	scripts/libzbxscripts.a \
	preprocessor/libpreprocessor.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	libzbxserver.a

if HAVE_IPMI
zabbix_server_LDADD += ipmi/libipmi.a
endif

zabbix_server_LDADD += @SERVER_LIBS@ $(CUNIT_LDADD)

zabbix_server_LDFLAGS = @SERVER_LDFLAGS@

zabbix_server_CFLAGS = \
	-DDEFAULT_SSL_CERT_LOCATION="\"@CURL_SSL_CERT_LOCATION@\"" \
	-DDEFAULT_SSL_KEY_LOCATION="\"@CURL_SSL_KEY_LOCATION@\"" \
	-DDEFAULT_CONFIG_FILE="\"@SERVER_CONFIG_FILE@\"" \
	-DDEFAULT_EXTERNAL_SCRIPTS_PATH="\"@EXTERNAL_SCRIPTS_PATH@\"" \
	-DDEFAULT_ALERT_SCRIPTS_PATH="\"@ALERT_SCRIPTS_PATH@\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"@LOAD_MODULE_PATH@\""

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)@SERVER_CONFIG_FILE@.d"
	$(MKDIR_P) "$(DESTDIR)@EXTERNAL_SCRIPTS_PATH@"
	$(MKDIR_P) "$(DESTDIR)@ALERT_SCRIPTS_PATH@"
	$(MKDIR_P) "$(DESTDIR)@LOAD_MODULE_PATH@"
	test -f "$(DESTDIR)@SERVER_CONFIG_FILE@" || cp "../../conf/zabbix_server.conf" "$(DESTDIR)@SERVER_CONFIG_FILE@"
