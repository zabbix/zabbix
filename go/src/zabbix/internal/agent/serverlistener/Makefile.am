## Process this file with automake to produce Makefile.in

all: build

build:
	CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go build

clean:
	go clean

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go test

