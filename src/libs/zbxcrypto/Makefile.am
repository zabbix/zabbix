## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcrypto.a

libzbxcrypto_a_SOURCES = \
	base64.c \
	md5.c \
	sha256crypt.c \
	tls.c \
	tls.h \
	tls_tcp.h \
	tls_tcp_active.h \
	tls_version.c

libzbxcrypto_a_CFLAGS = $(TLS_CFLAGS)
