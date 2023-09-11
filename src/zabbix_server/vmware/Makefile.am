## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxvmware.a

libzbxvmware_a_SOURCES = \
	vmware_shmem.c \
	vmware_shmem.h \
	vmware_stats.c \
	vmware_perfcntr.c \
	vmware_perfcntr.h \
	vmware_rest.c \
	vmware_manager.c \
	vmware_internal.h \
	vmware.c \
	vmware.h

libzbxvmware_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
