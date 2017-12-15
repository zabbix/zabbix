if SERVER
SERVER_SUBDIRS = \
	libs
endif

SUBDIRS = \
	. \
	$(SERVER_SUBDIRS)

noinst_LIBRARIES = libzbxmockdata.a

libzbxmockdata_a_SOURCES = \
	db_simulation.c \
	zbxmockdata.c \
	zbxmockdata.h
