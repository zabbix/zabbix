/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
	"git.zabbix.com/ap/plugin-support/log"
	"syscall"
	"unsafe"
)

func findEncodingFromBOM(encoding string, inbuf []byte, bytecount int) string {
	/* try to guess encoding using Byte Order Mark (BOM) if it exists */
	if "" == encoding {
		if bytecount > 3 && 0xef == inbuf[0] && 0xbb == inbuf[1] && 0xbf == inbuf[2] {
			encoding = "UTF-8"
		} else if bytecount > 2 && 0xff == inbuf[0] && 0xfe == inbuf[1] {
			encoding = "UTF-16LE"
		} else if bytecount > 2 && 0xfe == inbuf[0] && 0xff == inbuf[1] {
			encoding = "UTF-16BE"
		}
	}

	return encoding
}

func decodeToUTF8(encoding string, inbuf []byte, bytecount int) (outbuf []byte, outbytecount int) {
	if bytecount == 0 {
		return inbuf, 0
	}
	if encoding == "" {
		return inbuf, bytecount
	}
	tocode := C.CString("UTF-8")
	log.Tracef("Calling C function \"free()\"")
	defer C.free(unsafe.Pointer(tocode))
	fromcode := C.CString(encoding)
	log.Tracef("Calling C function \"free()\"")
	defer C.free(unsafe.Pointer(fromcode))

	log.Tracef("Calling C function \"iconv_open()\"")
	cd, err := C.iconv_open(tocode, fromcode)

	if err != nil {
		return inbuf, bytecount
	}

	outbuf = make([]byte, bytecount)
	inbytes := C.size_t(bytecount)
	outbytes := C.size_t(bytecount)

	for {
		inptr := (*C.char)(unsafe.Pointer(&inbuf[bytecount-int(inbytes)]))
		outptr := (*C.char)(unsafe.Pointer(&outbuf[len(outbuf)-int(outbytes)]))

		log.Tracef("Calling C function \"call_iconv()\"")
		_, err := C.call_iconv(cd, inptr, &inbytes, outptr, &outbytes)
		if err == nil || err.(syscall.Errno) != syscall.E2BIG {
			break
		}

		outbytes += C.size_t(bytecount)
		tmp := make([]byte, len(outbuf)+bytecount)
		copy(tmp, outbuf)
		outbuf = tmp
	}

	outbuf = outbuf[:len(outbuf)-int(outbytes)]

	log.Tracef("Calling C function \"iconv_close()\"")
	C.iconv_close(cd)
	if len(outbuf) > 3 && 0xef == outbuf[0] && 0xbb == outbuf[1] && 0xbf == outbuf[2] {
		outbuf = outbuf[3:]
	}

	return outbuf, len(outbuf)
}
