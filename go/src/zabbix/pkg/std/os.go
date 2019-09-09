/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"errors"
	"io"
	"os"
	"syscall"
	"time"
)

// File interface is used to mock os.File structure
type File interface {
	io.Reader
	io.Closer
}

// Os interface is used to mock os package
type Os interface {
	Open(name string) (File, error)
	Stat(name string) (os.FileInfo, error)
	IsExist(err error) bool
}

// A FileMode represents a file's mode and permission bits.
type FileMode uint32

// wrappers for standard os functionality

type sysOs struct {
}

func (o *sysOs) Open(name string) (File, error) {
	return os.Open(name)
}

func (o *sysOs) Stat(name string) (os.FileInfo, error) {
	return os.Stat(name)
}

func (o *sysOs) IsExist(err error) bool {
	return os.IsExist(err)
}

// mocked os functionality

type MockOs interface {
	MockFile(path string, data []byte)
}

type fileTime struct {
	ModTime time.Time
	acTime  int64
	chTime  int64
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
		return nil, errors.New("file does not exist")
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
		return nil, errors.New("file does not exist")
	} else {
		var fs fileStat

		fs.mode = 436
		fs.modTime = o.ftimes[name].ModTime
		fs.name = name
		fs.size = int64(len(data))
		fs.sys.Atim.Sec = o.ftimes[name].acTime
		fs.sys.Ctim.Sec = o.ftimes[name].chTime

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
	ft.acTime = ft.ModTime.Unix()
	ft.chTime = ft.ModTime.Unix()
	o.ftimes[path] = ft
}

// NewOs returns Os interface that forwards supported methods to os package.
func NewOs() Os {
	return &sysOs{}
}

// NewMockOs returns Os interface that replaces supported os package functionality with mock functions.
func NewMockOs() Os {
	return &mockOs{
		files:  make(map[string][]byte),
		ftimes: make(map[string]fileTime),
	}
}
