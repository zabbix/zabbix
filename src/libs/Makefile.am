## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbcache \
	zbxdbhigh \
	zbxdb \
	zbxdbupgrade \
	zbxmemory \
	zbxalgo \
	zbxmedia \
	zbxcrypto \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxlog \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxsys \
	zbxjson \
	zbxserver \
	zbxicmpping \
	zbxexec \
	zbxself \
	zbxmodules \
	zbxregexp \
	zbxtasks \
	zbxhttp \
	zbxipcservice \
	zbxhistory \
	zbxcompress \
	zbxembed \
	zbxprometheus

if SERVER
SERVER_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbupgrade \
	zbxdbcache \
	zbxdbhigh \
	zbxhttp \
	zbxmemory \
	zbxserver \
	zbxicmpping \
	zbxmedia \
	zbxself \
	zbxtasks \
	zbxhistory \
	zbxcompress \
	zbxembed \
	zbxprometheus
else
if PROXY
PROXY_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbupgrade \
	zbxdbcache \
	zbxdbhigh \
	zbxhttp \
	zbxmemory \
	zbxserver \
	zbxicmpping \
	zbxself \
	zbxtasks \
	zbxhistory \
	zbxcompress \
	zbxembed \
	zbxprometheus
endif
endif

SUBDIRS = \
	zbxcrypto \
	zbxcommon \
	zbxlog \
	zbxalgo \
	zbxnix \
	zbxconf \
	zbxhttp \
	zbxsysinfo \
	zbxsys \
	zbxcomms \
	zbxjson \
	zbxexec \
	zbxmodules \
	zbxregexp \
	zbxipcservice \
	zbxcompress \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(AGENT_SUBDIRS)

EXTRA_DIST = zbxwin32
