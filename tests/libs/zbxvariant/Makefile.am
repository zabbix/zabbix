include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_variant_compare \
	zbx_variant_to_value_type \
	zbx_variant_convert \
	zbx_variant_set_numeric \
	zbx_variant_copy \
	zbx_variant_same
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

#zbx_variant_convert

zbx_variant_convert_SOURCES = \
	zbx_variant_convert.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_convert_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_convert_LDADD += @SERVER_LIBS@

zbx_variant_convert_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_convert_CFLAGS = $(VARIANT_COMPILER_FLAGS)

#zbx_variant_set_numeric

zbx_variant_set_numeric_SOURCES = \
	zbx_variant_set_numeric.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_set_numeric_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_set_numeric_LDADD += @SERVER_LIBS@

zbx_variant_set_numeric_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_set_numeric_CFLAGS = $(VARIANT_COMPILER_FLAGS)

#zbx_variant_copy

zbx_variant_copy_SOURCES = \
	zbx_variant_copy.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_copy_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_copy_LDADD += @SERVER_LIBS@

zbx_variant_copy_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_copy_CFLAGS = $(VARIANT_COMPILER_FLAGS)

#zbx_variant_same

zbx_variant_same_SOURCES = \
	zbx_variant_same.c \
	zbx_variant_common.c \
	$(COMMON_SRC_FILES)

zbx_variant_same_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_same_LDADD += @SERVER_LIBS@

zbx_variant_same_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_same_CFLAGS = $(VARIANT_COMPILER_FLAGS)

endif
