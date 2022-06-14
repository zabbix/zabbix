## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	agent \
	common \
	aix \
	freebsd \
	hpux \
	linux \
	netbsd \
	openbsd \
	osf \
	osx \
	simple \
	solaris \
	unknown

SUBDIRS = \
	agent \
	common \
	simple \
	$(ARCH)

EXTRA_DIST = \
	win32

noinst_LIBRARIES = \
	libzbxagentsysinfo.a \
	libzbxproxysysinfo.a \
	libzbxserversysinfo.a \
	libzbxagent2sysinfo.a

# AGENT version of sysinfo library
libzbxagentsysinfo_a_CFLAGS = \
	-DWITH_AGENT_METRICS \
	-DWITH_COMMON_METRICS \
	-DWITH_HTTP_METRICS \
	-DWITH_SIMPLE_METRICS \
	-DWITH_SPECIFIC_METRICS \
	-DWITH_HOSTNAME_METRIC

libzbxagentsysinfo_a_SOURCES = \
	specsysinfo.h \
	sysinfo.c

# AGENT2 version of sysinfo library
libzbxagent2sysinfo_a_CFLAGS = \
	-DWITH_COMMON_METRICS \
	-DWITH_AGENT2_METRICS

libzbxagent2sysinfo_a_SOURCES = \
	sysinfo.c

# SERVER version of sysinfo library
libzbxserversysinfo_a_CFLAGS = \
	-DWITH_COMMON_METRICS \
	-DWITH_HTTP_METRICS \
	-DWITH_SIMPLE_METRICS

libzbxserversysinfo_a_SOURCES = \
	sysinfo.c

# PROXY version of sysinfo library
libzbxproxysysinfo_a_CFLAGS = \
	-DWITH_COMMON_METRICS \
	-DWITH_HTTP_METRICS \
	-DWITH_SIMPLE_METRICS \
	-DWITH_HOSTNAME_METRIC

libzbxproxysysinfo_a_SOURCES = \
	sysinfo.c
