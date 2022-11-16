noinst_PROGRAMS = \
	zbx_json_open_path \
	zbx_json_decodevalue \
	zbx_json_decodevalue_dyn \
	zbx_jsonpath_compile \
	zbx_jsonobj_query

JSON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_json_open_path_SOURCES = \
	zbx_json_open_path.c \
	../../zbxmocktest.h

zbx_json_open_path_LDADD = $(JSON_LIBS)

if SERVER
zbx_json_open_path_LDADD += @SERVER_LIBS@
zbx_json_open_path_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_json_open_path_LDADD += @PROXY_LIBS@
zbx_json_open_path_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_json_open_path_CFLAGS = -I@top_srcdir@/tests

# zbx_json_decodevalue

zbx_json_decodevalue_SOURCES = \
	zbx_json_decodevalue.c \
	mock_json.c mock_json.h \
	../../zbxmocktest.h

zbx_json_decodevalue_LDADD = $(JSON_LIBS)

if SERVER
zbx_json_decodevalue_LDADD += @SERVER_LIBS@
zbx_json_decodevalue_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_json_decodevalue_LDADD += @PROXY_LIBS@
zbx_json_decodevalue_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_json_decodevalue_CFLAGS = -I@top_srcdir@/tests

# zbx_json_decodevalue_dyn

zbx_json_decodevalue_dyn_SOURCES = \
	zbx_json_decodevalue_dyn.c \
	mock_json.c mock_json.h \
	../../zbxmocktest.h

zbx_json_decodevalue_dyn_LDADD = $(JSON_LIBS)

if SERVER
zbx_json_decodevalue_dyn_LDADD += @SERVER_LIBS@
zbx_json_decodevalue_dyn_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_json_decodevalue_dyn_LDADD += @PROXY_LIBS@
zbx_json_decodevalue_dyn_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_json_decodevalue_dyn_CFLAGS = -I@top_srcdir@/tests

zbx_jsonpath_compile_SOURCES = \
	zbx_jsonpath_compile.c \
	../../zbxmocktest.h

zbx_jsonpath_compile_LDADD = $(JSON_LIBS)

if SERVER
zbx_jsonpath_compile_LDADD += @SERVER_LIBS@
zbx_jsonpath_compile_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_jsonpath_compile_LDADD += @PROXY_LIBS@
zbx_jsonpath_compile_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_jsonpath_compile_CFLAGS = -I@top_srcdir@/tests

# zbx_jsonobj_query

zbx_jsonobj_query_SOURCES = \
	zbx_jsonobj_query.c \
	../../zbxmocktest.h

zbx_jsonobj_query_LDADD = $(JSON_LIBS)

if SERVER
zbx_jsonobj_query_LDADD += @SERVER_LIBS@
zbx_jsonobj_query_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_jsonobj_query_LDADD += @PROXY_LIBS@
zbx_jsonobj_query_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_jsonobj_query_CFLAGS = -I@top_srcdir@/tests
