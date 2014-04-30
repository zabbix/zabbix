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
	@ARCH@

EXTRA_DIST = \
	aix/aix.c aix/hostname.c \
	freebsd/freebsd.c freebsd/hostname.c \
	hpux/hpux.c hpux/hostname.c \
	linux/linux.c linux/hostname.c \
	netbsd/netbsd.c netbsd/hostname.c \
	openbsd/openbsd.c openbsd/hostname.c \
	osf/osf.c osf/hostname.c \
	osx/osx.c osx/hostname.c \
	solaris/solaris.c solaris/hostname.c \
	unknown/unknown.c unknown/hostname.c \
	win32

noinst_LIBRARIES = \
	libzbxagentsysinfo.a \
	libzbxproxysysinfo.a \
	libzbxserversysinfo.a

# AGENT version of sysinfo library
libzbxagentsysinfo_a_CFLAGS = \
	-DWITH_AGENT_METRICS \
	-DWITH_COMMON_METRICS \
	-DWITH_SIMPLE_METRICS \
	-DWITH_SPECIFIC_METRICS \
	-DWITH_HOSTNAME_METRIC

libzbxagentsysinfo_a_SOURCES = \
	specsysinfo.h \
	sysinfo.c

# SERVER version of sysinfo library
libzbxserversysinfo_a_CFLAGS = \
	-DWITH_COMMON_METRICS \
	-DWITH_SIMPLE_METRICS

libzbxserversysinfo_a_SOURCES = \
	sysinfo.c

# PROXY version of sysinfo library
libzbxproxysysinfo_a_CFLAGS = \
	-DWITH_COMMON_METRICS \
	-DWITH_SIMPLE_METRICS \
	-DWITH_HOSTNAME_METRIC

libzbxproxysysinfo_a_SOURCES = \
	sysinfo.c
