if SERVER
noinst_PROGRAMS = parse_cfg_file

parse_cfg_file_SOURCES = \
	parse_cfg_file.c \
	../../zbxmocktest.h

parse_cfg_file_LDADD = \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a

parse_cfg_file_LDADD += \
	@SERVER_LIBS@ \
	$(CMOCKA_LIBS) \
	$(YAML_LIBS)

parse_cfg_file_LDFLAGS = \
	@SERVER_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

parse_cfg_file_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)
endif
