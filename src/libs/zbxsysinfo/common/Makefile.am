## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libcommonsysinfo.a libcommonsysinfo_http.a libcommonsysinfo_httpmetrics.a

libcommonsysinfo_a_CFLAGS = -I@top_srcdir@/src/zabbix_agent/

libcommonsysinfo_a_SOURCES = \
	zbxsysinfo_common.c \
	zbxsysinfo_common.h \
	cpu.c \
	cpu.h \
	dir.c \
	dir.h \
	dns.c \
	dns.h \
	file.c \
	file.h \
	net.c \
	net.h \
	system.c \
	system.h \
	zabbix_stats.c \
	zabbix_stats.h

libcommonsysinfo_http_a_SOURCES = \
	http.c \
	http.h

libcommonsysinfo_httpmetrics_a_SOURCES = \
	http_metrics.c \
	http_metrics.h
