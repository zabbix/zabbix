## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbcache \
	zbxdbhigh \
	zbxdb \
	zbxdbupgrade \
	zbxshmem \
	zbxalgo \
	zbxmedia \
	zbxcrypto \
	zbxhash \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxdbschema \
	zbxdiscovery \
	zbxlog \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxthreads \
	zbxmutexs \
	zbxjson \
	zbxserver \
	zbxicmpping \
	zbxexec \
	zbxexport \
	zbxself \
	zbxmodules \
	zbxregexp \
	zbxtasks \
	zbxhttp \
	zbxipcservice \
	zbxhistory \
	zbxcompress \
	zbxembed \
	zbxprometheus \
	zbxvault \
	zbxcyberark \
	zbxhashicorp \
	zbxkvs \
	zbxdiag \
	zbxtrends \
	zbxavailability \
	zbxservice \
	zbxaudit \
	zbxeval \
	zbxxml \
	zbxha \
	zbxrtc

if SERVER
SERVER_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbschema \
	zbxdbupgrade \
	zbxdbhigh \
	zbxhttp \
	zbxshmem \
	zbxserver \
	zbxicmpping \
	zbxmedia \
	zbxself \
	zbxtasks \
	zbxhistory \
	zbxcompress \
	zbxxml \
	zbxembed \
	zbxexport \
	zbxprometheus \
	zbxvault \
	zbxcyberark \
	zbxhashicorp \
	zbxkvs \
	zbxdiag \
	zbxtrends \
	zbxavailability \
	zbxservice \
	zbxaudit \
	zbxeval \
	zbxha \
	zbxrtc
else
if PROXY
PROXY_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbschema \
	zbxdbupgrade \
	zbxdbhigh \
	zbxhttp \
	zbxshmem \
	zbxserver \
	zbxicmpping \
	zbxself \
	zbxtasks \
	zbxhistory \
	zbxcompress \
	zbxxml \
	zbxembed \
	zbxexport \
	zbxprometheus \
	zbxvault \
	zbxcyberark \
	zbxhashicorp \
	zbxkvs \
	zbxdiag \
	zbxtrends \
	zbxavailability \
	zbxaudit \
	zbxeval \
	zbxrtc
endif
endif

SUBDIRS = \
	zbxhash \
	zbxcrypto \
	zbxcommon \
	zbxdbschema \
	zbxlog \
	zbxalgo \
	zbxnix \
	zbxconf \
	zbxhttp \
	zbxsysinfo \
	zbxthreads \
	zbxmutexs \
	zbxcommshigh \
	zbxdiscovery \
	zbxcomms \
	zbxjson \
	zbxexec \
	zbxmodules \
	zbxregexp \
	zbxipcservice \
	zbxcompress \
	zbxxml \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(AGENT_SUBDIRS)

EXTRA_DIST = \
	zbxsymbols \
	zbxwin32
