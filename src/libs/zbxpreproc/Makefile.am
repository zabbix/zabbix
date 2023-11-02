## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpreproc.a libzbxpreprocbase.a

libzbxpreproc_a_SOURCES = \
	item_preproc.c \
	item_preproc.h \
	preproc_snmp.c \
	preproc_snmp.h \
	pp_cache.c \
	pp_cache.h \
	pp_diag.c \
	pp_error.c \
	pp_error.h \
	pp_execute.c \
	pp_execute.h \
	pp_manager.c \
	pp_manager.h \
	pp_queue.c \
	pp_queue.h \
	pp_stats.c \
	pp_task.c \
	pp_task.h \
	pp_worker.c \
	pp_worker.h \
	pp_protocol.c \
	pp_protocol.h

libzbxpreproc_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)

libzbxpreprocbase_a_SOURCES = \
	pp_history.c \
	pp_item.c
