## Process this file with automake to produce Makefile.in

all: build

build:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go build

clean:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go clean

install:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go install

check:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go test
