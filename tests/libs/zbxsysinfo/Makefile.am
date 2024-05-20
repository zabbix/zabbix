SUBDIRS = \
	common \
	@ARCH@

if SERVER
SERVER_tests = \
	zbx_parse_item_key \
	zbx_check_service_default_addr
endif

if AGENT
AGENT_tests = \
	zbx_execute_agent_check \
	zbx_execute_agent_check_http \
	zbx_check_key_access_rules \
	zbx_parse_item_key_check_get_fqdn
endif

noinst_PROGRAMS = $(SERVER_tests) $(AGENT_tests)

if SERVER
zbx_parse_item_key_SOURCES = \
	zbx_parse_item_key.c \
	../../zbxmocktest.h

zbx_check_service_default_addr_SOURCES = \
	../../../src/libs/zbxsysinfo/simple/simple.c \
	zbx_check_service_default_addr.c \
	../../zbxmocktest.h \
	../../zbxmockutil.c \
	../../zbxmockexit.c \
	../../zbxmockfile.c \
	../../zbxmocklog.c \
	../../zbxmockdir.c

zbx_parse_item_key_LDADD = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

if HAVE_IPMI
zbx_parse_item_key_LDADD += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

zbx_parse_item_key_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

zbx_parse_item_key_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_parse_item_key_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_check_service_default_addr_LDADD = \
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
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
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

zbx_check_service_default_addr_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
zbx_check_service_default_addr_LDFLAGS = @SERVER_LDFLAGS@ \
	-Wl,--wrap=tcp_expect $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
zbx_check_service_default_addr_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif

if AGENT
COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
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
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
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
	-Wl,--wrap=vfs_dev_discovery \
	-Wl,--wrap=vfs_dev_read \
	-Wl,--wrap=vfs_dev_write \
	-Wl,--wrap=vfs_dir_count \
	-Wl,--wrap=vfs_dir_get \
	-Wl,--wrap=vfs_dir_size \
	-Wl,--wrap=vfs_dir_cksum \
	-Wl,--wrap=vfs_file_contents \
	-Wl,--wrap=vfs_file_exists \
	-Wl,--wrap=vfs_file_get \
	-Wl,--wrap=vfs_file_md5sum \
	-Wl,--wrap=vfs_file_owner \
	-Wl,--wrap=vfs_file_permissions \
	-Wl,--wrap=vfs_file_regexp \
	-Wl,--wrap=vfs_file_regmatch \
	-Wl,--wrap=vfs_file_size \
	-Wl,--wrap=vfs_file_time \
	-Wl,--wrap=vfs_fs_discovery \
	-Wl,--wrap=vfs_fs_get \
	-Wl,--wrap=vfs_fs_inode \
	-Wl,--wrap=vfs_fs_size \
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

# zbx_check_key_access_rules

zbx_check_key_access_rules_SOURCES = \
	zbx_check_key_access_rules.c \
	../../zbxmocktest.h

zbx_check_key_access_rules_LDADD = $(COMMON_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

zbx_check_key_access_rules_LDADD += @AGENT_LIBS@

zbx_check_key_access_rules_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_check_key_access_rules_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_parse_item_key_check_get_fqdn

zbx_parse_item_key_check_get_fqdn_SOURCES = \
	zbx_parse_item_key_check_get_fqdn.c \
	../../zbxmocktest.h

zbx_parse_item_key_check_get_fqdn_LDADD = $(COMMON_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

zbx_parse_item_key_check_get_fqdn_LDADD += @AGENT_LIBS@

zbx_parse_item_key_check_get_fqdn_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_parse_item_key_check_get_fqdn_WRAP_FUNCS = \
	-Wl,--wrap=gethostname \
	-Wl,--wrap=getaddrinfo \
	-Wl,--wrap=freeaddrinfo

zbx_parse_item_key_check_get_fqdn_CFLAGS = \
	$(zbx_parse_item_key_check_get_fqdn_WRAP_FUNCS) \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) $(YAML_CFLAGS)

endif
