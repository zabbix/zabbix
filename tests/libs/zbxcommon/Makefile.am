noinst_PROGRAMS = calculate_item_nextcheck

calculate_item_nextcheck_SOURCES = \
	calculate_item_nextcheck.c \
	../../zbxmocktest.h

calculate_item_nextcheck_LDADD = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/tests/libzbxmockdata.a

calculate_item_nextcheck_CFLAGS = -I@top_srcdir@/tests
