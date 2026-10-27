include ../Makefile.include

noinst_PROGRAMS = \
	zbx_buf_readln

# zbxfile depends on zbxcommon

FILE_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_buf_readln_SOURCES = \
	zbx_buf_readln.c \
	../../zbxmocktest.h

zbx_buf_readln_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_buf_readln_LDADD = $(FILE_LIBS)
zbx_buf_readln_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_buf_readln_LDADD += @SERVER_LIBS@
zbx_buf_readln_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_buf_readln_LDADD += @PROXY_LIBS@
zbx_buf_readln_LDFLAGS += @PROXY_LDFLAGS@
endif
endif
