SUBDIRS = \
	common \
	@ARCH@

if SERVER
SERVER_tests = \
	parse_item_key \
	check_service_test
endif

if AGENT
AGENT_tests = \
	zbx_execute_agent_check \
	zbx_execute_agent_check_http \
	check_key_access_rules \
	check_get_fqdn
endif

noinst_PROGRAMS = $(SERVER_tests) $(AGENT_tests)

if SERVER
parse_item_key_SOURCES = \
	parse_item_key.c \
	../../zbxmocktest.h

check_service_test_SOURCES = \
	../../../src/libs/zbxsysinfo/simple/simple.c \
	check_service_test.c \
	../../zbxmocktest.h \
	../../zbxmockutil.c \
	../../zbxmockexit.c \
	../../zbxmockfile.c \
	../../zbxmocklog.c \
	../../zbxmockdir.c

parse_item_key_LDADD = \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/libs/zbxscripts/libzbxscripts.a \
	$(top_srcdir)/src/libs/zbxalerter/libzbxalerter.a \
	$(top_srcdir)/src/libs/zbxdbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/dbconfig/libzbxdbconfig.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/timer/libzbxtimer.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/zabbix_server/proxypoller/libzbxproxypoller.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_srcdir)/src/libs/zbxexpression/libzbxexpression.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

if HAVE_IPMI
parse_item_key_LDADD += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

parse_item_key_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

parse_item_key_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

parse_item_key_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

check_service_test_LDADD = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

check_service_test_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
check_service_test_LDFLAGS = @SERVER_LDFLAGS@ \
	-Wl,--wrap=tcp_expect $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
check_service_test_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif

if AGENT
COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

zbx_execute_agent_check_SOURCES = \
	zbx_execute_agent_check.c \
	../../zbxmocktest.h

zbx_execute_agent_check_LDADD = $(COMMON_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

zbx_execute_agent_check_WRAP_FUNCS = \
	-Wl,--wrap=system_localtime \
	-Wl,--wrap=vfs_file_size \
	-Wl,--wrap=vfs_file_time \
	-Wl,--wrap=vfs_file_exists \
	-Wl,--wrap=vfs_file_contents \
	-Wl,--wrap=vfs_file_regmatch \
	-Wl,--wrap=vfs_file_md5sum \
	-Wl,--wrap=vfs_file_cksum \
	-Wl,--wrap=vfs_dir_size \
	-Wl,--wrap=net_dns \
	-Wl,--wrap=net_dns_record \
	-Wl,--wrap=net_dns_perf \
	-Wl,--wrap=net_tcp_port \
	-Wl,--wrap=system_users_num

zbx_execute_agent_check_LDADD += @AGENT_LIBS@

zbx_execute_agent_check_LDFLAGS = @AGENT_LDFLAGS@ $(zbx_execute_agent_check_WRAP_FUNCS) $(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

zbx_execute_agent_check_CFLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_execute_agent_check_http

zbx_execute_agent_check_http_SOURCES = \
	zbx_execute_agent_check_http.c \
	../../zbxmocktest.h

zbx_execute_agent_check_http_LDADD = $(COMMON_LIB_FILES)

zbx_execute_agent_check_http_WRAP_FUNCS = \
	-Wl,--wrap=web_page_get \
	-Wl,--wrap=web_page_perf \
	-Wl,--wrap=web_page_regexp

zbx_execute_agent_check_http_LDADD += @AGENT_LIBS@

zbx_execute_agent_check_http_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_execute_agent_check_http_CFLAGS = $(zbx_execute_agent_check_http_WRAP_FUNCS) -I@top_srcdir@/tests $(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

# check_key_access_rules

check_key_access_rules_SOURCES = \
	check_key_access_rules.c \
	../../zbxmocktest.h

check_key_access_rules_LDADD = $(COMMON_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

check_key_access_rules_LDADD += @AGENT_LIBS@

check_key_access_rules_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

check_key_access_rules_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# check_get_fqdn

check_get_fqdn_SOURCES = \
	check_get_fqdn.c \
	../../zbxmocktest.h

check_get_fqdn_LDADD = $(COMMON_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

check_get_fqdn_LDADD += @AGENT_LIBS@

check_get_fqdn_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

check_get_fqdn_WRAP_FUNCS = \
	-Wl,--wrap=gethostname \
	-Wl,--wrap=getaddrinfo \
	-Wl,--wrap=freeaddrinfo

check_get_fqdn_CFLAGS = $(check_get_fqdn_WRAP_FUNCS) -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

endif
