## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libcommonsysinfo.a libcommonsysinfo_http.a libcommonsysinfo_httpmetrics.a

libcommonsysinfo_a_CFLAGS = \
	$(TLS_CFLAGS)

libcommonsysinfo_a_SOURCES = \
	zbxsysinfo_common.c \
	zbxsysinfo_common.h \
	cpustat.c \
	cpustat.h \
	cpu.c \
	cpu.h \
	dir.c \
	dir.h \
	ip_reverse.c \
	ip_reverse.h \
	dns.c \
	dns.h \
	vfs_file.c \
	vfs_file.h \
	net.c \
	net.h \
	system.c \
	system.h \
	zabbix_stats.c \
	zabbix_stats.h \
	diskdevices.c \
	diskdevices.h \
	procstat.h \
	procstat.c \
	stats.h \
	stats.c \
	zbxkstat.h \
	zbxkstat.c

libcommonsysinfo_http_a_SOURCES = \
	http.c \
	http.h

libcommonsysinfo_http_a_CFLAGS = \
	$(TLS_CFLAGS)

libcommonsysinfo_httpmetrics_a_SOURCES = \
	http_metrics.c \
	http_metrics.h
