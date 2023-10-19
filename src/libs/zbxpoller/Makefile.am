## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller.a

libzbxpoller_a_SOURCES = \
	checks_agent.c \
	checks_ssh.c \
	checks_telnet.c \
	poller.c \
	ssh_run.h \
	telnet_run.c \
	telnet_run.h

if HAVE_SSH
libzbxpoller_a_SOURCES += ssh_run.c
libzbxpoller_a_CFLAGS = $(SSH_CFLAGS)
endif

if HAVE_SSH2
libzbxpoller_a_SOURCES += ssh2_run.c
libzbxpoller_a_CFLAGS = $(SSH2_CFLAGS)
endif
