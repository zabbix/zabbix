## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller.a

libzbxpoller_a_SOURCES = \
	checks_agent.c \
	checks_ssh.c \
	checks_telnet.c \
	poller.c \
	poller_thread.c \
	ssh_run.h \
	telnet_run.c \
	telnet_run.h \
	checks_external.c \
	checks_external.h \
	checks_internal.c \
	checks_internal.h \
	checks_script.c \
	checks_script.h \
	checks_browser.c \
	checks_browser.h \
	checks_simple.c \
	checks_simple.h \
	checks_snmp.c \
	checks_snmp.h \
	checks_db.c \
	checks_db.h \
	checks_java.c \
	checks_java.h \
	checks_calculated.c \
	checks_calculated.h \
	checks_http.c \
	checks_http.h \
	checks_simple_vmware.c \
	checks_simple_vmware.h \
	async_poller.c \
	async_manager.c \
	async_manager.h \
	async_httpagent.c \
	async_httpagent.h \
	async_agent.c \
	async_worker.c \
	async_worker.h \
	async_queue.c \
	async_queue.h


libzbxpoller_a_CFLAGS = \
	$(TLS_CFLAGS) \
	$(LIBXML2_CFLAGS) \
	$(SNMP_CFLAGS)

if HAVE_SSH
libzbxpoller_a_SOURCES += ssh_run.c
libzbxpoller_a_CFLAGS += $(SSH_CFLAGS)
endif

if HAVE_SSH2
libzbxpoller_a_SOURCES += ssh2_run.c
libzbxpoller_a_CFLAGS += $(SSH2_CFLAGS)
endif
