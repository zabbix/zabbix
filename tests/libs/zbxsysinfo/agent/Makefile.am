if AGENT
if HAVE_LIBMODBUS

AGENT_tests = \
	MODBUS_GET

noinst_PROGRAMS = $(AGENT_tests)

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/zabbix_agent/libzbxagent.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

MODBUS_GET_SOURCES = \
	MODBUS_GET.c
	../../../zbxmocktest.h

MODBUS_GET_LDADD = \
	$(COMMON_LIB_FILES)

MODBUS_GET_WRAP_FUNCS = \
	-Wl,--wrap=modbus_new_rtu \
	-Wl,--wrap=modbus_new_tcp_pi \
	-Wl,--wrap=modbus_set_slave \
	-Wl,--wrap=modbus_connect \
	-Wl,--wrap=modbus_read_bits \
	-Wl,--wrap=modbus_read_input_bits \
	-Wl,--wrap=modbus_read_input_registers \
	-Wl,--wrap=modbus_read_registers \
	-Wl,--wrap=modbus_close \
	-Wl,--wrap=modbus_free \
	-Wl,--wrap=modbus_set_response_timeout

MODBUS_GET_LDADD += @AGENT_LIBS@

MODBUS_GET_LDFLAGS = @AGENT_LDFLAGS@

MODBUS_GET_CFLAGS = $(MODBUS_GET_WRAP_FUNCS) $(COMMON_COMPILER_FLAGS)

MODBUS_GET_CFLAGS += @LIBMODBUS_CFLAGS@

endif
endif
