include ../../Makefile.include

if AGENT
AGENT_tests = \
	system_localtime \
	web_page_get \
	vfs_file_exists \
	zbx_ip_reverse
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(AGENT_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

system_localtime_SOURCES = \
	system_localtime.c \
	$(COMMON_SRC_FILES)

system_localtime_WRAP_FUNCS = \
	-Wl,--wrap=time \
	-Wl,--wrap=gettimeofday

system_localtime_LDADD = \
	$(COMMON_LIB_FILES)

system_localtime_LDADD += @AGENT_LIBS@

system_localtime_LDFLAGS = @AGENT_LDFLAGS@ $(system_localtime_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	$(TLS_LDFLAGS)

system_localtime_CFLAGS = $(COMMON_COMPILER_FLAGS)

web_page_get_SOURCES = \
	web_page_get.c \
	$(COMMON_SRC_FILES)

if HAVE_LIBCURL
web_page_get_WRAP_FUNCS = \
	-Wl,--wrap=curl_easy_init \
	-Wl,--wrap=curl_easy_setopt \
	-Wl,--wrap=curl_easy_perform \
	-Wl,--wrap=curl_easy_cleanup
else
web_page_get_WRAP_FUNCS = \
	-Wl,--wrap=zbx_tcp_connect \
	-Wl,--wrap=zbx_tcp_send_ext \
	-Wl,--wrap=zbx_tcp_recv_raw_ext \
	-Wl,--wrap=zbx_tcp_close
endif

web_page_get_LDADD = \
	$(COMMON_LIB_FILES)

web_page_get_LDADD += @AGENT_LIBS@

web_page_get_LDFLAGS = @AGENT_LDFLAGS@ $(web_page_get_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

web_page_get_CFLAGS = $(COMMON_COMPILER_FLAGS)

vfs_file_exists_SOURCES = \
	vfs_file_exists.c \
	$(COMMON_SRC_FILES)

vfs_file_exists_LDADD = \
	$(COMMON_LIB_FILES)

vfs_file_exists_LDADD += @AGENT_LIBS@

vfs_file_exists_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

vfs_file_exists_CFLAGS = $(COMMON_COMPILER_FLAGS) -I$(top_srcdir)/src/libs/zbxsysinfo/common/

zbx_ip_reverse_SOURCES = \
	zbx_ip_reverse.c \
	$(COMMON_SRC_FILES)

zbx_ip_reverse_LDADD = \
	$(COMMON_LIB_FILES)

zbx_ip_reverse_LDADD += @SERVER_LIBS@

zbx_ip_reverse_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ip_reverse_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
