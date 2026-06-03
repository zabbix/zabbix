include ../Makefile.include

noinst_PROGRAMS = \
	zbx_json_open_path \
	zbx_json_decodevalue \
	zbx_json_decodevalue_dyn \
	zbx_jsonpath_compile \
	zbx_jsonobj_query \
	zbx_json_value_by_name \
	zbx_json_object_is_empty \
	zbx_json_count

JSON_LIBS = \
	$(JSON_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_json_open_path_SOURCES = \
	zbx_json_open_path.c \
	../../zbxmocktest.h

zbx_json_open_path_LDADD = $(JSON_LIBS)
zbx_json_open_path_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_open_path_LDADD += @SERVER_LIBS@
zbx_json_open_path_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_open_path_LDADD += @PROXY_LIBS@
zbx_json_open_path_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_open_path_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_json_decodevalue

zbx_json_decodevalue_SOURCES = \
	zbx_json_decodevalue.c \
	mock_json.c mock_json.h \
	../../zbxmocktest.h

zbx_json_decodevalue_LDADD = $(JSON_LIBS)
zbx_json_decodevalue_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_decodevalue_LDADD += @SERVER_LIBS@
zbx_json_decodevalue_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_decodevalue_LDADD += @PROXY_LIBS@
zbx_json_decodevalue_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_decodevalue_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_json_decodevalue_dyn

zbx_json_decodevalue_dyn_SOURCES = \
	zbx_json_decodevalue_dyn.c \
	mock_json.c mock_json.h \
	../../zbxmocktest.h

zbx_json_decodevalue_dyn_LDADD = $(JSON_LIBS)
zbx_json_decodevalue_dyn_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_decodevalue_dyn_LDADD += @SERVER_LIBS@
zbx_json_decodevalue_dyn_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_decodevalue_dyn_LDADD += @PROXY_LIBS@
zbx_json_decodevalue_dyn_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_decodevalue_dyn_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_jsonpath_compile_SOURCES = \
	zbx_jsonpath_compile.c \
	../../zbxmocktest.h

zbx_jsonpath_compile_LDADD = $(JSON_LIBS)
zbx_jsonpath_compile_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_jsonpath_compile_LDADD += @SERVER_LIBS@
zbx_jsonpath_compile_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_jsonpath_compile_LDADD += @PROXY_LIBS@
zbx_jsonpath_compile_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_jsonpath_compile_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_jsonobj_query

zbx_jsonobj_query_SOURCES = \
	zbx_jsonobj_query.c \
	../../zbxmocktest.h

zbx_jsonobj_query_LDADD = $(JSON_LIBS)
zbx_jsonobj_query_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_jsonobj_query_LDADD += @SERVER_LIBS@
zbx_jsonobj_query_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_jsonobj_query_LDADD += @PROXY_LIBS@
zbx_jsonobj_query_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_jsonobj_query_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_json_value_by_name

zbx_json_value_by_name_SOURCES = \
	zbx_json_value_by_name.c \
	mock_json.c mock_json.h \
	../../zbxmocktest.h

zbx_json_value_by_name_LDADD = $(JSON_LIBS)
zbx_json_value_by_name_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_value_by_name_LDADD += @SERVER_LIBS@
zbx_json_value_by_name_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_value_by_name_LDADD += @PROXY_LIBS@
zbx_json_value_by_name_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_value_by_name_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_json_object_is_empty

zbx_json_object_is_empty_SOURCES = \
	zbx_json_object_is_empty.c \
	../../zbxmocktest.h

zbx_json_object_is_empty_LDADD = $(JSON_LIBS)
zbx_json_object_is_empty_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_object_is_empty_LDADD += @SERVER_LIBS@
zbx_json_object_is_empty_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_object_is_empty_LDADD += @PROXY_LIBS@
zbx_json_object_is_empty_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_object_is_empty_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# zbx_json_count

zbx_json_count_SOURCES = \
	zbx_json_count.c \
	../../zbxmocktest.h

zbx_json_count_LDADD = $(JSON_LIBS)
zbx_json_count_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_json_count_LDADD += @SERVER_LIBS@
zbx_json_count_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_json_count_LDADD += @PROXY_LIBS@
zbx_json_count_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_json_count_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
