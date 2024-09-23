include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_variant_compare \
	zbx_variant_to_value_type
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

VARIANT_LIBS = \
	$(VARIANT_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

VARIANT_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_variant_compare_SOURCES = \
	zbx_variant_compare.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_compare_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_compare_LDADD += @SERVER_LIBS@

zbx_variant_compare_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_compare_CFLAGS = $(VARIANT_COMPILER_FLAGS)

#zbx_variant_to_value_type

zbx_variant_to_value_type_SOURCES = \
	zbx_variant_to_value_type.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_to_value_type_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_to_value_type_LDADD += @SERVER_LIBS@

zbx_variant_to_value_type_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_to_value_type_CFLAGS = $(VARIANT_COMPILER_FLAGS)

endif
