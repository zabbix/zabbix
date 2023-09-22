## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = 		\
	libzbxautoreg_server.a	\
	libzbxautoreg_proxy.a

# server
libzbxautoreg_server_a_SOURCES = \
	autoreg_server.c

libzbxautoreg_server_a_CFLAGS = \
	$(TLS_CFLAGS)

# proxy
libzbxautoreg_proxy_a_SOURCES = \
	autoreg_proxy.c

libzbxautoreg_proxy_a_CFLAGS = \
	$(TLS_CFLAGS)
