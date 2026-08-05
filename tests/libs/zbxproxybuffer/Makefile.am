include ../Makefile.include

if PROXY
noinst_PROGRAMS = \
	zbx_pb_discovery_close \
	zbx_pb_parse_mode \
	zbx_pb_discovery_write_host \
	zbx_pb_autoreg_write_host \
	zbx_pb_create_error \
	zbx_pb_autoreg_check_age \
	zbx_pb_discovery_check_age \
	zbx_pb_history_check_age \
	zbx_pb_history_write_value \
	zbx_pb_history_write_meta_value \
	zbx_pb_history_get_unsent_num \
	zbx_pb_history_get_rows \
	zbx_pb_free_space

# All proxybuffer tests use the same library set and LDFLAGS.
#
# PROXY_GROUPED_LIBS (tests/libs/grouped-libs.mk.inc) links libzbxproxybuffer.a
# and the mock archives inside a -Wl,--start-group/--end-group, so the linker
# can resolve their cyclic references (libzbxproxybuffer.a calls zbx_db_*()/
# exit(), which the mock archives wrap) regardless of archive order - the same
# pattern used by tests/libs/zbxcacheconfig/Makefile.am's CACHE_LIBS.

PROXYBUFFER_LIBS = \
	$(PROXY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

# AM_LDFLAGS applies to all targets in this file; per-target *_LDFLAGS would
# also work but using AM_LDFLAGS avoids automake's "PB has no canonical name"
# warning that fires when a FOO_LDFLAGS variable has no matching target FOO.
#
# These duplicate part of the top-level COMMON_WRAP_FUNCS (Makefile.am) that
# "make tests_build" injects via LDFLAGS when recursing into tests/ - that
# injection is invisible to "make" invoked directly in this directory, so it
# is repeated here to keep both build paths behaviorally identical (a direct
# local build must not silently link the real zbx_db_*()/exit() instead of
# the mocks). Kept to what this library can actually reach, not copied
# wholesale from the global list:
#   - zbx_db_select/select_n/execute/begin/commit: called directly by
#     proxybuffer.c/pb_history.c (pb_get_rows_db, pb_check_unsent_rows,
#     pb_set_lastid, pb_flush, and the write_host/close begin+commit loops).
#     zbx_db_vselect and zbx_db_execute_multiple_query have no call site
#     anywhere in this library and were dropped.
#   - exit: not called directly, but zbx_malloc()'s built-in OOM handler
#     calls it, and every *_open()/pb_discovery_write_row()/
#     pb_history_add_value() memory-mode path uses zbx_malloc(); this is the
#     same safety net tests/zbxmockexit.c provides tree-wide.
#   - fopen/fclose: not called directly either, but zabbix_log() (used
#     throughout for tracing) can write to a log file; same tree-wide safety
#     net via tests/zbxmockfile.c.
#   - __zbx_shmem_malloc: reached indirectly through the ZBX_SHMEM_FUNC_IMPL
#     macro (pb_malloc()/pb_strdup()), not by name - see zbx_pb_mock_stubs.c
#     for why this one needs a real stub instead of just a safety net.
AM_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=exit \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_select_n \
	-Wl,--wrap=zbx_db_execute \
	-Wl,--wrap=zbx_db_begin \
	-Wl,--wrap=zbx_db_commit \
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

zbx_pb_autoreg_check_age_SOURCES = zbx_pb_autoreg_check_age.c $(STUBS)
zbx_pb_autoreg_check_age_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_autoreg_check_age_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_discovery_check_age_SOURCES = zbx_pb_discovery_check_age.c $(STUBS)
zbx_pb_discovery_check_age_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_discovery_check_age_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_history_check_age_SOURCES = zbx_pb_history_check_age.c $(STUBS)
zbx_pb_history_check_age_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_history_check_age_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_history_write_value_SOURCES = zbx_pb_history_write_value.c $(STUBS)
zbx_pb_history_write_value_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_history_write_value_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_history_write_meta_value_SOURCES = zbx_pb_history_write_meta_value.c $(STUBS)
zbx_pb_history_write_meta_value_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_history_write_meta_value_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_history_get_unsent_num_SOURCES = zbx_pb_history_get_unsent_num.c $(STUBS)
zbx_pb_history_get_unsent_num_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_history_get_unsent_num_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_history_get_rows_SOURCES = zbx_pb_history_get_rows.c $(STUBS)
zbx_pb_history_get_rows_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_history_get_rows_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_free_space_SOURCES = zbx_pb_free_space.c $(STUBS)
zbx_pb_free_space_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_free_space_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@
endif
