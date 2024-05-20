include ../Makefile.common

if SERVER
ZLIB_tests = zbx_tcp_recv_ext_zlib
endif

noinst_PROGRAMS = zbx_tcp_recv_ext zbx_tcp_recv_raw_ext $(ZLIB_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMSHIGH_LIBS = \
	$(COMMSHIGH_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_tcp_recv_ext_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_LDADD = \
	$(COMMSHIGH_LIBS)

zbx_tcp_recv_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)

if SERVER
zbx_tcp_recv_ext_zlib_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_zlib_LDADD = \
	$(COMMSHIGH_LIBS)

zbx_tcp_recv_ext_zlib_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_zlib_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_zlib_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
endif

zbx_tcp_recv_raw_ext_SOURCES = \
	zbx_tcp_recv_raw_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_raw_ext_LDADD = \
	$(COMMSHIGH_LIBS)

zbx_tcp_recv_raw_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_raw_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_raw_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
