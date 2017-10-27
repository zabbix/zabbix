if SERVER
SERVER_SUBDIRS = \
	zabbix_server
endif

SUBDIRS = \
	. \
	$(SERVER_SUBDIRS)

noinst_LIBRARIES = libzbxmockdata.a

libzbxmockdata_a_SOURCES = \
	data.c \
	db_simulation.c \
	zbxmockdata.c \
	zbxmockdata.h
