include ../Makefile.include

if PROXY
noinst_PROGRAMS = \
	zbx_pb_discovery_close \
	zbx_pb_parse_mode \
	zbx_pb_discovery_write_host \
	zbx_pb_autoreg_write_host \
	zbx_pb_create_error

PROXYBUFFER_LIBS = \
	$(PROXY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

AM_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,-u,__wrap_fopen \
	-Wl,-u,__wrap_fclose \
	-Wl,-u,__wrap_open \
	-Wl,-u,__wrap_close \
	-Wl,--wrap=exit \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_vselect \
	-Wl,--wrap=zbx_db_select_n \
	-Wl,--wrap=zbx_db_execute \
	-Wl,--wrap=zbx_db_begin \
	-Wl,--wrap=zbx_db_commit \
	-Wl,--wrap=zbx_db_execute_multiple_query \
	-Wl,--wrap=__zbx_shmem_malloc

PROXYBUFFER_FLAGS = \
	-I@top_srcdir@/src/libs/zbxproxybuffer \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_pb_mock_stubs.c is compiled once per binary (automake renames objects
# with the per-target prefix due to per-target _CFLAGS).
STUBS = zbx_pb_mock_stubs.c

zbx_pb_discovery_close_SOURCES = zbx_pb_discovery_close.c $(STUBS)
zbx_pb_discovery_close_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_discovery_close_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_parse_mode_SOURCES = zbx_pb_parse_mode.c $(STUBS)
zbx_pb_parse_mode_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_parse_mode_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_discovery_write_host_SOURCES = zbx_pb_discovery_write_host.c $(STUBS)
zbx_pb_discovery_write_host_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_discovery_write_host_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_autoreg_write_host_SOURCES = zbx_pb_autoreg_write_host.c $(STUBS)
zbx_pb_autoreg_write_host_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_autoreg_write_host_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_create_error_SOURCES = zbx_pb_create_error.c $(STUBS)
zbx_pb_create_error_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_create_error_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@
endif
