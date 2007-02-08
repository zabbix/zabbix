## Process this file with automake to produce Makefile.in

SUBDIRS = $(ARCH)

noinst_LIBRARIES = libzbxsysinfo.a

libzbxsysinfo_a_SOURCES = \
	common/common.c \
	common/file.c \
	common/ntp.c \
	common/system.c \
	common/http.c \
	@ARCH@/@ARCH@.c

DIST_SUBDIRS = \
	aix \
	freebsd \
	hpux \
	linux \
	netbsd \
	openbsd \
	osf \
	osx \
	solaris \
	unknown

EXTRA_DIST = \
	aix/aix.c \
	freebsd/freebsd.c \
	hpux/hpux.c \
	linux/linux.c \
	netbsd/netbsd.c \
	openbsd/openbsd.c \
	osf/osf.c \
	osx/osx.c \
	solaris/solaris.c \
	unknown/unknown.c \
	win32
