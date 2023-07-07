## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libfunclistsysinfo.a libspecsysinfo.a libspechostnamesysinfo.a

libfunclistsysinfo_a_SOURCES = \
	hpux.c

libspecsysinfo_a_CFLAGS = -I$(top_srcdir)/src/zabbix_agent

libspecsysinfo_a_SOURCES = \
	cpu.c \
	diskio.c \
	diskspace.c \
	inodes.c inodes.h \
	memory.c \
	net.c \
	proc.c \
	software.c \
	system.c

libspechostnamesysinfo_a_SOURCES = \
	hostname.c
