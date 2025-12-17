## Process this file with automake to produce Makefile.in

EXTRA_DIST = .

BUILD_TIME=`date +%H:%M:%S`
BUILD_DATE=`date +"%b %_d %Y"`
GOOS=`go env GOOS`
GOARCH=`go env GOARCH`
PKG=golang.zabbix.com/agent2/pkg/version
NEW_FROM_REV=""

GOLDFLAGS = -X '${PKG}.compileDate=${BUILD_DATE}'
GOLDFLAGS += -X ${PKG}.compileTime=${BUILD_TIME}
GOLDFLAGS += -X ${PKG}.compileOs=${GOOS}
GOLDFLAGS += -X ${PKG}.compileArch=${GOARCH}

AGENT_GOLDFLAGS = ${GOLDFLAGS}
AGENT_GOLDFLAGS += -X main.confDefault=${AGENT2_CONFIG_FILE}
AGENT_GOLDFLAGS += -X main.applicationName=zabbix_agent2

WEBSERVICE_GOLDFLAGS = ${GOLDFLAGS}
WEBSERVICE_GOLDFLAGS += -X main.applicationName=zabbix_web_service

if HAVE_PCRE2
AGENT_GOTAGS = pcre2
endif

dist_sysconf_DATA =
TARGETS =
INSTALL_TARGETS =

if AGENT2
TARGETS += bin/zabbix_agent2
INSTALL_TARGETS += install-bin/zabbix_agent2
dist_sysconf_DATA += conf/zabbix_agent2.conf
endif

if WEBSERVICE
TARGETS += bin/zabbix_web_service
INSTALL_TARGETS += install-bin/zabbix_web_service
dist_sysconf_DATA += conf/zabbix_web_service.conf
endif

all: build

bin/zabbix_agent2: FORCE
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		$(GO) build \
		-tags="${AGENT_GOTAGS}" \
		-ldflags="${AGENT_GOLDFLAGS}" \
		-o bin \
		golang.zabbix.com/agent2/cmd/zabbix_agent2

bin/zabbix_web_service: FORCE
	$(GO) build \
	-ldflags="${WEBSERVICE_GOLDFLAGS}" \
	-o bin \
	golang.zabbix.com/agent2/cmd/zabbix_web_service

build: ${TARGETS}

clean: clean-go-build clean-sbom

clean-go-build:
	-$(GO) clean ./...
	-$(GO) clean -cache
	-rm -f bin/mock_server
	-rm -f bin/zabbix_agent2
	-rm -f bin/zabbix_web_service

clean-sbom:
	-rm -f sbom-mod.json
	-rm -f sbom-mod.xml
	-rm -f sbom-za2.json
	-rm -f sbom-za2.xml
	-rm -f sbom-zws.json
	-rm -f sbom-zws.xml

# Mode flags (-m) for $(INSTALL) have been intentionally omitted to avoid
# changing permission flags of existing directories and breaking their
# conformance to system policy.
#
# Directories are created for the case when we're installing into a staging
# environment where they may not exist at all.
install-bin/zabbix_agent2: bin/zabbix_agent2
	$(INSTALL) -d "$(DESTDIR)$(sbindir)"
	$(INSTALL_PROGRAM) bin/zabbix_agent2 "$(DESTDIR)$(sbindir)"
	$(INSTALL) -d "$(DESTDIR)$(AGENT2_PLUGIN_CONFIG_DIR)"
	$(INSTALL_DATA) conf/zabbix_agent2.conf "$(DESTDIR)$(sysconfdir)"
	$(INSTALL) -d "$(DESTDIR)$(AGENT2_PLUGIN_CONFIG_DIR)"
	(cd conf/zabbix_agent2.d/plugins.d && \
	for _f in *.conf; do \
		$(INSTALL_DATA) $${_f} "$(DESTDIR)$(AGENT2_PLUGIN_CONFIG_DIR)"; \
	done)
	(cd conf/zabbix_agent2.d/plugins.d/nix && \
	for _f in *.conf; do \
		$(INSTALL_DATA) $${_f} "$(DESTDIR)$(AGENT2_PLUGIN_CONFIG_DIR)"; \
	done)

install-bin/zabbix_web_service: bin/zabbix_web_service
	$(INSTALL_PROGRAM) bin/zabbix_web_service "$(DESTDIR)$(sbindir)"

install-exec-local: ${INSTALL_TARGETS}

sbom-za2.json:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod app \
		-main cmd/zabbix_agent2 \
		-licenses -assert-licenses \
		-json \
		-output-version 1.4 \
		-output "$@"

# cyclonedx-gomod defaults to xml as the output format if the -json flag is not given
# there is no way to specify this explicitly
sbom-za2.xml:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod app \
		-main cmd/zabbix_agent2 \
		-licenses -assert-licenses \
		-output-version 1.4 \
		-output "$@"

sbom-za2: sbom-za2.json

sbom-zws.json:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod app \
		-main cmd/zabbix_web_service \
		-licenses -assert-licenses \
		-json \
		-output-version 1.4 \
		-output "$@"

# cyclonedx-gomod defaults to xml as the output format if the -json flag is not given
# there is no way to specify this explicitly
sbom-zws.xml:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod app \
		-main cmd/zabbix_web_service \
		-licenses -assert-licenses \
		-output-version 1.4 \
		-output "$@"

sbom-zws: sbom-zws.json

sbom-apps: sbom-za2 sbom-zws

sbom-mod.json:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod mod \
		-licenses -assert-licenses \
		-json \
		-output-version 1.4 \
		-output "$@"

sbom-mod.xml:
	GOARCH="${GOARC}" GOOS="${GOOS}" CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" \
		cyclonedx-gomod mod \
		-licenses -assert-licenses \
		-output-version 1.4 \
		-output "$@"

sbom-mod: sbom-mod.json

sbom: sbom-apps sbom-mod

check:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" $(GO) test ./...
style:
	golangci-lint run --new-from-rev=$(NEW_FROM_REV)  ./...
format:
	$(GO) fmt ./...

.PHONY: all build check clean clean-go-build clean-sbom format sbom sbom-apps sbom-mod sbom-za2 sbom-zws style

# This is a target to be used as a prerequisite for other targets to force
# their build. See
# https://www.gnu.org/software/make/manual/html_node/Force-Targets.html
#
# In the case of bin/zabbix_{agent2,web_service} targets, make is unaware of
# their prerequisites, i.e. source files, as all it does is drive the Go build
# system.
#
# When building either target, and then modifying the source files, any repeat
# builds of these targets will not execute, as the target already exists.
# This leads to the subtle issue when iterating in development, where modifying
# the source and asking to build the `install` target would install the initial
# executable.
FORCE:
