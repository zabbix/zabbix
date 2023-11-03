## Process this file with automake to produce Makefile.in

EXTRA_DIST = .	

BUILD_TIME=`date +%H:%M:%S`
BUILD_DATE=`date +"%b %_d %Y"`
GOOS=`go env GOOS`
GOARCH=`go env GOARCH`
PKG=zabbix.com/pkg/version
NEW_FROM_REV=""

GOLDFLAGS = -X ${PKG}.titleMessage=zabbix_agent2
GOLDFLAGS += -X '${PKG}.compileDate=${BUILD_DATE}'
GOLDFLAGS += -X ${PKG}.compileTime=${BUILD_TIME}
GOLDFLAGS += -X ${PKG}.compileOs=${GOOS}
GOLDFLAGS += -X ${PKG}.compileArch=${GOARCH}
# GOLDFLAGS += -X ${PKG}.compileMode=daemon
GOLDFLAGS += -X main.confDefault=${AGENT2_CONFIG_FILE}

all: build

build:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go build -ldflags="${GOLDFLAGS}" -o bin ./...

clean: clean-go-build clean-sbom

clean-go-build:
	-go clean ./...
	-rm -f bin/mock_server
	-rm -f bin/zabbix_agent2

clean-sbom:
	-rm -f sbom-mod.json
	-rm -f sbom-mod.xml
	-rm -f sbom-za2.json
	-rm -f sbom-za2.xml

install:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOBIN=${GOBIN} \
		go install -ldflags="${GOLDFLAGS}" zabbix.com/cmd/zabbix_agent2
	test -f "$(DESTDIR)@AGENT2_CONFIG_FILE@" || cp "conf/zabbix_agent2.conf" "$(DESTDIR)@AGENT2_CONFIG_FILE@"

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

sbom-apps: sbom-za2

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

.PHONY: all build check clean clean-go-build clean-sbom format sbom sbom-apps sbom-mod sbom-za2 style
