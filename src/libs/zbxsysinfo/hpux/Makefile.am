## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libspecsysinfo.a libspechostnamesysinfo.a

libspecsysinfo_a_CFLAGS = -I$(top_srcdir)/src/zabbix_agent

libspecsysinfo_a_SOURCES = \
	cpu.c \
	diskio.c \
	diskspace.c \
	hpux.c \
	inodes.c inodes.h \
	memory.c \
	net.c \
	proc.c \
	software.c \
	system.c

libspechostnamesysinfo_a_SOURCES = \
	hostname.c
