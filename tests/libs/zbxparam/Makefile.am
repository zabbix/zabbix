include ../Makefile.include

BINARIES_tests = \
	replace_key_params_dyn

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

PARAM_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

PARAM_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

replace_key_params_dyn_SOURCES = \
	replace_key_params_dyn.c \
	$(COMMON_SRC_FILES)

replace_key_params_dyn_LDADD = \
	$(PARAM_LIBS)

replace_key_params_dyn_LDADD += @SERVER_LIBS@

replace_key_params_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

replace_key_params_dyn_CFLAGS = $(PARAM_COMPILER_FLAGS)
