include ../../Makefile.include

if AGENT
if HAVE_LIBMODBUS

AGENT_tests = \
	modbus_get

noinst_PROGRAMS = $(AGENT_tests)

COMMON_LIB_FILES = \
	$(AGENT_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

modbus_get_SOURCES = \
	modbus_get.c \
	../../../zbxmocktest.h

modbus_get_LDADD = \
	$(COMMON_LIB_FILES)

modbus_get_WRAP_FUNCS = \
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

modbus_get_LDADD += @AGENT_LIBS@

modbus_get_LDFLAGS = @AGENT_LDFLAGS@

modbus_get_CFLAGS = $(modbus_get_WRAP_FUNCS) $(COMMON_COMPILER_FLAGS)

modbus_get_CFLAGS += @LIBMODBUS_CFLAGS@

endif
endif
