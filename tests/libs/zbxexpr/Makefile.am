include ../Makefile.include

BINARIES_tests = \
	zbx_function_find \
	parse_key \
	calculate_item_nextcheck \
	calculate_item_nextcheck_unreachable \
	zbx_function_get_param_dyn \
	zbx_token_find \
	zbx_interval_preproc \
	zbx_validate_interval \
	is_double_suffix \
	zbx_user_macro_parse \
	zbx_get_report_nextcheck \
	macro_fmttime \
	macro_func \
	macro_tr \
	zbx_function_param_parse_ext \
	zbx_substitute_macros \
	zbx_substitute_macros1 \
	zbx_function_param_quote \
	zbx_make_hostname \
	zbx_check_hostname \
	zbx_parse_host_key \
	zbx_uint64match_condition \
	zbx_get_function_type \
	zbx_is_discovery_macro \
	zbx_function_validate_parameters \
	zbx_check_time_period \
	zbx_get_agent_item_nextcheck \
	zbx_url_encode_decode \
	zbx_custom_interval_is_scheduling \
	zbx_user_macro_quote_context_dyn

if HAVE_PCRE2
BINARIES_tests += macro_regrepl
endif

if SERVER
BINARIES_tests += zbx_substitute_macros_um
endif

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

EXPR_LIBS = \
	$(EXPR_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

EXPR_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_function_find_SOURCES = \
	zbx_function_find.c \
	$(COMMON_SRC_FILES)

zbx_function_find_LDADD = \
	$(EXPR_LIBS)

zbx_function_find_LDADD += @SERVER_LIBS@

zbx_function_find_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_function_find_CFLAGS = $(EXPR_COMPILER_FLAGS)


parse_key_SOURCES = \
	parse_key.c \
	$(COMMON_SRC_FILES)

parse_key_LDADD = \
	$(EXPR_LIBS)

parse_key_LDADD += @SERVER_LIBS@

parse_key_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

parse_key_CFLAGS = $(EXPR_COMPILER_FLAGS)


calculate_item_nextcheck_SOURCES = \
	calculate_item_nextcheck.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_LDADD = \
	$(EXPR_LIBS)

calculate_item_nextcheck_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

calculate_item_nextcheck_CFLAGS = $(EXPR_COMPILER_FLAGS)

calculate_item_nextcheck_unreachable_SOURCES = \
	calculate_item_nextcheck_unreachable.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_unreachable_LDADD = \
	$(EXPR_LIBS)

calculate_item_nextcheck_unreachable_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_unreachable_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

calculate_item_nextcheck_unreachable_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_function_get_param_dyn_SOURCES = \
	zbx_function_get_param_dyn.c \
	$(COMMON_SRC_FILES)

zbx_function_get_param_dyn_LDADD = \
	$(EXPR_LIBS)

zbx_function_get_param_dyn_LDADD += @SERVER_LIBS@

zbx_function_get_param_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_function_get_param_dyn_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_token_find_SOURCES = \
	zbx_token_find.c \
	$(COMMON_SRC_FILES)

zbx_token_find_LDADD = \
	$(EXPR_LIBS)

zbx_token_find_LDADD += @SERVER_LIBS@

zbx_token_find_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_token_find_CFLAGS = $(EXPR_COMPILER_FLAGS)

zbx_interval_preproc_SOURCES = \
	zbx_interval_preproc.c \
	$(COMMON_SRC_FILES)

zbx_interval_preproc_LDADD = \
	$(EXPR_LIBS)

zbx_interval_preproc_LDADD += @SERVER_LIBS@

zbx_interval_preproc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_interval_preproc_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_validate_interval_SOURCES = \
	zbx_validate_interval.c \
	$(COMMON_SRC_FILES)

zbx_validate_interval_LDADD = \
	$(EXPR_LIBS)

zbx_validate_interval_LDADD += @SERVER_LIBS@

zbx_validate_interval_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_validate_interval_CFLAGS = $(EXPR_COMPILER_FLAGS)


is_double_suffix_SOURCES = \
	is_double_suffix.c \
	$(COMMON_SRC_FILES)

is_double_suffix_LDADD = \
	$(EXPR_LIBS)

is_double_suffix_LDADD += @SERVER_LIBS@

is_double_suffix_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

is_double_suffix_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_user_macro_parse_SOURCES = \
	zbx_user_macro_parse.c \
	$(COMMON_SRC_FILES)

zbx_user_macro_parse_LDADD = \
	$(EXPR_LIBS)

zbx_user_macro_parse_LDADD += @SERVER_LIBS@

zbx_user_macro_parse_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_user_macro_parse_CFLAGS = $(EXPR_COMPILER_FLAGS)


# zbx_get_report_nextcheck

zbx_get_report_nextcheck_SOURCES = \
	zbx_get_report_nextcheck.c \
	$(COMMON_SRC_FILES)

zbx_get_report_nextcheck_LDADD = \
	$(EXPR_LIBS)

zbx_get_report_nextcheck_LDADD += @SERVER_LIBS@

zbx_get_report_nextcheck_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_get_report_nextcheck_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_function_param_parse_ext_SOURCES = \
	zbx_function_param_parse_ext.c \
	$(COMMON_SRC_FILES)

zbx_function_param_parse_ext_LDADD = \
	$(EXPR_LIBS)

zbx_function_param_parse_ext_LDADD += @SERVER_LIBS@

zbx_function_param_parse_ext_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_param_parse_ext_CFLAGS = $(EXPR_COMPILER_FLAGS)


macro_tr_SOURCES = \
	macro_tr.c \
	$(COMMON_SRC_FILES)

macro_tr_LDADD = \
	$(EXPR_LIBS)

macro_tr_LDADD += @SERVER_LIBS@

macro_tr_LDFLAGS = @SERVER_LDFLAGS@

macro_tr_CFLAGS = $(EXPR_COMPILER_FLAGS)

macro_regrepl_SOURCES = \
	macro_regrepl.c \
	$(COMMON_SRC_FILES)

macro_regrepl_LDADD = \
	$(EXPR_LIBS)

macro_regrepl_LDADD += @SERVER_LIBS@

macro_regrepl_LDFLAGS = @SERVER_LDFLAGS@

macro_regrepl_CFLAGS = $(EXPR_COMPILER_FLAGS)


macro_func_SOURCES = \
	macro_func.c \
	$(COMMON_SRC_FILES)

macro_func_LDADD = \
	$(EXPR_LIBS)

macro_func_LDADD += @SERVER_LIBS@

macro_func_LDFLAGS = @SERVER_LDFLAGS@

macro_func_CFLAGS = $(EXPR_COMPILER_FLAGS)


macro_fmttime_SOURCES = \
	macro_fmttime.c \
	$(COMMON_SRC_FILES)

macro_fmttime_LDADD = \
	$(EXPR_LIBS)

macro_fmttime_LDADD += @SERVER_LIBS@

macro_fmttime_LDFLAGS = @SERVER_LDFLAGS@ -Wl,--wrap=time

macro_fmttime_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_substitute_macros_SOURCES = \
	zbx_substitute_macros.c \
	$(COMMON_SRC_FILES)

zbx_substitute_macros_LDADD = \
	$(EXPR_LIBS)

zbx_substitute_macros_LDADD += @SERVER_LIBS@

zbx_substitute_macros_LDFLAGS = @SERVER_LDFLAGS@

zbx_substitute_macros_CFLAGS = $(EXPR_COMPILER_FLAGS)

zbx_substitute_macros1_SOURCES = \
	zbx_substitute_macros1.c \
	$(COMMON_SRC_FILES)

zbx_substitute_macros1_LDADD = \
	$(EXPR_LIBS)

zbx_substitute_macros1_LDADD += @SERVER_LIBS@

zbx_substitute_macros1_LDFLAGS = @SERVER_LDFLAGS@

zbx_substitute_macros1_CFLAGS = $(EXPR_COMPILER_FLAGS)

if SERVER

WRAP_FUNCS = \
	-Wl,--wrap=__zbx_shmem_malloc \
	-Wl,--wrap=__zbx_shmem_realloc \
	-Wl,--wrap=__zbx_shmem_free

zbx_substitute_macros_um_SOURCES = \
	zbx_substitute_macros_um.c \
	../zbxcacheconfig/um_cache_mock.c \
	$(COMMON_SRC_FILES)

zbx_substitute_macros_um_LDADD = \
	$(EXPR_LIBS) \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_builddir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxexport/libzbxexport.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_substitute_macros_um_LDADD += @SERVER_LIBS@

zbx_substitute_macros_um_LDFLAGS = @SERVER_LDFLAGS@ $(WRAP_FUNCS)

zbx_substitute_macros_um_CFLAGS = \
	-I@top_srcdir@/src/libs \
	$(EXPR_COMPILER_FLAGS)

endif

zbx_function_param_quote_SOURCES = \
	zbx_function_param_quote.c \
	$(COMMON_SRC_FILES)

zbx_function_param_quote_LDADD = \
	$(EXPR_LIBS)

zbx_function_param_quote_LDADD += @SERVER_LIBS@

zbx_function_param_quote_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_param_quote_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_make_hostname

zbx_make_hostname_SOURCES = \
	zbx_make_hostname.c \
	$(COMMON_SRC_FILES)

zbx_make_hostname_LDADD = \
	$(EXPR_LIBS)

zbx_make_hostname_LDADD += @SERVER_LIBS@

zbx_make_hostname_LDFLAGS = @SERVER_LDFLAGS@

zbx_make_hostname_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_check_hostname

zbx_check_hostname_SOURCES = \
	zbx_check_hostname.c \
	$(COMMON_SRC_FILES)

zbx_check_hostname_LDADD = \
	$(EXPR_LIBS)

zbx_check_hostname_LDADD += @SERVER_LIBS@

zbx_check_hostname_LDFLAGS = @SERVER_LDFLAGS@

zbx_check_hostname_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_parse_host_key

zbx_parse_host_key_SOURCES = \
	zbx_parse_host_key.c \
	$(COMMON_SRC_FILES)

zbx_parse_host_key_LDADD = \
	$(EXPR_LIBS)

zbx_parse_host_key_LDADD += @SERVER_LIBS@

zbx_parse_host_key_LDFLAGS = @SERVER_LDFLAGS@

zbx_parse_host_key_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_uint64match_condition

zbx_uint64match_condition_SOURCES = \
	zbx_uint64match_condition.c \
	$(COMMON_SRC_FILES)

zbx_uint64match_condition_LDADD = \
	$(EXPR_LIBS)

zbx_uint64match_condition_LDADD += @SERVER_LIBS@

zbx_uint64match_condition_LDFLAGS = @SERVER_LDFLAGS@

zbx_uint64match_condition_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_get_function_type

zbx_get_function_type_SOURCES = \
	zbx_get_function_type.c \
	$(COMMON_SRC_FILES)

zbx_get_function_type_LDADD = \
	$(EXPR_LIBS)

zbx_get_function_type_LDADD += @SERVER_LIBS@

zbx_get_function_type_LDFLAGS = @SERVER_LDFLAGS@

zbx_get_function_type_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_is_discovery_macro

zbx_is_discovery_macro_SOURCES = \
	zbx_is_discovery_macro.c \
	$(COMMON_SRC_FILES)

zbx_is_discovery_macro_LDADD = \
	$(EXPR_LIBS)

zbx_is_discovery_macro_LDADD += @SERVER_LIBS@

zbx_is_discovery_macro_LDFLAGS = @SERVER_LDFLAGS@

zbx_is_discovery_macro_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_function_validate_parameters

zbx_function_validate_parameters_SOURCES = \
	zbx_function_validate_parameters.c \
	$(COMMON_SRC_FILES)

zbx_function_validate_parameters_LDADD = \
	$(EXPR_LIBS)

zbx_function_validate_parameters_LDADD += @SERVER_LIBS@

zbx_function_validate_parameters_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_validate_parameters_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_check_time_period

zbx_check_time_period_SOURCES = \
	zbx_check_time_period.c \
	zbx_expr_common.c \
	$(COMMON_SRC_FILES)

zbx_check_time_period_LDADD = \
	$(EXPR_LIBS)

zbx_check_time_period_LDADD += @SERVER_LIBS@

zbx_check_time_period_LDFLAGS = @SERVER_LDFLAGS@

zbx_check_time_period_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_get_agent_item_nextcheck

zbx_get_agent_item_nextcheck_SOURCES = \
	zbx_get_agent_item_nextcheck.c \
	zbx_expr_common.c \
	$(COMMON_SRC_FILES)

zbx_get_agent_item_nextcheck_LDADD = \
	$(EXPR_LIBS)

zbx_get_agent_item_nextcheck_LDADD += @SERVER_LIBS@

zbx_get_agent_item_nextcheck_LDFLAGS = @SERVER_LDFLAGS@

zbx_get_agent_item_nextcheck_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_url_encode_decode

zbx_url_encode_decode_SOURCES = \
	zbx_url_encode_decode.c \
	$(COMMON_SRC_FILES)

zbx_url_encode_decode_LDADD = \
	$(EXPR_LIBS)

zbx_url_encode_decode_LDADD += @SERVER_LIBS@

zbx_url_encode_decode_LDFLAGS = @SERVER_LDFLAGS@

zbx_url_encode_decode_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_custom_interval_is_scheduling

zbx_custom_interval_is_scheduling_SOURCES = \
	zbx_custom_interval_is_scheduling.c \
	$(COMMON_SRC_FILES)

zbx_custom_interval_is_scheduling_LDADD = \
	$(EXPR_LIBS)

zbx_custom_interval_is_scheduling_LDADD += @SERVER_LIBS@

zbx_custom_interval_is_scheduling_LDFLAGS = @SERVER_LDFLAGS@

zbx_custom_interval_is_scheduling_CFLAGS = $(EXPR_COMPILER_FLAGS)

#zbx_user_macro_quote_context_dyn

zbx_user_macro_quote_context_dyn_SOURCES = \
	zbx_user_macro_quote_context_dyn.c \
	$(COMMON_SRC_FILES)

zbx_user_macro_quote_context_dyn_LDADD = \
	$(EXPR_LIBS)

zbx_user_macro_quote_context_dyn_LDADD += @SERVER_LIBS@

zbx_user_macro_quote_context_dyn_LDFLAGS = @SERVER_LDFLAGS@

zbx_user_macro_quote_context_dyn_CFLAGS = $(EXPR_COMPILER_FLAGS)
