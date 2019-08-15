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

package filecontents

//#include <iconv.h>
//#include <stdlib.h>
//size_t call_iconv(iconv_t cd, char *inbuf, size_t *inbytesleft, char *outbuf, size_t *outbytesleft) {
//   return iconv(cd, &inbuf, inbytesleft, &outbuf, outbytesleft);
// }
import "C"

import (
	"bytes"
	"errors"
	"fmt"
	"syscall"
	"unsafe"
	"zabbix/internal/plugin"
	"zabbix/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func decode(encoder string, inbuf []byte) (outbuf []byte) {

	if "" == encoder {
		return inbuf
	}

	tocode := C.CString("UTF-8")
	defer C.free(unsafe.Pointer(tocode))
	fromcode := C.CString(encoder)
	defer C.free(unsafe.Pointer(fromcode))

	cd, err := C.iconv_open(tocode, fromcode)

	if err != nil {
		return inbuf
	}

	outbuf = make([]byte, len(inbuf))
	inbytes := C.size_t(len(inbuf))
	outbytes := C.size_t(len(inbuf))

	for {
		inptr := (*C.char)(unsafe.Pointer(&inbuf[len(inbuf)-int(inbytes)]))
		outptr := (*C.char)(unsafe.Pointer(&outbuf[len(outbuf)-int(outbytes)]))
		_, err := C.call_iconv(cd, inptr, &inbytes, outptr, &outbytes)
		if err == nil || err.(syscall.Errno) != syscall.E2BIG {
			break
		}
		outbytes += C.size_t(len(inbuf))
		tmp := make([]byte, len(outbuf)+len(inbuf))
		copy(tmp, outbuf)
		outbuf = tmp
	}
	outbuf = outbuf[:len(outbuf)-int(outbytes)]
	C.iconv_close(cd)
	return
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {

	if len(params) != 1 && len(params) != 2 {
		return nil, errors.New("Wrong number of parameters")
	}

	var encoder string

	if len(params) == 2 {
		encoder = params[1]
	}

	f, err := stdOs.Stat(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain file %s information: %s", params[0], err)
	}
	filelen := f.Size()

	bnum := 64 * 1024
	if filelen > int64(bnum) {
		return nil, errors.New("File is too large for this check")
	}

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	buf := bytes.Buffer{}
	if _, err = buf.ReadFrom(file); err != nil {
		return nil, fmt.Errorf("Cannot read from file: %s", err)
	}

	outbuf := decode(encoder, buf.Bytes())

	return string(bytes.TrimRight(outbuf, "\n\r")), nil

}

var stdOs std.Os

func init() {
	plugin.RegisterMetric(&impl, "contents", "vfs.file.contents", "Retrieves contents of the file")
	stdOs = std.NewOs()
}
