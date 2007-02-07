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

EXTRA_SUBDIRS = \
	aix aix/aix.c \
	common \
	freebsd freebsd/freebsd.c \
	hpux hpux/hpux.c \
	linux linux/linux.c \
	netbsd netbsd/netbsd.c \
	openbsd openbsd/openbsd.c \
	osf osf/osf.c \
	osx osx/osx.c \
	solaris solaris/solaris.c \
	unknown unknown/unknown.c \
	win32
