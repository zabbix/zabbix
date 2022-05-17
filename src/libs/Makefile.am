## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxalgo \
	zbxaudit \
	zbxavailability \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxcompress \
	zbxconf \
	zbxcrypto \
	zbxcyberark \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxdiscovery \
	zbxembed \
	zbxeval \
	zbxexec \
	zbxexport \
	zbxha \
	zbxhash \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxipcservice \
	zbxjson \
	zbxkvs \
	zbxlog \
	zbxmedia \
	zbxmodules \
	zbxmutexs \
	zbxnix \
	zbxprometheus \
	zbxregexp \
	zbxrtc \
	zbxself \
	zbxserver \
	zbxservice \
	zbxshmem \
	zbxsysinfo \
	zbxtasks \
	zbxthreads \
	zbxtrends \
	zbxvault \
	zbxxml

if SERVER
SERVER_SUBDIRS = \
	zbxaudit \
	zbxavailability \
	zbxcompress \
	zbxcyberark \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxexport \
	zbxha \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxkvs \
	zbxmedia \
	zbxprometheus \
	zbxrtc \
	zbxself \
	zbxserver \
	zbxservice \
	zbxshmem \
	zbxtasks \
	zbxtrends \
	zbxvault \
	zbxxml
else
if PROXY
PROXY_SUBDIRS = \
	zbxaudit \
	zbxavailability \
	zbxcompress \
	zbxcyberark \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxkvs \
	zbxprometheus \
	zbxrtc \
	zbxself \
	zbxserver \
	zbxshmem \
	zbxtasks \
	zbxtrends \
	zbxvault \
	zbxxml
endif
endif

SUBDIRS = \
	zbxalgo \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxcompress \
	zbxconf \
	zbxcrypto \
	zbxdbschema \
	zbxdiscovery \
	zbxexec \
	zbxhash \
	zbxhttp \
	zbxipcservice \
	zbxjson \
	zbxlog \
	zbxmodules \
	zbxmutexs \
	zbxnix \
	zbxregexp \
	zbxsysinfo \
	zbxthreads \
	zbxxml \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(AGENT_SUBDIRS)

EXTRA_DIST = \
	zbxsymbols \
	zbxwin32
