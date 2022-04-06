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

package file

//#include <iconv.h>
//#include <stdlib.h>
//size_t call_iconv(iconv_t cd, char *inbuf, size_t *inbytesleft, char *outbuf, size_t *outbytesleft) {
//   return iconv(cd, &inbuf, inbytesleft, &outbuf, outbytesleft);
// }
//
// #cgo windows LDFLAGS: -liconv
import "C"

import (
	"syscall"
	"unsafe"
)

func decode(encoder string, inbuf []byte) (outbuf []byte) {

	if "" == encoder {
		if len(inbuf) > 3 && 0xef == inbuf[0] && 0xbb == inbuf[1] && 0xbf == inbuf[2] {
			encoder = "UTF-8"
		} else if len(inbuf) > 2 && 0xff == inbuf[0] && 0xfe == inbuf[1] {
			encoder = "UTF-16LE"
		} else if len(inbuf) > 2 && 0xfe == inbuf[0] && 0xff == inbuf[1] {
			encoder = "UTF-16BE"
		} else {
			return inbuf
		}
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
	if len(outbuf) > 3 && 0xef == outbuf[0] && 0xbb == outbuf[1] && 0xbf == outbuf[2] {
		outbuf = outbuf[3:]
	}
	return
}
