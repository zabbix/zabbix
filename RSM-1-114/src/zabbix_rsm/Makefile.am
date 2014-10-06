## Process this file with automake to produce Makefile.in

bin_PROGRAMS = rsm_epp_gen rsm_epp_enc rsm_epp_dec

rsm_epp_gen_SOURCES = gen.c
rsm_epp_enc_SOURCES = enc.c
rsm_epp_dec_SOURCES = dec.c

common_libs = \
	$(top_srcdir)/src/libs/zbxrsm/libzbxrsm.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a

rsm_epp_gen_LDADD = $(common_libs)
rsm_epp_enc_LDADD = $(common_libs)
rsm_epp_dec_LDADD = $(common_libs)
