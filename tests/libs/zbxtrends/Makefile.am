if SERVER
SERVER_tests = \
	zbx_trends_parse_range
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

zbx_trends_parse_range_SOURCES = \
	zbx_trends_parse_range.c \
	$(COMMON_SRC_FILES)

zbx_trends_parse_range_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trends_parse_range_LDADD += @SERVER_LIBS@

zbx_trends_parse_range_LDFLAGS = @SERVER_LDFLAGS@ 

zbx_trends_parse_range_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-Wl,--wrap=DBfetch \
	-Wl,--wrap=DBselect \
	-Wl,--wrap=DBis_null
	

endif
