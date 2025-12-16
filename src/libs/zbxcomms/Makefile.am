## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcomms.a libzbxagent2comms.a

libzbxcomms_a_SOURCES = \
	comms.h \
	comms.c \
	telnet.c \
	tls.h \
	tls_version.c \
	tls_validate.c

libzbxcomms_a_CFLAGS = \
		$(LIBXML2_CFLAGS) \
		$(TLS_CFLAGS) \
		$(ARES_CFLAGS)

# AGENT2 version of comms library
libzbxagent2comms_a_CFLAGS = \
	-DWITH_AGENT2_METRICS \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)

libzbxagent2comms_a_SOURCES = \
	comms.h \
	comms.c \
	telnet.c \
	tls.h \
	tls_version.c \
	tls_validate.c

if HAVE_OPENSSL
libzbxcomms_a_SOURCES += tls.c tls_openssl.c
libzbxagent2comms_a_SOURCES += tls.c tls_openssl.c
else
if HAVE_GNUTLS
libzbxcomms_a_SOURCES += tls.c tls_gnutls.c
libzbxagent2comms_a_SOURCES += tls.c tls_gnutls.c
endif
endif
