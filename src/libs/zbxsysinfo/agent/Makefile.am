## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libagentsysinfo.a

libagentsysinfo_a_SOURCES = \
	agent.c \
	agent.h \
	modbus.c

if HAVE_LIBMODBUS
libagentsysinfo_a_CFLAGS = @LIBMODBUS_CFLAGS@
endif
