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

package itemutil

import (
	"bytes"
	"errors"
	"fmt"
)

func isKeyChar(c byte, wildcard bool) bool {
	if c >= 'a' && c <= 'z' {
		return true
	}
	if c == '.' || c == '-' || c == '_' {
		return true
	}
	if c >= '0' && c <= '9' {
		return true
	}
	if c >= 'A' && c <= 'Z' {
		return true
	}
	if wildcard && c == '*' {
		return true
	}
	return false
}

// parseQuotedParam parses item key quoted parameter "..." and returns
// the parsed parameter (including quotes, but without whitespace outside quotes)
// and the data after the parameter (skipping also whitespace after closing quotes).
func parseQuotedParam(data []byte) (param []byte, left []byte, err error) {
	var last byte
	for i, c := range data[1:] {
		if c == '"' && last != '\\' {
			i += 2
			param = data[:i]
			for ; i < len(data) && data[i] == ' '; i++ {
			}
			left = data[i:]
			return
		}
		last = c
	}
	err = errors.New("unterminated quoted string")
	return
}

// parseUnquotedParam parses item key normal parameter (any combination of any characters except ',' and ']',
// including trailing whitespace) and returns the parsed parameter and the data after the parameter.
func parseUnquotedParam(data []byte) (param []byte, left []byte, err error) {
	for i, c := range data {
		if c == ',' || c == ']' {
			param = data[:i]
			left = data[i:]
			return
		}
	}
	err = errors.New("unterminated parameter")
	return
}

// parseArrayParam parses item key array parameter [...] and returns.
func parseArrayParam(data []byte) (param []byte, left []byte, err error) {
	var pos int
	b := data[1:]

	for len(b) > 0 {
	loop:
		for i, c := range b {
			switch c {
			case ' ':
				continue
			case '"':
				if _, b, err = parseQuotedParam(b[i:]); err != nil {
					return
				}
				break loop
			case '[':
				err = errors.New("nested arrays are not supported")
				return
			default:
				if _, b, err = parseUnquotedParam(b[i:]); err != nil {
					return
				}
				break loop
			}
		}
		if len(b) == 0 {
			err = errors.New("unterminated array parameter")
			return
		}
		if b[0] == ']' {
			pos = cap(data) - cap(b)
			left = b[1:]
			break
		}
		if b[0] != ',' {
			err = errors.New("unterminated array parameter")
			return
		}
		b = b[1:]
	}
	if left == nil {
		err = errors.New("unterminated array parameter X")
		return
	}
	param = data[:pos+1]
	return
}

// unquoteParam unquotes quoted parameter by removing enclosing double quotes '"' and
// unescaping '\"' escape sequences.
func unquoteParam(data []byte) (param []byte) {
	param = make([]byte, 0, len(data))
	var last byte
	for _, c := range data[1:] {
		switch c {
		case '"':
			if last != '\\' {
				return
			}
			param = append(param, c)
		case '\\':
			if last == '\\' {
				param = append(param, '\\')
			}
		default:
			if last == '\\' {
				param = append(param, '\\')
			}
			param = append(param, c)
		}
		last = c
	}
	return
}

// expandArray expands array parameter by removing enclosing brackets '[]' and,
// removing whitespace before normal array items and around quoted items.
func expandArray(data []byte) (param []byte) {
	param = make([]byte, 0, len(data))
	var p []byte
	b := data[1:]
	for len(b) > 0 {
	loop:
		for i, c := range b {
			switch c {
			case ' ':
				continue
			case '"':
				p, b, _ = parseQuotedParam(b[i:])
				break loop
			default:
				p, b, _ = parseUnquotedParam(b[i:])
				break loop
			}
		}
		param = append(param, p...)
		if b[0] == ']' {
			break
		}
		param = append(param, ',')
		b = b[1:]
	}
	return
}

// parseParams parses single item key parameter.
func parseParam(data []byte) (param []byte, left []byte, err error) {
	for i, c := range data {
		switch c {
		case ' ':
			continue
		case '"':
			if param, left, err = parseQuotedParam(data[i:]); err == nil {
				param = unquoteParam(param)
			}
			return
		case '[':
			if param, left, err = parseArrayParam(data[i:]); err == nil {
				param = expandArray(param)
			}
			return
		case ']', ',':
			return data[i:i], data[i:], nil
		default:
			param, left, err = parseUnquotedParam(data[i:])
			return
		}
	}
	err = errors.New("unterminated parameter list")
	return
}

// parseParams parses item key parameters.
func parseParams(data []byte) (params []string, left []byte, err error) {
	if data[0] != '[' {
		err = fmt.Errorf("key name must be followed by '['")
		return
	}
	if len(data) == 1 {
		err = fmt.Errorf("unterminated parameter list")
		return
	}
	var param []byte
	b := data[1:]
	for len(b) > 0 {
		if param, b, err = parseParam(b); err != nil {
			return
		}
		if len(b) == 0 {
			err = errors.New("key parameters ended unexpectedly")
			return
		}
		if b[0] == ']' {
			if len(b) > 1 {
				left = b[1:]
			}
			break
		}
		if b[0] != ',' {
			err = errors.New("invalid parameter separator")
			return
		}
		b = b[1:]
		params = append(params, string(param))
	}
	if len(b) == 0 {
		err = fmt.Errorf("unterminated parameter list")
	}
	params = append(params, string(param))
	return
}

func newKeyError() (err error) {
	return errors.New("Invalid item key format.")
}

func parseKey(data []byte, wildcard bool) (key string, params []string, left []byte, err error) {
	for i, c := range data {
		if !isKeyChar(c, wildcard) {
			if i == 0 {
				err = newKeyError()
				return
			}
			if c != '[' {
				key = string(data[:i])
				left = data[i:]
				return
			}
			if params, left, err = parseParams(data[i:]); err != nil {
				err = newKeyError()
				return
			}
			key = string(data[:i])
			return
		}
	}
	key = string(data)
	return
}

func parseMetricKey(text string, wildcard bool) (key string, params []string, err error) {
	if text == "" {
		err = newKeyError()
		return
	}
	var left []byte
	if key, params, left, err = parseKey([]byte(text), wildcard); err != nil {
		return
	}
	if len(left) > 0 {
		err = newKeyError()
	}
	return
}

// ParseKey parses item key in format key[param1, param2, ...] and returns
// the parsed key and parameteres.
func ParseKey(text string) (key string, params []string, err error) {
	return parseMetricKey(text, false)
}

// ParseWildcardKey parses item key in format key[param1, param2, ...] and returns
// the parsed key and parameteres.
func ParseWildcardKey(text string) (key string, params []string, err error) {
	return parseMetricKey(text, true)
}

// ParseAlias parses Alias in format name:key and returns the name
// and the key separately without changes
func ParseAlias(text string) (key1, key2 string, err error) {
	var left, left2 []byte
	if _, _, left, err = parseKey([]byte(text), false); err != nil {
		return
	}
	if len(left) < 2 || left[0] != ':' {
		err = fmt.Errorf("syntax error")
		return
	}
	key1 = text[:len(text)-len(left)]
	if _, _, left2, err = parseKey(left[1:], false); err != nil {
		return
	}
	if len(left2) != 0 {
		err = fmt.Errorf("syntax error")
		return
	}
	key2 = string(left[1:])
	return
}

func mustQuote(param string) bool {
	if len(param) > 0 && (param[0] == '"' || param[0] == ' ') {
		return true
	}
	for _, b := range param {
		switch b {
		case ',', ']':
			return true
		}
	}
	return false
}

func quoteParam(buf *bytes.Buffer, param string) {
	buf.WriteByte('"')
	for _, b := range param {
		if b == '"' {
			buf.WriteByte('\\')
		}
		buf.WriteRune(b)
	}
	buf.WriteByte('"')
}

func MakeKey(key string, params []string) (text string) {
	buf := bytes.Buffer{}
	buf.WriteString(key)

	if len(params) > 0 {
		buf.WriteByte('[')
		for i, p := range params {
			if i != 0 {
				buf.WriteByte(',')
			}
			if !mustQuote(p) {
				buf.WriteString(p)
			} else {
				quoteParam(&buf, p)
			}
		}
		buf.WriteByte(']')
	}
	return buf.String()
}

func CompareKeysParams(key1 string, params1 []string, key2 string, params2 []string) bool {
	if key1 != key2 {
		return false
	}
	if len(params1) != len(params2) {
		return false
	}

	for i := 0; i < len(params1); i++ {
		if params1[i] != params2[i] {
			return false
		}
	}
	return true
}
