include ../Makefile.include

BINARIES_tests = \
	zbx_is_supported_ip \
	zbx_is_ip \
	zbx_ip_in_list \
	zbx_iprange_parse \
	zbx_parse_serveractive_element \
	zbx_iprange_uniq_iter \
	zbx_iprange_volume \
	zbx_iprange_ip2str \
	zbx_iprange_uniq_next \
	zbx_portrange_uniq_next

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

IP_LIBS = \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

IP_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

#zbx_is_supported_ip

zbx_is_supported_ip_SOURCES = \
	zbx_is_supported_ip.c \
	$(COMMON_SRC_FILES)

zbx_is_supported_ip_LDADD = \
	$(IP_LIBS)

zbx_is_supported_ip_LDADD += @SERVER_LIBS@

zbx_is_supported_ip_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_is_supported_ip_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_is_ip

zbx_is_ip_SOURCES = \
	zbx_is_ip.c \
	$(COMMON_SRC_FILES)

zbx_is_ip_LDADD = \
	$(IP_LIBS)

zbx_is_ip_LDADD += @SERVER_LIBS@

zbx_is_ip_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_is_ip_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_ip_in_list

zbx_ip_in_list_SOURCES = \
	zbx_ip_in_list.c \
	$(COMMON_SRC_FILES)

zbx_ip_in_list_LDADD = \
	$(IP_LIBS)

zbx_ip_in_list_LDADD += @SERVER_LIBS@

zbx_ip_in_list_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_ip_in_list_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_iprange_parse

zbx_iprange_parse_SOURCES = \
	zbx_iprange_parse.c \
	$(COMMON_SRC_FILES)

zbx_iprange_parse_LDADD = \
	$(IP_LIBS)

zbx_iprange_parse_LDADD += @SERVER_LIBS@

zbx_iprange_parse_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_parse_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_parse_serveractive_element

zbx_parse_serveractive_element_SOURCES = \
	zbx_parse_serveractive_element.c \
	$(COMMON_SRC_FILES)

zbx_parse_serveractive_element_LDADD = \
	$(IP_LIBS)

zbx_parse_serveractive_element_LDADD += @SERVER_LIBS@

zbx_parse_serveractive_element_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_parse_serveractive_element_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_iprange_uniq_iter

zbx_iprange_uniq_iter_SOURCES = \
	zbx_iprange_uniq_iter.c \
	zbx_ip_common.c \
	$(COMMON_SRC_FILES)

zbx_iprange_uniq_iter_LDADD = \
	$(IP_LIBS)

zbx_iprange_uniq_iter_LDADD += @SERVER_LIBS@

zbx_iprange_uniq_iter_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_uniq_iter_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_iprange_volume

zbx_iprange_volume_SOURCES = \
	zbx_iprange_volume.c \
	$(COMMON_SRC_FILES)

zbx_iprange_volume_LDADD = \
	$(IP_LIBS)

zbx_iprange_volume_LDADD += @SERVER_LIBS@

zbx_iprange_volume_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_volume_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_iprange_ip2str

zbx_iprange_ip2str_SOURCES = \
	zbx_iprange_ip2str.c \
	$(COMMON_SRC_FILES)

zbx_iprange_ip2str_LDADD = \
	$(IP_LIBS)

zbx_iprange_ip2str_LDADD += @SERVER_LIBS@

zbx_iprange_ip2str_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_ip2str_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_iprange_uniq_next

zbx_iprange_uniq_next_SOURCES = \
	zbx_iprange_uniq_next.c \
	zbx_ip_common.c \
	$(COMMON_SRC_FILES)

zbx_iprange_uniq_next_LDADD = \
	$(IP_LIBS)

zbx_iprange_uniq_next_LDADD += @SERVER_LIBS@

zbx_iprange_uniq_next_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iprange_uniq_next_CFLAGS = $(IP_COMPILER_FLAGS)

#zbx_portrange_uniq_next

zbx_portrange_uniq_next_SOURCES = \
	zbx_portrange_uniq_next.c \
	zbx_ip_common.c \
	$(COMMON_SRC_FILES)

zbx_portrange_uniq_next_LDADD = \
	$(IP_LIBS)

zbx_portrange_uniq_next_LDADD += @SERVER_LIBS@

zbx_portrange_uniq_next_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_portrange_uniq_next_CFLAGS = $(IP_COMPILER_FLAGS)
