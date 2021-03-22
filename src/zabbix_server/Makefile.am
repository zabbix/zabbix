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
	preprocessor \
	lld

sbin_PROGRAMS = zabbix_server

noinst_LIBRARIES = libzbxserver.a

libzbxserver_a_SOURCES = \
	actions.c \
	actions.h \
	events.c \
	events.h \
	operations.c \
	operations.h \
	postinit.c \
	postinit.h

libzbxserver_a_CFLAGS = \
	-DZABBIX_DAEMON \
	-I$(top_srcdir)/src/libs/zbxdbcache

zabbix_server_SOURCES = server.c

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
	$(top_builddir)/src/libs/zbxembed/libzbxembed.a \
	poller/libzbxpoller_server.a \
	lld/libzbxlld.a \
	libzbxserver.a \
	$(top_builddir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxserver/libzbxserver.a \
	$(top_builddir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_builddir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_builddir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxself/libzbxself.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_builddir)/src/libs/zbxsys/libzbxsys.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_builddir)/src/libs/zbxjson/libzbxjson.a \
	$(top_builddir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_builddir)/src/libs/zbxexec/libzbxexec.a \
	$(top_builddir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_builddir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_builddir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_builddir)/src/libs/zbxdb/libzbxdb.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_builddir)/src/libs/zbxdiag/libzbxdiag_server.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_builddir)/src/libs/zbxserver/libzbxserver_server.a \
	$(top_builddir)/src/libs/zbxvault/libzbxvault.a

if HAVE_IPMI
zabbix_server_LDADD += ipmi/libipmi.a
endif

zabbix_server_LDADD += $(SERVER_LIBS)

zabbix_server_LDFLAGS = $(SERVER_LDFLAGS)

zabbix_server_CFLAGS = \
	-DDEFAULT_SSL_CERT_LOCATION="\"$(CURL_SSL_CERT_LOCATION)\"" \
	-DDEFAULT_SSL_KEY_LOCATION="\"$(CURL_SSL_KEY_LOCATION)\"" \
	-DDEFAULT_CONFIG_FILE="\"$(SERVER_CONFIG_FILE)\"" \
	-DDEFAULT_EXTERNAL_SCRIPTS_PATH="\"$(EXTERNAL_SCRIPTS_PATH)\"" \
	-DDEFAULT_ALERT_SCRIPTS_PATH="\"$(ALERT_SCRIPTS_PATH)\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"$(LOAD_MODULE_PATH)\""

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(SERVER_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(EXTERNAL_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(ALERT_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(SERVER_CONFIG_FILE)" || cp "../../conf/zabbix_server.conf" "$(DESTDIR)$(SERVER_CONFIG_FILE)"
