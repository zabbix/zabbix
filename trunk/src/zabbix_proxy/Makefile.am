## Process this file with automake to produce Makefile.in

SUBDIRS = \
	heart \
	housekeeper \
	proxyconfig \
	datasender \
	taskmanager

sbin_PROGRAMS = zabbix_proxy

zabbix_proxy_SOURCES = \
	servercomms.c servercomms.h \
	events.c \
	preprocessing.c \
	proxy.c

if PROXY_IPCSERVICE
LIBIPCSERVICE = $(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a
endif

zabbix_proxy_LDADD = \
	heart/libzbxheart.a \
	$(top_srcdir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	proxyconfig/libzbxproxyconfig.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/snmptrapper/libzbxsnmptrapper.a \
	$(top_srcdir)/src/zabbix_server/odbc/libzbxodbc.a \
	datasender/libzbxdatasender.a \
	taskmanager/libzbxtaskmanager.a \
	$(top_srcdir)/src/zabbix_server/selfmon/libzbxselfmon.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	${LIBIPCSERVICE} \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a

if PROXY_IPCSERVICE
zabbix_proxy_LDADD += $(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a
endif

zabbix_proxy_LDADD += $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a

if HAVE_IPMI
zabbix_proxy_LDADD += $(top_srcdir)/src/zabbix_server/ipmi/libipmi.a
endif

zabbix_proxy_LDADD += @PROXY_LIBS@

zabbix_proxy_LDFLAGS = @PROXY_LDFLAGS@

zabbix_proxy_CFLAGS = \
	-DZABBIX_DAEMON \
	-DDEFAULT_SSL_CERT_LOCATION="\"@CURL_SSL_CERT_LOCATION@\"" \
	-DDEFAULT_SSL_KEY_LOCATION="\"@CURL_SSL_KEY_LOCATION@\"" \
	-DDEFAULT_CONFIG_FILE="\"@PROXY_CONFIG_FILE@\"" \
	-DDEFAULT_EXTERNAL_SCRIPTS_PATH="\"@EXTERNAL_SCRIPTS_PATH@\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"@LOAD_MODULE_PATH@\""

install-data-hook:
	$(MKDIR_P) "@PROXY_CONFIG_FILE@.d"
	$(MKDIR_P) "@EXTERNAL_SCRIPTS_PATH@"
	$(MKDIR_P) "@LOAD_MODULE_PATH@"
	test -f "@PROXY_CONFIG_FILE@" || cp "../../conf/zabbix_proxy.conf" "@PROXY_CONFIG_FILE@"
