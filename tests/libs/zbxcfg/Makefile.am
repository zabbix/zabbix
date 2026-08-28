include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	parse_cfg_file \
	zbx_cfg_validate_bridge_adapter_url \
	zbx_cfg_prepare_bridge_adapter_connect_to

COMMON_SRC_FILES = \
	../../zbxmocktest.h

CFG_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

CFG_BRIDGE_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

CFG_WRAP_FLAGS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=fgets \
	-Wl,--wrap=open \
	-Wl,--wrap=close \
	-Wl,--wrap=lseek \
	-Wl,--wrap=fstat \
	-Wl,--wrap=__fxstat \
	-Wl,--wrap=stat \
	-Wl,--wrap=exit

CFG_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

CFG_LD_FLAGS = \
	@SERVER_LDFLAGS@ \
	$(CFG_WRAP_FLAGS) \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

parse_cfg_file_SOURCES = \
	parse_cfg_file.c \
	$(COMMON_SRC_FILES)

parse_cfg_file_LDADD = $(CFG_LIBS)

parse_cfg_file_LDFLAGS = $(CFG_LD_FLAGS)

parse_cfg_file_CFLAGS = $(CFG_COMPILER_FLAGS)

zbx_cfg_validate_bridge_adapter_url_SOURCES = \
	zbx_cfg_validate_bridge_adapter_url.c \
	$(COMMON_SRC_FILES)

zbx_cfg_validate_bridge_adapter_url_LDADD = $(CFG_BRIDGE_LIBS)

zbx_cfg_validate_bridge_adapter_url_LDFLAGS = $(CFG_LD_FLAGS)

zbx_cfg_validate_bridge_adapter_url_CFLAGS = $(CFG_COMPILER_FLAGS)

zbx_cfg_prepare_bridge_adapter_connect_to_SOURCES = \
	zbx_cfg_prepare_bridge_adapter_connect_to.c \
	$(COMMON_SRC_FILES)

zbx_cfg_prepare_bridge_adapter_connect_to_LDADD = $(CFG_BRIDGE_LIBS)

zbx_cfg_prepare_bridge_adapter_connect_to_LDFLAGS = $(CFG_LD_FLAGS)

zbx_cfg_prepare_bridge_adapter_connect_to_CFLAGS = $(CFG_COMPILER_FLAGS)
endif
