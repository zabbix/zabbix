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

clean:
	go clean ./...
	rm -f bin/zabbix_agent2 bin/mock_server

install:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOBIN=${GOBIN} \
		go install -ldflags="${GOLDFLAGS}" zabbix.com/cmd/zabbix_agent2
	test -f "$(DESTDIR)@AGENT2_CONFIG_FILE@" || cp "conf/zabbix_agent2.conf" "$(DESTDIR)@AGENT2_CONFIG_FILE@"

sbom.json:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" cyclonedx-gomod mod \
		   -output-version 1.4 \
		   -licenses -assert-licenses -json -output "$@"

# cyclonedx-gomod mod defaults to xml as the output format if the -json flag is not given
# there is no way to specify this explicitly
sbom.xml:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" cyclonedx-gomod mod \
		   -output-version 1.4 \
		   -licenses -assert-licenses -output "$@"

sbom: sbom.json

check:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go test ./...
style:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" golangci-lint run --new-from-rev=$(NEW_FROM_REV)  ./...
format:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go fmt ./...
