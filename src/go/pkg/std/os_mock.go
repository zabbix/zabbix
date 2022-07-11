//go:build linux
// +build linux

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

// package std is used to create wrappers for standard Go functions to support
// mocking in tests where necessary
package std

import (
	"bytes"
	"os"
	"syscall"
	"time"

	"golang.org/x/sys/unix"
)

// mocked os functionality

type MockOs interface {
	MockFile(path string, data []byte)
}

type fileTime struct {
	ModTime time.Time
}

type mockOs struct {
	files  map[string][]byte
	ftimes map[string]fileTime
}

type mockFile struct {
	buffer *bytes.Buffer
}

type fileStat struct {
	name    string
	size    int64
	mode    FileMode
	modTime time.Time
	sys     syscall.Stat_t
}

func (o *mockOs) Open(name string) (File, error) {
	if data, ok := o.files[name]; !ok {
		return nil, os.ErrNotExist
	} else {
		return &mockFile{bytes.NewBuffer(data)}, nil
	}
}

func (o *fileStat) IsDir() bool {
	return false
}

func (o *fileStat) Mode() os.FileMode {
	return os.FileMode(o.mode)
}

func (o *fileStat) ModTime() time.Time {
	return o.modTime
}

func (o *fileStat) Name() string {
	return o.name
}

func (o *fileStat) Size() int64 {
	return o.size
}

func (o *fileStat) Sys() interface{} {
	return &o.sys
}

func (o *mockOs) Stat(name string) (os.FileInfo, error) {
	if data, ok := o.files[name]; !ok {
		return nil, os.ErrNotExist
	} else {
		var fs fileStat

		fs.mode = 436
		fs.modTime = o.ftimes[name].ModTime
		fs.name = name
		fs.size = int64(len(data))
		a, err := unix.TimeToTimespec(o.ftimes[name].ModTime)
		if err != nil {
			return nil, err
		}
		fs.sys.Atim.Sec = a.Sec
		fs.sys.Ctim.Sec = a.Sec

		return &fs, nil
	}
}

func (o *mockOs) IsExist(err error) bool {

	if err == nil {
		return false
	}
	if err.Error() == "exists" {
		return true
	}
	return false
}

func (f *mockFile) Close() error {
	return nil
}

func (f *mockFile) Read(p []byte) (n int, err error) {
	return f.buffer.Read(p)
}

// MockFile creates new mock file with the specified path and contents.
func (o *mockOs) MockFile(path string, data []byte) {
	o.files[path] = data
	var ft fileTime
	ft.ModTime = time.Now()
	o.ftimes[path] = ft
}

// NewMockOs returns Os interface that replaces supported os package functionality with mock functions.
func NewMockOs() Os {
	return &mockOs{
		files:  make(map[string][]byte),
		ftimes: make(map[string]fileTime),
	}
}
