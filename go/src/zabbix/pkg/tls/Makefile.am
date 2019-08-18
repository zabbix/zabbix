## Process this file with automake to produce Makefile.in

all: build

build:
	CGO_LDFLAGS="${CGO_LDFLAGS}"  go build

clean:
	CGO_LDFLAGS="${CGO_LDFLAGS}"  go clean

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go test

