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
	zbxdbwrap \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxdiscovery \
	zbxembed \
	zbxeval \
	zbxexec \
	zbxexport \
	zbxexpr \
	zbxgetopt \
	zbxha \
	zbxhash \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxipcservice \
	zbxjson \
	zbxkvs \
	zbxlog \
	zbxmedia \
	zbxmodules \
	zbxmutexs \
	zbxnix \
	zbxnum \
	zbxparam \
	zbxprometheus \
	zbxregexp \
	zbxrtc \
	zbxself \
	zbxserialize \
	zbxserver \
	zbxservice \
	zbxshmem \
	zbxsysinfo \
	zbxstr \
	zbxtasks \
	zbxthreads \
	zbxtime \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
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
	zbxdbwrap \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxexport \
	zbxexpr \
	zbxha \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxkvs \
	zbxmedia \
	zbxnum \
	zbxparam \
	zbxprometheus \
	zbxrtc \
	zbxself \
	zbxserialize \
	zbxserver \
	zbxservice \
	zbxshmem \
	zbxstr \
	zbxtasks \
	zbxtime \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
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
	zbxdbwrap \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxexport \
	zbxhashicorp \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxkvs \
	zbxnum \
	zbxparam \
	zbxprometheus \
	zbxrtc \
	zbxself \
	zbxserialize \
	zbxserver \
	zbxshmem \
	zbxstr \
	zbxtasks \
	zbxtime \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
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
	zbxexpr \
	zbxgetopt \
	zbxhash \
	zbxhttp \
	zbxip \
	zbxipcservice \
	zbxjson \
	zbxlog \
	zbxmodules \
	zbxmutexs \
	zbxnix \
	zbxnum \
	zbxparam \
	zbxregexp \
	zbxsysinfo \
	zbxstr \
	zbxthreads \
	zbxtime \
	zbxvariant \
	zbxversion \
	zbxxml \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(AGENT_SUBDIRS)

EXTRA_DIST = \
	zbxsymbols \
	zbxwin32 \
	zbxwinservice
