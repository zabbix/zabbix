if SERVER
SERVER_SUBDIRS = \
	libs
endif

SUBDIRS = \
	. \
	$(SERVER_SUBDIRS)

noinst_LIBRARIES = \
	libzbxmocktest.a \
	libzbxmockdata.a

libzbxmocktest_a_SOURCES = \
	zbxmocktest.c

libzbxmockdata_a_SOURCES = \
	db_simulation.c \
	zbxmockdata.c \
	zbxmockdata.h
