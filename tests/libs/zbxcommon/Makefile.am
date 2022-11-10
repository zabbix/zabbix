if SERVER
SERVER_tests = \
	zbx_function_find \
	parse_key \
	replace_key_params_dyn \
	calculate_item_nextcheck \
	calculate_item_nextcheck_unreachable \
	zbx_function_get_param_dyn \
	zbx_json_to_xml \
	zbx_xml_to_json \
	zbx_token_find \
	zbx_interval_preproc \
	zbx_validate_interval \
	is_double_suffix \
	is_double \
	zbx_trim_float \
	zbx_trim_integer \
	zbx_variant_compare \
	zbx_user_macro_parse \
	zbx_str_extract \
	convert_to_utf8 \
	zbx_truncate_itemkey \
	zbx_truncate_value \
	zbx_dyn_escape_string \
	zbx_tm_add \
	zbx_tm_sub \
	zbx_tm_round_up \
	zbx_tm_round_down \
	zbx_get_report_nextcheck \
	zbx_ltrim_utf8 \
	zbx_rtrim_utf8 \
	zbx_get_week_number \
	zbx_iso8601_utc
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/alerter/libzbxalerter.a \
	$(top_srcdir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/dbconfig/libzbxdbconfig.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/timer/libzbxtimer.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/snmptrapper/libzbxsnmptrapper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/zabbix_server/proxypoller/libzbxproxypoller.a \
	$(top_srcdir)/src/zabbix_server/selfmon/libzbxselfmon.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/zabbix_server/taskmanager/libzbxtaskmanager.a \
	$(top_srcdir)/src/zabbix_server/ipmi/libipmi.a \
	$(top_srcdir)/src/zabbix_server/odbc/libzbxodbc.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/zabbix_server/preprocessor/libpreprocessor.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

zbx_function_find_SOURCES = \
	zbx_function_find.c \
	$(COMMON_SRC_FILES)

zbx_function_find_LDADD = \
	$(COMMON_LIB_FILES)

zbx_function_find_LDADD += @SERVER_LIBS@

zbx_function_find_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_find_CFLAGS = $(COMMON_COMPILER_FLAGS)


parse_key_SOURCES = \
	parse_key.c \
	$(COMMON_SRC_FILES)

parse_key_LDADD = \
	$(COMMON_LIB_FILES)

parse_key_LDADD += @SERVER_LIBS@

parse_key_LDFLAGS = @SERVER_LDFLAGS@

parse_key_CFLAGS = $(COMMON_COMPILER_FLAGS)


replace_key_params_dyn_SOURCES = \
	replace_key_params_dyn.c \
	$(COMMON_SRC_FILES)

replace_key_params_dyn_LDADD = \
	$(COMMON_LIB_FILES)

replace_key_params_dyn_LDADD += @SERVER_LIBS@

replace_key_params_dyn_LDFLAGS = @SERVER_LDFLAGS@

replace_key_params_dyn_CFLAGS = $(COMMON_COMPILER_FLAGS)


calculate_item_nextcheck_SOURCES = \
	calculate_item_nextcheck.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_LDADD = \
	$(COMMON_LIB_FILES)

calculate_item_nextcheck_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_LDFLAGS = @SERVER_LDFLAGS@

calculate_item_nextcheck_CFLAGS = $(COMMON_COMPILER_FLAGS)

calculate_item_nextcheck_unreachable_SOURCES = \
	calculate_item_nextcheck_unreachable.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_unreachable_LDADD = \
	$(COMMON_LIB_FILES)

calculate_item_nextcheck_unreachable_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_unreachable_LDFLAGS = @SERVER_LDFLAGS@

calculate_item_nextcheck_unreachable_CFLAGS = $(COMMON_COMPILER_FLAGS)

zbx_function_get_param_dyn_SOURCES = \
	zbx_function_get_param_dyn.c \
	$(COMMON_SRC_FILES)

zbx_function_get_param_dyn_LDADD = \
	$(COMMON_LIB_FILES)

zbx_function_get_param_dyn_LDADD += @SERVER_LIBS@

zbx_function_get_param_dyn_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_get_param_dyn_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_json_to_xml_SOURCES = \
	zbx_json_to_xml.c \
	$(COMMON_SRC_FILES)

zbx_json_to_xml_LDADD = \
	$(COMMON_LIB_FILES)

zbx_json_to_xml_LDADD += @SERVER_LIBS@

zbx_json_to_xml_LDFLAGS = @SERVER_LDFLAGS@

zbx_json_to_xml_CFLAGS = $(COMMON_COMPILER_FLAGS) @LIBXML2_CFLAGS@


zbx_xml_to_json_SOURCES = \
	zbx_xml_to_json.c \
	$(COMMON_SRC_FILES)

zbx_xml_to_json_LDADD = \
	$(COMMON_LIB_FILES)

zbx_xml_to_json_LDADD += @SERVER_LIBS@

zbx_xml_to_json_LDFLAGS = @SERVER_LDFLAGS@

zbx_xml_to_json_CFLAGS = $(COMMON_COMPILER_FLAGS) @LIBXML2_CFLAGS@


zbx_token_find_SOURCES = \
	zbx_token_find.c \
	$(COMMON_SRC_FILES)

zbx_token_find_LDADD = \
	$(COMMON_LIB_FILES)

zbx_token_find_LDADD += @SERVER_LIBS@

zbx_token_find_LDFLAGS = @SERVER_LDFLAGS@

zbx_token_find_CFLAGS = $(COMMON_COMPILER_FLAGS)

zbx_interval_preproc_SOURCES = \
	zbx_interval_preproc.c \
	$(COMMON_SRC_FILES)

zbx_interval_preproc_LDADD = \
	$(COMMON_LIB_FILES)

zbx_interval_preproc_LDADD += @SERVER_LIBS@

zbx_interval_preproc_LDFLAGS = @SERVER_LDFLAGS@

zbx_interval_preproc_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_validate_interval_SOURCES = \
	zbx_validate_interval.c \
	$(COMMON_SRC_FILES)

zbx_validate_interval_LDADD = \
	$(COMMON_LIB_FILES)

zbx_validate_interval_LDADD += @SERVER_LIBS@

zbx_validate_interval_LDFLAGS = @SERVER_LDFLAGS@

zbx_validate_interval_CFLAGS = $(COMMON_COMPILER_FLAGS)


is_double_suffix_SOURCES = \
	is_double_suffix.c \
	$(COMMON_SRC_FILES)

is_double_suffix_LDADD = \
	$(COMMON_LIB_FILES)

is_double_suffix_LDADD += @SERVER_LIBS@

is_double_suffix_LDFLAGS = @SERVER_LDFLAGS@

is_double_suffix_CFLAGS = $(COMMON_COMPILER_FLAGS)


is_double_SOURCES = \
	is_double.c \
	$(COMMON_SRC_FILES)

is_double_LDADD = \
	$(COMMON_LIB_FILES)

is_double_LDADD += @SERVER_LIBS@

is_double_LDFLAGS = @SERVER_LDFLAGS@

is_double_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_trim_float_SOURCES = \
	zbx_trim_float.c \
	$(COMMON_SRC_FILES)

zbx_trim_float_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trim_float_LDADD += @SERVER_LIBS@

zbx_trim_float_LDFLAGS = @SERVER_LDFLAGS@

zbx_trim_float_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_trim_integer_SOURCES = \
	zbx_trim_integer.c \
	$(COMMON_SRC_FILES)

zbx_trim_integer_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trim_integer_LDADD += @SERVER_LIBS@

zbx_trim_integer_LDFLAGS = @SERVER_LDFLAGS@

zbx_trim_integer_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_variant_compare_SOURCES = \
	zbx_variant_compare.c \
	$(COMMON_SRC_FILES)

zbx_variant_compare_LDADD = \
	$(COMMON_LIB_FILES)

zbx_variant_compare_LDADD += @SERVER_LIBS@

zbx_variant_compare_LDFLAGS = @SERVER_LDFLAGS@

zbx_variant_compare_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_user_macro_parse_SOURCES = \
	zbx_user_macro_parse.c \
	$(COMMON_SRC_FILES)

zbx_user_macro_parse_LDADD = \
	$(COMMON_LIB_FILES)

zbx_user_macro_parse_LDADD += @SERVER_LIBS@

zbx_user_macro_parse_LDFLAGS = @SERVER_LDFLAGS@

zbx_user_macro_parse_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_str_extract_SOURCES = \
	zbx_str_extract.c \
	$(COMMON_SRC_FILES)

zbx_str_extract_LDADD = \
	$(COMMON_LIB_FILES)

zbx_str_extract_LDADD += @SERVER_LIBS@

zbx_str_extract_LDFLAGS = @SERVER_LDFLAGS@

zbx_str_extract_CFLAGS = $(COMMON_COMPILER_FLAGS)

convert_to_utf8_SOURCES = \
     convert_to_utf8.c \
     $(COMMON_SRC_FILES)

convert_to_utf8_LDADD = \
     $(COMMON_LIB_FILES)

convert_to_utf8_LDADD += @SERVER_LIBS@

convert_to_utf8_LDFLAGS = @SERVER_LDFLAGS@

convert_to_utf8_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_truncate_itemkey_SOURCES = \
	zbx_truncate_itemkey.c \
	$(COMMON_SRC_FILES)

zbx_truncate_itemkey_LDADD = \
	$(COMMON_LIB_FILES)

zbx_truncate_itemkey_LDADD += @SERVER_LIBS@

zbx_truncate_itemkey_LDFLAGS = @SERVER_LDFLAGS@

zbx_truncate_itemkey_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_truncate_value_SOURCES = \
	zbx_truncate_value.c \
	$(COMMON_SRC_FILES)

zbx_truncate_value_LDADD = \
	$(COMMON_LIB_FILES)

zbx_truncate_value_LDADD += @SERVER_LIBS@

zbx_truncate_value_LDFLAGS = @SERVER_LDFLAGS@

zbx_truncate_value_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_dyn_escape_string_SOURCES = \
	zbx_dyn_escape_string.c \
	$(COMMON_SRC_FILES)

zbx_dyn_escape_string_LDADD = \
	$(COMMON_LIB_FILES)

zbx_dyn_escape_string_LDADD += @SERVER_LIBS@

zbx_dyn_escape_string_LDFLAGS = @SERVER_LDFLAGS@

zbx_dyn_escape_string_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_tm_add

zbx_tm_add_SOURCES = \
	zbx_tm_add.c \
	$(COMMON_SRC_FILES)

zbx_tm_add_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tm_add_LDADD += @SERVER_LIBS@

zbx_tm_add_LDFLAGS = @SERVER_LDFLAGS@

zbx_tm_add_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_tm_sub

zbx_tm_sub_SOURCES = \
	zbx_tm_sub.c \
	$(COMMON_SRC_FILES)

zbx_tm_sub_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tm_sub_LDADD += @SERVER_LIBS@

zbx_tm_sub_LDFLAGS = @SERVER_LDFLAGS@

zbx_tm_sub_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_tm_round_up

zbx_tm_round_up_SOURCES = \
	zbx_tm_round_up.c \
	$(COMMON_SRC_FILES)

zbx_tm_round_up_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tm_round_up_LDADD += @SERVER_LIBS@

zbx_tm_round_up_LDFLAGS = @SERVER_LDFLAGS@

zbx_tm_round_up_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_tm_round_up

zbx_tm_round_down_SOURCES = \
	zbx_tm_round_down.c \
	$(COMMON_SRC_FILES)

zbx_tm_round_down_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tm_round_down_LDADD += @SERVER_LIBS@

zbx_tm_round_down_LDFLAGS = @SERVER_LDFLAGS@

zbx_tm_round_down_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_get_report_nextcheck

zbx_get_report_nextcheck_SOURCES = \
	zbx_get_report_nextcheck.c \
	$(COMMON_SRC_FILES)

zbx_get_report_nextcheck_LDADD = \
	$(COMMON_LIB_FILES)

zbx_get_report_nextcheck_LDADD += @SERVER_LIBS@

zbx_get_report_nextcheck_LDFLAGS = @SERVER_LDFLAGS@

zbx_get_report_nextcheck_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_ltrim_utf8

zbx_ltrim_utf8_SOURCES = \
	zbx_common_trim_utf8.c \
	zbx_ltrim_utf8.c \
	$(COMMON_SRC_FILES)

zbx_ltrim_utf8_LDADD = \
	$(COMMON_LIB_FILES)

zbx_ltrim_utf8_LDADD += @SERVER_LIBS@

zbx_ltrim_utf8_LDFLAGS = @SERVER_LDFLAGS@

zbx_ltrim_utf8_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_ltrim_utf8

zbx_rtrim_utf8_SOURCES = \
	zbx_common_trim_utf8.c \
	zbx_rtrim_utf8.c \
	$(COMMON_SRC_FILES)

zbx_rtrim_utf8_LDADD = \
	$(COMMON_LIB_FILES)

zbx_rtrim_utf8_LDADD += @SERVER_LIBS@

zbx_rtrim_utf8_LDFLAGS = @SERVER_LDFLAGS@

zbx_rtrim_utf8_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_get_week_number

zbx_get_week_number_SOURCES = \
	zbx_get_week_number.c \
	$(COMMON_SRC_FILES)

zbx_get_week_number_LDADD = \
	$(COMMON_LIB_FILES)

zbx_get_week_number_LDADD += @SERVER_LIBS@

zbx_get_week_number_LDFLAGS = @SERVER_LDFLAGS@

zbx_get_week_number_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_iso8601_utc

zbx_iso8601_utc_SOURCES = \
	zbx_iso8601_utc.c \
	$(COMMON_SRC_FILES)

zbx_iso8601_utc_LDADD = \
	$(COMMON_LIB_FILES)

zbx_iso8601_utc_LDADD += @SERVER_LIBS@

zbx_iso8601_utc_LDFLAGS = @SERVER_LDFLAGS@

zbx_iso8601_utc_CFLAGS = $(COMMON_COMPILER_FLAGS)


endif
