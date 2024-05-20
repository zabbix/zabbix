include ../Makefile.common

noinst_PROGRAMS = \
	zbx_json_open_path \
	zbx_json_decodevalue \
	zbx_json_decodevalue_dyn \
	zbx_jsonpath_compile \
	zbx_jsonobj_query

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
