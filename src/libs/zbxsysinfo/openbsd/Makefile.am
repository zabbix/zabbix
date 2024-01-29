## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libfunclistsysinfo.a libspecsysinfo.a libspechostnamesysinfo.a

libfunclistsysinfo_a_SOURCES = \
	openbsd.c

libspecsysinfo_a_CFLAGS = -I$(top_srcdir)/src/zabbix_agent

libspecsysinfo_a_SOURCES = \
	boottime.c \
	cpu.c \
	diskio.c \
	diskspace.c \
	inodes.c inodes.h \
	kernel.c \
	memory.c \
	net.c \
	proc.c \
	sensors.c \
	software.c \
	swap.c \
	system.c \
	uptime.c

libspechostnamesysinfo_a_SOURCES = \
	hostname.c
