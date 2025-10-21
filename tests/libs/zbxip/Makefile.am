BINARIES_tests = \
	zbx_join_hostport

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

IP_LIBS = \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
zbx_iprange_ip2str_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_ip2str_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_join_hostport

zbx_join_hostport_SOURCES = \
	zbx_join_hostport.c \
	$(COMMON_SRC_FILES)

zbx_join_hostport_LDADD = \
	$(IP_LIBS)

zbx_join_hostport_LDADD += @SERVER_LIBS@

zbx_join_hostport_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_join_hostport_CFLAGS = $(IP_COMPILER_FLAGS)
