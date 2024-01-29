## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libfunclistsysinfo.a libspecsysinfo.a libspechostnamesysinfo.a

libfunclistsysinfo_a_SOURCES = \
	osf.c

libspecsysinfo_a_CFLAGS = -I$(top_srcdir)/src/zabbix_agent

libspecsysinfo_a_SOURCES = \
	cpu.c \
	diskio.c \
	diskspace.c \
	inodes.c \
	kernel.c \
	memory.c \
	proc.c \
	software.c \
	swap.c \
	system.c \
	uptime.c

libspechostnamesysinfo_a_SOURCES = \
	hostname.c
