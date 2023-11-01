## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxalgo \
	zbxalerter \
	zbxasyncpoller \
	zbxasynchttppoller \
	zbxaudit \
	zbxautoreg \
	zbxavailability \
	zbxconnector \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxcompress \
	zbxconf \
	zbxcrypto \
	zbxdb \
	zbxcacheconfig \
	zbxcachehistory \
	zbxcachevalue \
	zbxdbhigh \
	zbxdbwrap \
	zbxdbsyncer \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxdiscovery \
	zbxembed \
	zbxeval \
	zbxevent \
	zbxexec \
	zbxexport \
	zbxexpr \
	zbxfile \
	zbxgetopt \
	zbxhash \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxipcservice \
	zbxipmi \
	zbxjson \
	zbxkvs \
	zbxlog \
	zbxmedia \
	zbxmodules \
	zbxmutexs \
	zbxnix \
	zbxnum \
	zbxodbc \
	zbxparam \
	zbxpoller \
	zbxpreproc \
	zbxprometheus \
	zbxproxybuffer \
	zbxregexp \
	zbxrtc \
	zbxscripts \
	zbxself \
	zbxserialize \
	zbxexpression \
	zbxservice \
	zbxshmem \
	zbxsnmptrapper \
	zbxsysinfo \
	zbxstats \
	zbxstr \
	zbxtagfilter \
	zbxtasks \
	zbxthreads \
	zbxtime \
	zbxtimekeeper \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
	zbxprof \
	zbxxml

if SERVER
SERVER_SUBDIRS = \
	zbxasynchttppoller \
	zbxaudit \
	zbxautoreg \
	zbxavailability \
	zbxconnector \
	zbxcompress \
	zbxdb \
	zbxcacheconfig \
	zbxcachehistory \
	zbxcachevalue \
	zbxdbhigh \
	zbxdbwrap \
	zbxdbsyncer \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxevent \
	zbxexport \
	zbxexpr \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxkvs \
	zbxmedia \
	zbxnum \
	zbxodbc \
	zbxparam \
	zbxpoller \
	zbxpreproc \
	zbxprometheus \
	zbxproxybuffer \
	zbxrtc \
	zbxscripts \
	zbxself \
	zbxserialize \
	zbxexpression \
	zbxservice \
	zbxshmem \
	zbxsnmptrapper \
	zbxstats \
	zbxstr \
	zbxtagfilter \
	zbxtasks \
	zbxtime \
	zbxtimekeeper \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
	zbxxml
if HAVE_IPMI
SERVER_SUBDIRS += zbxipmi
endif
else
if PROXY
PROXY_SUBDIRS = \
	zbxaudit \
	zbxautoreg \
	zbxavailability \
	zbxconnector \
	zbxcompress \
	zbxdb \
	zbxcacheconfig \
	zbxcachehistory \
	zbxcachevalue \
	zbxdbhigh \
	zbxdbwrap \
	zbxdbsyncer \
	zbxdbschema \
	zbxdbupgrade \
	zbxdiag \
	zbxembed \
	zbxeval \
	zbxevent \
	zbxexport \
	zbxhistory \
	zbxhttp \
	zbxicmpping \
	zbxip \
	zbxkvs \
	zbxnum \
	zbxodbc \
	zbxparam \
	zbxpoller \
	zbxpreproc \
	zbxprometheus \
	zbxproxybuffer \
	zbxrtc \
	zbxself \
	zbxserialize \
	zbxexpression \
	zbxscripts \
	zbxshmem \
	zbxsnmptrapper \
	zbxstats \
	zbxstr \
	zbxtagfilter \
	zbxtasks \
	zbxtime \
	zbxtimekeeper \
	zbxtrends \
	zbxvariant \
	zbxvault \
	zbxversion \
	zbxxml
if HAVE_IPMI
PROXY_SUBDIRS += zbxipmi
endif
endif
endif

SUBDIRS = \
	zbxalgo \
	zbxalerter \
	zbxasyncpoller \
	zbxasynchttppoller \
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
	zbxfile \
	zbxgetopt \
	zbxhash \
	zbxhttp \
	zbxip \
	zbxipcservice \
	zbxjson \
	zbxlog \
	zbxmodules \
	zbxmutexs \
	zbxprof \
	zbxnix \
	zbxnum \
	zbxodbc \
	zbxparam \
	zbxregexp \
	zbxscripts \
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
	zbxwin32 \
	zbxwinservice

## hashicorp and cyberark used to be a separate libraries, remove them at some point in the future
clean: clean-recursive
	rm -rf hashicorp && \
	rm -rf cyberark
.PHONY: clean
