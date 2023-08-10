## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxvmware.a

if HAVE_LIBXML2
if HAVE_LIBCURL
VMWARE_FILES_WITH_LIBXML2 = vmware_perfcntr.c
endif
endif

libzbxvmware_a_SOURCES = \
	vmware_stats.c \
	$(VMWARE_FILES_WITH_LIBXML2) \
	vmware_perfcntr.h \
	vmware_rest.c \
	vmware_manager.c \
	vmware_internal.h \
	vmware.c \
	vmware.h

libzbxvmware_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
