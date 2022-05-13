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
	zbxgetopt \
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
	zbxserialize \
	zbxxml \
	zbxha \
	zbxrtc \
	zbxtls

if SERVER
SERVER_SUBDIRS = \
	zbxdb \
	zbxdbupgrade \
	zbxdbcache \
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
	zbxserialize \
	zbxha \
	zbxrtc \
	zbxtls
else
if PROXY
PROXY_SUBDIRS = \
	zbxdb \
	zbxdbupgrade \
	zbxdbcache \
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
	zbxserialize \
	zbxrtc \
	zbxtls
endif
endif

SUBDIRS = \
	zbxhash \
	zbxcrypto \
	zbxcommon \
	zbxgetopt \
	zbxlog \
	zbxalgo \
	zbxnix \
	zbxconf \
	zbxhttp \
	zbxsysinfo \
	zbxthreads \
	zbxmutexs \
	zbxcommshigh \
	zbxcomms \
	zbxjson \
	zbxexec \
	zbxmodules \
	zbxregexp \
	zbxipcservice \
	zbxcompress \
	zbxxml \
	zbxtls \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(AGENT_SUBDIRS)

EXTRA_DIST = \
	zbxsymbols \
	zbxwin32
