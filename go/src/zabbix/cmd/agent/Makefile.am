## Process this file with automake to produce Makefile.in

all: build

build:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go build

clean:
	go clean

install:
	go install

check:
	go test

