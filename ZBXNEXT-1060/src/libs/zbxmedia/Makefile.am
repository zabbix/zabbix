## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxmedia.a

if JABBER
JABBER_SOURCES = jabber.c
endif

libzbxmedia_a_SOURCES = \
			email.c \
			$(JABBER_SOURCES) \
			sms.c
