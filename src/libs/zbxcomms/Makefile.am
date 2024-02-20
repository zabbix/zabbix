## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcomms.a

libzbxcomms_a_SOURCES = \
	comms.h \
	comms.c \
	telnet.c \
	tls.h \
	tls_version.c \
	tls_validate.c

if HAVE_OPENSSL
libzbxcomms_a_SOURCES += tls.c tls_openssl.c
else
if HAVE_GNUTLS
libzbxcomms_a_SOURCES += tls.c tls_gnutls.c
endif
endif

libzbxcomms_a_CFLAGS = \
		$(LIBXML2_CFLAGS) \
		$(TLS_CFLAGS)
