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

// Package conf provides .conf file loading and unmarshaling
package conf

import (
	"bytes"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"reflect"
	"runtime"
	"strconv"
	"strings"
	"unicode/utf8"

	"zabbix.com/pkg/std"
)

// Node structure is used to store parsed conf file parameters or parameter components.
type Node struct {
	name        string
	used        bool
	values      [][]byte
	nodes       []*Node
	parent      *Node
	line        int
	level       int
	includeFail bool
}

// Meta structure is used to stroe the 'conf' tag metadata.
type Meta struct {
	name         string
	defaultValue *string
	optional     bool
	min          int64
	max          int64
}

// get returns child node by name
func (n *Node) get(name string) (node *Node) {
	for _, child := range n.nodes {
		if child.name == name {
			return child
		}
	}
	return nil
}

func validateParameterName(key []byte) (err error) {
	for i, b := range key {
		if ('A' > b || b > 'Z') && ('a' > b || b > 'z') && ('0' > b || b > '9') && b != '_' && b != '.' {
			return fmt.Errorf("invalid character '%c' at position %d", b, i+1)
		}
	}
	return
}

// add appends new child node
func (n *Node) add(name []byte, value []byte, lineNum int) {
	var node *Node
	var key string

	split := bytes.IndexByte(name, '.')
	if split == -1 {
		key = string(name)
	} else {
		key = string(name[:split])
	}

	if node = n.get(key); node == nil {
		node = &Node{
			name:   string(key),
			used:   false,
			values: make([][]byte, 0),
			nodes:  make([]*Node, 0),
			parent: n,
			line:   lineNum}
		n.nodes = append(n.nodes, node)
	}

	if split != -1 {
		node.add(name[split+1:], value, lineNum)
	} else {
		node.values = append(node.values, value)
	}
}

// checkUsage checks if all conf nodes were recognized.
// This is done by recursively checking 'used' flag for all nodes.
func (n *Node) checkUsage() (err error) {
	for _, node := range n.nodes {
		if !node.used {
			return newNodeError(node, "unknown parameter")
		}
		if err = node.checkUsage(); err != nil {
			return
		}
	}
	return
}

// parseLine parses parameter configuration line and returns key,value pair.
// The line must have format: <key>[ ]=[ ]<value> where whitespace surrounding
// '=' is optional.
func parseLine(line []byte) (key []byte, value []byte, err error) {
	valueStart := bytes.IndexByte(line, '=')
	if valueStart == -1 {
		return nil, nil, errors.New("missing assignment operator")
	}

	if key = bytes.TrimSpace(line[:valueStart]); len(key) == 0 {
		return nil, nil, errors.New("missing variable name")
	}

	if err = validateParameterName(key); err != nil {
		return
	}

	return key, bytes.TrimSpace(line[valueStart+1:]), nil
}

// getMeta returns 'conf' tag metadata.
// The metadata has format <name>,<optional>,<range>,<default value>
//   where:
//   <name> - the parameter name,
//   <optional> - string 'optional' if the value is optional,
//   <range> - the allowed range <min>:<max>, where <min>, <max> values are optional,
//   <default value> - the default value.
func getMeta(field reflect.StructField) (meta *Meta, err error) {
	t := field.Tag
	m := Meta{name: "", optional: false, min: -1, max: -1}
	tag := t.Get("conf")
	if tag != "" {
		tags := strings.SplitN(tag, ",", 4)
		switch len(tags) {
		case 4:
			m.defaultValue = &tags[3]
			fallthrough
		case 3:
			rangeTag := strings.Trim(tags[2], " \t")
			if len(rangeTag) > 0 {
				limits := strings.Split(rangeTag, ":")
				if len(limits) != 2 {
					return nil, errors.New("invalid range tag format")
				}
				if limits[0] != "" {
					m.min, _ = strconv.ParseInt(limits[0], 10, 64)
				}
				if limits[1] != "" {
					m.max, _ = strconv.ParseInt(limits[1], 10, 64)
				}
			}
			fallthrough
		case 2:
			if strings.Trim(tags[1], " \t") == "optional" {
				m.optional = true
			} else if tags[1] != "" {
				return nil, fmt.Errorf("unknown 'conf' tag: %s", tags[1])
			}
			fallthrough
		case 1:
			m.name = strings.Trim(tags[0], " \t")
		}
	}
	if m.name == "" {
		m.name = field.Name
	}
	return &m, nil
}

// getNodeValue returns node value or meta data default value or nil if
// metadata 'optional' tag is set. Otherwise error is returned.
func getNodeValue(node *Node, meta *Meta) (value *string, err error) {
	if node != nil {
		count := len(node.values)
		if count > 0 {
			tmp := string(node.values[count-1])
			value = &tmp
		}
	}

	if value == nil && meta != nil {
		if meta.defaultValue != nil {
			value = meta.defaultValue
		} else if meta.optional {
			return
		} else {
			return nil, fmt.Errorf("cannot find mandatory parameter %s", meta.name)
		}
	}
	return
}

// newNodeError creates error based on the specified node. The error message will
// have full node name (parameter name up to the node, including it) and the line
// number where parameter was defined.
func newNodeError(node *Node, format string, a ...interface{}) (err error) {
	if node == nil {
		return fmt.Errorf(format, a...)
	}
	var name string
	for parent := node; parent.parent != nil; parent = parent.parent {
		if name == "" {
			name = parent.name
		} else {
			name = parent.name + "." + name
		}
	}
	desc := fmt.Sprintf(format, a...)
	return fmt.Errorf("invalid parameter %s at line %d: %s", name, node.line, desc)
}

func setBasicValue(value reflect.Value, meta *Meta, str *string) (err error) {
	if str == nil {
		return nil
	}
	switch value.Type().Kind() {
	case reflect.String:
		value.SetString(*str)
	case reflect.Int, reflect.Int8, reflect.Int16, reflect.Int32, reflect.Int64:
		var v int64
		if v, err = strconv.ParseInt(*str, 10, 64); err == nil {
			if meta != nil {
				if meta.min != -1 && v < meta.min || meta.max != -1 && v > meta.max {
					return errors.New("value out of range")
				}
			}
			value.SetInt(v)
		}
	case reflect.Uint, reflect.Uint8, reflect.Uint16, reflect.Uint32, reflect.Uint64:
		var v uint64
		if v, err = strconv.ParseUint(*str, 10, 64); err == nil {
			if meta != nil {
				if meta.min != -1 && v < uint64(meta.min) || meta.max != -1 && v > uint64(meta.max) {
					return errors.New("value out of range")
				}
			}
			value.SetUint(v)
		}
	case reflect.Float32, reflect.Float64:
		var v float64
		if v, err = strconv.ParseFloat(*str, 64); err == nil {
			if meta != nil {
				if meta.min != -1 && v < float64(meta.min) || meta.max != -1 && v > float64(meta.max) {
					return errors.New("value out of range")
				}
			}
			value.SetFloat(v)
		}
	case reflect.Bool:
		var v bool
		if *str == "true" {
			v = true
		}
		value.SetBool(v)
	case reflect.Ptr:
		v := reflect.New(value.Type().Elem())
		value.Set(v)
		return setBasicValue(v.Elem(), meta, str)
	default:
		err = fmt.Errorf("unsupported variable type %v", value.Type().Kind())
	}
	return err
}

func setStructValue(value reflect.Value, node *Node) (err error) {
	rt := value.Type()
	for i := 0; i < rt.NumField(); i++ {
		var meta *Meta
		if meta, err = getMeta(rt.Field(i)); err != nil {
			return
		}
		child := node.get(meta.name)
		if child != nil || meta.defaultValue != nil {
			if err = setValue(value.Field(i), meta, child); err != nil {
				return
			}
		} else if !meta.optional {
			return fmt.Errorf("cannot find mandatory parameter %s", meta.name)
		}
	}
	return
}

func setMapValue(value reflect.Value, node *Node) (err error) {
	m := reflect.MakeMap(reflect.MapOf(value.Type().Key(), value.Type().Elem()))
	for _, child := range node.nodes {
		k := reflect.New(value.Type().Key())
		if err = setBasicValue(k.Elem(), nil, &child.name); err != nil {
			return
		}
		v := reflect.New(value.Type().Elem())
		if err = setValue(v.Elem(), nil, child); err != nil {
			return
		}
		m.SetMapIndex(k.Elem(), v.Elem())
	}
	value.Set(m)
	return
}

func setSliceValue(value reflect.Value, node *Node) (err error) {
	size := len(node.values)
	values := reflect.MakeSlice(reflect.SliceOf(value.Type().Elem()), 0, size)

	if len(node.values) > 0 {
		for _, data := range node.values {
			v := reflect.New(value.Type().Elem())
			str := string(data)
			if err = setBasicValue(v.Elem(), nil, &str); err != nil {
				return
			}
			values = reflect.Append(values, v.Elem())
		}
	} else {
		for _, child := range node.nodes {
			v := reflect.New(value.Type().Elem())
			if err = setValue(v.Elem(), nil, child); err != nil {
				return
			}
			values = reflect.Append(values, v.Elem())
		}
	}
	value.Set(values)
	return
}

func setValue(value reflect.Value, meta *Meta, node *Node) (err error) {
	var str *string
	if node != nil {
		node.used = true
	}
	switch value.Type().Kind() {
	case reflect.Int, reflect.Int8, reflect.Int16, reflect.Int32, reflect.Int64,
		reflect.Uint, reflect.Uint8, reflect.Uint16, reflect.Uint32, reflect.Uint64,
		reflect.Float32, reflect.Float64, reflect.Bool, reflect.String:
		if str, err = getNodeValue(node, meta); err == nil {
			if err = setBasicValue(value, meta, str); err != nil {
				return newNodeError(node, "%s", err.Error())
			}
		}
	case reflect.Struct:
		if node != nil {
			return setStructValue(value, node)
		}
	case reflect.Map:
		if node != nil {
			return setMapValue(value, node)
		}
	case reflect.Slice:
		if node != nil {
			return setSliceValue(value, node)
		}
	case reflect.Ptr:
		v := reflect.New(value.Type().Elem())
		value.Set(v)
		return setValue(v.Elem(), meta, node)
	}

	return nil
}

// assignValues assigns parsed nodes to the specified structure
func assignValues(v interface{}, root *Node) (err error) {
	rv := reflect.ValueOf(v)

	switch rv.Type().Kind() {
	case reflect.Ptr:
		rv = rv.Elem()
	default:
		return errors.New("output variable must be a pointer to a structure")
	}

	switch rv.Type().Kind() {
	case reflect.Struct:
		if err = setStructValue(rv, root); err != nil {
			return err
		}
	default:
		return errors.New("output variable must be a pointer to a structure")
	}

	return root.checkUsage()
}

func newIncludeError(root *Node, filename *string, errmsg string) (err error) {
	if root.includeFail {
		return errors.New(errmsg)
	}
	root.includeFail = true
	if filename != nil {
		return fmt.Errorf(`cannot include "%s": %s`, *filename, errmsg)
	}
	return fmt.Errorf(`cannot load file: %s`, errmsg)
}

func hasMeta(path string) bool {
	var metaChars string
	if runtime.GOOS != "windows" {
		metaChars = `*?[\`
	} else {
		metaChars = `*?[`
	}
	return strings.ContainsAny(path, metaChars)
}

func loadInclude(root *Node, path string) (err error) {
	if hasMeta(filepath.Dir(path)) {
		return newIncludeError(root, &path, "glob pattern is supported only in file names")
	}
	if !hasMeta(path) {
		var fi os.FileInfo
		if fi, err = stdOs.Stat(path); err != nil {
			return newIncludeError(root, &path, err.Error())
		}
		if fi.IsDir() {
			path = filepath.Join(path, "*")
		}
	} else {
		var fi os.FileInfo
		if fi, err = stdOs.Stat(filepath.Dir(path)); err != nil {
			return newIncludeError(root, &path, err.Error())
		}
		if !fi.IsDir() {
			return newIncludeError(root, &path, "base path is not a directory")
		}
	}

	var paths []string
	if hasMeta(path) {
		if paths, err = filepath.Glob(path); err != nil {
			return newIncludeError(root, nil, err.Error())
		}
	} else {
		paths = append(paths, path)
	}

	for _, path := range paths {
		// skip directories
		var fi os.FileInfo
		if fi, err = stdOs.Stat(path); err != nil {
			return newIncludeError(root, &path, err.Error())
		}
		if fi.IsDir() {
			continue
		}
		if !filepath.IsAbs(path) {
			return newIncludeError(root, &path, "relative paths are not supported")
		}

		var file std.File
		if file, err = stdOs.Open(path); err != nil {
			return newIncludeError(root, &path, err.Error())
		}
		defer file.Close()

		buf := bytes.Buffer{}
		if _, err = buf.ReadFrom(file); err != nil {
			return newIncludeError(root, &path, err.Error())
		}

		if err = parseConfig(root, buf.Bytes()); err != nil {
			return newIncludeError(root, &path, err.Error())
		}
	}
	return
}

func parseConfig(root *Node, data []byte) (err error) {
	const maxStringLen = 2048
	var line []byte

	root.level++

	for offset, end, num := 0, 0, 1; end != -1; offset, num = offset+end+1, num+1 {
		if end = bytes.IndexByte(data[offset:], '\n'); end != -1 {
			line = bytes.TrimSpace(data[offset : offset+end])
		} else {
			line = bytes.TrimSpace(data[offset:])
		}

		if len(line) > maxStringLen {
			return fmt.Errorf("cannot parse configuration at line %d: limit of %d bytes is exceeded", num, maxStringLen)
		}

		if len(line) == 0 || line[0] == '#' {
			continue
		}

		if !utf8.ValidString(string(line)) {
			return fmt.Errorf("cannot parse configuration at line %d: not a valid UTF-8 character found", num)
		}

		var key, value []byte
		if key, value, err = parseLine(line); err != nil {
			return fmt.Errorf("cannot parse configuration at line %d: %s", num, err.Error())
		}
		if string(key) == "Include" {
			if root.level == 10 {
				return fmt.Errorf("include depth exceeded limits")
			}

			if err = loadInclude(root, string(value)); err != nil {
				return
			}
		} else {
			root.add(key, value, num)
		}
	}
	root.level--
	return nil
}

func Unmarshal(data []byte, v interface{}) (err error) {
	rv := reflect.ValueOf(v)
	if rv.Kind() != reflect.Ptr || rv.IsNil() {
		return errors.New("Invalid output parameter")
	}

	root := &Node{
		name:   "",
		used:   false,
		values: make([][]byte, 0),
		nodes:  make([]*Node, 0),
		parent: nil,
		line:   0}

	if err = parseConfig(root, data); err != nil {
		return fmt.Errorf("Cannot read configuration: %s", err.Error())
	}

	if err = assignValues(v, root); err != nil {
		return fmt.Errorf("Cannot read configuration: %s", err.Error())
	}

	return nil
}

func Load(filename string, v interface{}) (err error) {
	var file std.File

	if file, err = stdOs.Open(filename); err != nil {
		return fmt.Errorf("Cannot load configuration: %s", err.Error())
	}
	defer file.Close()

	buf := bytes.Buffer{}
	if _, err = buf.ReadFrom(file); err != nil {
		return fmt.Errorf("Cannot load configuration: %s", err.Error())
	}

	return Unmarshal(buf.Bytes(), v)
}

var stdOs std.Os

func init() {
	stdOs = std.NewOs()
}
