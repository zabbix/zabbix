## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxvmware.a

libzbxvmware_a_SOURCES = \
	vmware_shmem.c \
	vmware_shmem.h \
	vmware_stats.c \
	vmware_perfcntr.c \
	vmware_perfcntr.h \
	vmware_service_cfglists.c \
	vmware_service_cfglists.h \
	vmware_hv.c \
	vmware_hv.h \
	vmware_ds.c \
	vmware_ds.h \
	vmware_vm.c \
	vmware_vm.h \
	vmware_event.c \
	vmware_event.h \
	vmware_rest.c \
	vmware_manager.c \
	vmware_internal.h \
	vmware.c

libzbxvmware_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
