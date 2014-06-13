CC = gcc
CFLAGS = -Wall -I ../../../include -I /usr/include/mysql
LFLAGS = -lssl -lldns -lcurl -lmysqlclient
LIBS_PFX = ../..
INCLUDE_OBJS = \
	$(LIBS_PFX)/libs/zbxcommon/libzbxcommon.a \
	$(LIBS_PFX)/libs/zbxalgo/libzbxalgo.a \
	$(LIBS_PFX)/libs/zbxdbcache/libzbxdbcache.a \
	../poller/libzbxpoller.a \
	$(LIBS_PFX)/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(LIBS_PFX)/libs/zbxserver/libzbxserver.a \
	$(LIBS_PFX)/libs/zbxlog/libzbxlog.a \
	$(LIBS_PFX)/libs/zbxdbhigh/libzbxdbhigh.a \
	$(LIBS_PFX)/libs/zbxjson/libzbxjson.a \
	$(LIBS_PFX)/libs/zbxcomms/libzbxcomms.a \
	$(LIBS_PFX)/libs/zbxrsm/libzbxrsm.a \
	$(LIBS_PFX)/libs/zbxcrypto/libzbxcrypto.a\
	$(LIBS_PFX)/libs/zbxsys/libzbxsys.a \
	$(LIBS_PFX)/libs/zbxalgo/libzbxalgo.a \
	$(LIBS_PFX)/libs/zbxmemory/libzbxmemory.a \
	../zabbix_server-events.o \
	$(LIBS_PFX)/libs/zbxnix/libzbxnix.a \
	$(LIBS_PFX)/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(LIBS_PFX)/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(LIBS_PFX)/libs/zbxcomms/libzbxcomms.a \
	$(LIBS_PFX)/libs/zbxcommon/libzbxcommon.a \
	$(LIBS_PFX)/libs/zbxdb/libzbxdb.a \
	../zabbix_server-actions.o \
	$(LIBS_PFX)/libs/zbxexec/libzbxexec.a \
	../zabbix_server-operations.o

all: t_rsm

t_rsm: t_rsm.o
	$(CC) $? -o $@ $(LFLAGS) $(CFLAGS) $(INCLUDE_OBJS)

clean:
	rm -f *.o t_rsm


