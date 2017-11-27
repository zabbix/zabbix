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
	zbxmocktest.c \
	zbxmocktest.h

libzbxmockdata_a_SOURCES = \
	zbxmockdb.c \
	zbxmockfile.c \
	zbxmockdata.c \
	zbxmockdata.h
