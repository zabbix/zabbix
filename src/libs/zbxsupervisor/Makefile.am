## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = \
	libzbxsupervisor.a \
	libzbxsupervisor_client.a

libzbxsupervisor_a_SOURCES = \
	supervisor.c

libzbxsupervisor_client_a_SOURCES = \
	supervisor_client.c

libzbxsupervisor_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS) \
	$(SNMP_CFLAGS)
