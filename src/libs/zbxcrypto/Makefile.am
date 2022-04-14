## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcrypto.a

libzbxcrypto_a_SOURCES = \
	crypto.c \
	base64.c \
	sha256crypt.c \
	sha512crypt.c \
	aes.c \
	aes.h \
	tls.c \
	tls.h \
	tls_tcp.h \
	tls_tcp_active.h \
	tls_version.c \
	hmac.c

libzbxcrypto_a_CFLAGS = $(TLS_CFLAGS)
