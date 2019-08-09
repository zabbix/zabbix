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
	"time"
	"unsafe"
	"zabbix/internal/agent"
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

	inlen := len(inbuf)
	outlen := 4 * inlen
	locbuf := make([]byte, outlen)
	inbytes := C.size_t(inlen)
	inptr := &inbuf[0]
	outbytes := C.size_t(outlen)
	outptr := &locbuf[0]

	for inbytes > 0 {
		C.call_iconv(cd,
			(*C.char)(unsafe.Pointer(inptr)), &inbytes,
			(*C.char)(unsafe.Pointer(outptr)), &outbytes)
		outptr = &locbuf[C.size_t(outlen)-outbytes]
	}

	C.iconv_close(cd)
	return locbuf[:outlen-int(outbytes)]
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {

	if len(params) != 1 && len(params) != 2 {
		return nil, errors.New("Wrong number of parameters")
	}
	start := time.Now()

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

	buf := make([]byte, filelen)

	bnum, err = file.Read(buf)

	outbuf := decode(encoder, buf)

	elapsed := time.Since(start)
	if elapsed.Seconds() > float64(agent.Options.Timeout) {
		return nil, errors.New("Timeout while processing item")
	}
	if err != nil {
		return nil, errors.New("Cannot read from file")
	}

	return string(bytes.TrimRight(outbuf, "\n\r")), nil

}

var stdOs std.Os

func init() {
	plugin.RegisterMetric(&impl, "contents", "vfs.file.contents", "Retrieves contents of the file")
	stdOs = std.NewOs()
}
