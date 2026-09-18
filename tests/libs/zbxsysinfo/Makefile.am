include ../Makefile.include

SUBDIRS = \
	common \
	agent \
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

SYSINFO_SERVER_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

parse_item_key_LDADD = $(SYSINFO_SERVER_LIBS)

if HAVE_IPMI
parse_item_key_LDADD += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

parse_item_key_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

parse_item_key_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

parse_item_key_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

check_service_test_LDADD = \
	$(SYSINFO_SERVER_LIBS)

check_service_test_LDADD += @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
check_service_test_LDFLAGS = @SERVER_LDFLAGS@ \
	-Wl,--wrap=tcp_expect $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
check_service_test_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif

if AGENT
AGENT_LIB_FILES = \
	$(AGENT_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_execute_agent_check_SOURCES = \
	zbx_execute_agent_check.c \
	../../zbxmocktest.h

zbx_execute_agent_check_LDADD = $(AGENT_LIB_FILES) \
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

zbx_execute_agent_check_http_LDADD = $(AGENT_LIB_FILES)

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

check_key_access_rules_LDADD = $(AGENT_LIB_FILES) \
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

check_get_fqdn_LDADD = $(AGENT_LIB_FILES) \
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
