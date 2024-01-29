## Process this file with automake to produce Makefile.in

EXTRA_DIST = .

BUILD_TIME=`date +%H:%M:%S`
BUILD_DATE=`date +"%b %_d %Y"`
GOOS=`go env GOOS`
GOARCH=`go env GOARCH`
PKG=zabbix.com/pkg/version
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

if HAVE_PCRE
AGENT_GOTAGS = pcre
endif

if HAVE_PCRE2
AGENT_GOTAGS = pcre2
endif

dist_sysconf_DATA =
TARGETS =
INSTALL_TARGETS =

if AGENT2
TARGETS += zabbix.com/cmd/zabbix_agent2
INSTALL_TARGETS += install-zabbix.com/cmd/zabbix_agent2
DBGTARGETS = zabbix.com/cmd/mock_server
dist_sysconf_DATA += conf/zabbix_agent2.conf
endif

if WEBSERVICE
TARGETS += zabbix.com/cmd/zabbix_web_service
INSTALL_TARGETS += install-zabbix.com/cmd/zabbix_web_service
dist_sysconf_DATA += conf/zabbix_web_service.conf
endif

all: build

zabbix.com/cmd/zabbix_agent2:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go build -tags="${AGENT_GOTAGS}" -ldflags="${AGENT_GOLDFLAGS}" -o bin zabbix.com/cmd/zabbix_agent2

zabbix.com/cmd/zabbix_web_service:
	go build -ldflags="${WEBSERVICE_GOLDFLAGS}" -o bin zabbix.com/cmd/zabbix_web_service

build: ${TARGETS}

clean: clean-go-build clean-sbom

clean-go-build:
	-go clean ./...
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

install-zabbix.com/cmd/zabbix_agent2:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOBIN=${GOBIN} \
		go install -ldflags="${AGENT_GOLDFLAGS}" ${TARGETS}
	$(MKDIR_P) "$(DESTDIR)$(AGENT2_CONFIG_PLUGINS_PATH)"
	test -f "$(DESTDIR)$(AGENT2_CONFIG_FILE)" || cp "conf/zabbix_agent2.conf" "$(DESTDIR)$(AGENT2_CONFIG_FILE)"
	for cfg in conf/zabbix_agent2.d/plugins.d/*.conf; do \
		test -f "$(DESTDIR)$(AGENT2_CONFIG_PLUGINS_PATH)/"`basename $$cfg` || \
		cp "$$cfg" "$(DESTDIR)$(AGENT2_CONFIG_PLUGINS_PATH)/"`basename $$cfg`; \
	done

install-zabbix.com/cmd/zabbix_web_service:
	GOBIN=${GOBIN} go install -ldflags="${WEBSERVICE_GOLDFLAGS}" zabbix.com/cmd/zabbix_web_service

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
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go test ./...
style:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" golangci-lint run --new-from-rev=$(NEW_FROM_REV)  ./...
format:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go fmt ./... 

.PHONY: all build check clean clean-go-build clean-sbom format sbom sbom-apps sbom-mod sbom-za2 sbom-zws style
