include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_trends_parse_range \
	zbx_baseline_get_data

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(SERVER_GROUPED_LIBS) \
	$(CMOCKA_LIBS) $(YAML_LIBS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_trends_parse_range_SOURCES = \
	zbx_trends_parse_range.c \
	$(COMMON_SRC_FILES)

zbx_trends_parse_range_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trends_parse_range_LDADD += @SERVER_LIBS@

zbx_trends_parse_range_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_is_null \
	-Wl,--wrap=zbx_recalc_time_period 

zbx_trends_parse_range_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_baseline_get_data

zbx_baseline_get_data_SOURCES = \
	zbx_baseline_get_data.c \
	$(COMMON_SRC_FILES)

zbx_baseline_get_data_LDADD = \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(COMMON_LIB_FILES)

zbx_baseline_get_data_LDADD += @SERVER_LIBS@

zbx_baseline_get_data_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_is_null \
	-Wl,--wrap=zbx_trends_get_avg \
	-Wl,--wrap=zbx_recalc_time_period \
	-Wl,--wrap=zbx_history_get_trends_flags
	

zbx_baseline_get_data_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
