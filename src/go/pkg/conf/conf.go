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

// Package conf provides .conf file loading and unmarshalling
package conf

import (
	"bytes"
	"encoding/base64"
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

// Meta structure is used to store the 'conf' tag metadata.
type Meta struct {
	name         string
	defaultValue *string
	optional     bool
	min          int64
	max          int64
}
type Suffix struct {
	suffix string
	factor int
}

var currentConfigPath string

// setCurrentConfigPath sets a path of the current config file.
func setCurrentConfigPath(path string) {
	currentConfigPath = path
}

// GetCurrentConfigPath returns a path of the current config file.
func GetCurrentConfigPath() string {
	return currentConfigPath
}

func validateParameterName(key []byte) (err error) {
	for i, b := range key {
		if ('A' > b || b > 'Z') && ('a' > b || b > 'z') && ('0' > b || b > '9') && b != '_' && b != '.' {
			return fmt.Errorf("invalid character '%c' at position %d", b, i+1)
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
// The metadata has format [name=<name>,][optional,][range=<range>,][default=<default value>]
//   where:
//   <name> - the parameter name,
//   optional - set if the value is optional,
//   <range> - the allowed range <min>:<max>, where <min>, <max> values are optional,
//   <default value> - the default value. If specified it must always be the last tag.
func getMeta(field reflect.StructField) (meta *Meta, err error) {
	m := Meta{name: "", optional: false, min: -1, max: -1}
	conf := field.Tag.Get("conf")

loop:
	for conf != "" {
		tags := strings.SplitN(conf, ",", 2)
		fields := strings.SplitN(tags[0], "=", 2)
		tag := strings.TrimSpace(fields[0])
		if len(fields) == 1 {
			// boolean tag
			switch tag {
			case "optional":
				m.optional = true
			default:
				return nil, fmt.Errorf("invalid conf tag '%s'", tag)
			}
		} else {
			// value tag
			switch tag {
			case "default":
				value := fields[1]
				if len(tags) == 2 {
					value += "," + tags[1]
				}
				m.defaultValue = &value
				break loop
			case "name":
				m.name = strings.TrimSpace(fields[1])
			case "range":
				limits := strings.Split(fields[1], ":")
				if len(limits) != 2 {
					return nil, errors.New("invalid range format")
				}
				if limits[0] != "" {
					m.min, _ = strconv.ParseInt(limits[0], 10, 64)
				}
				if limits[1] != "" {
					m.max, _ = strconv.ParseInt(limits[1], 10, 64)
				}
			default:
				return nil, fmt.Errorf("invalid conf tag '%s'", tag)
			}
		}

		if len(tags) == 1 {
			break
		}
		conf = tags[1]
	}

	if m.name == "" {
		m.name = field.Name
	}
	return &m, nil
}

func getTimeSuffix(str string) (string, int) {
	suffixes := []Suffix{
		Suffix{
			suffix: "s",
			factor: 1,
		},
		Suffix{
			suffix: "m",
			factor: 60,
		},
		Suffix{
			suffix: "h",
			factor: 3600,
		},
		Suffix{
			suffix: "d",
			factor: 86400,
		},
		Suffix{
			suffix: "w",
			factor: (7 * 86400),
		},
	}

	for _, s := range suffixes {
		if strings.HasSuffix(str, s.suffix) == true {
			str = strings.TrimSuffix(str, s.suffix)
			return str, s.factor
		}
	}
	return str, 1

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
		var r int
		*str, r = getTimeSuffix(*str)
		if v, err = strconv.ParseInt(*str, 10, 64); err == nil {
			v = v * int64(r)
			if meta != nil {
				if meta.min != -1 && v < meta.min || meta.max != -1 && v > meta.max {
					return errors.New("value out of range")
				}
			}
			value.SetInt(v)
		}
	case reflect.Uint, reflect.Uint8, reflect.Uint16, reflect.Uint32, reflect.Uint64:
		var v uint64
		var r int
		*str, r = getTimeSuffix(*str)
		if v, err = strconv.ParseUint(*str, 10, 64); err == nil {
			v = v * uint64(r)
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
		switch *str {
		case "true":
			v = true
		case "false":
			v = false
		default:
			return errors.New("invalid boolean value")
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
	for _, v := range node.Nodes {
		if child, ok := v.(*Node); ok {
			k := reflect.New(value.Type().Key())
			if err = setBasicValue(k.Elem(), nil, &child.Name); err != nil {
				return
			}
			v := reflect.New(value.Type().Elem())
			if err = setValue(v.Elem(), nil, child); err != nil {
				return
			}
			m.SetMapIndex(k.Elem(), v.Elem())
		}
	}
	value.Set(m)
	return
}

func setSliceValue(value reflect.Value, node *Node) (err error) {
	tmpValues := make([][]byte, 0)
	for _, v := range node.Nodes {
		if val, ok := v.(*Value); ok {
			tmpValues = append(tmpValues, val.Value)
		}
	}
	size := len(tmpValues)
	values := reflect.MakeSlice(reflect.SliceOf(value.Type().Elem()), 0, size)

	if len(tmpValues) > 0 {
		for _, data := range tmpValues {
			v := reflect.New(value.Type().Elem())
			str := string(data)
			if err = setBasicValue(v.Elem(), nil, &str); err != nil {
				return
			}
			values = reflect.Append(values, v.Elem())
		}
	} else {
		for _, n := range node.Nodes {
			if child, ok := n.(*Node); ok {
				v := reflect.New(value.Type().Elem())
				if err = setValue(v.Elem(), nil, child); err != nil {
					return
				}
				values = reflect.Append(values, v.Elem())
			}
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
		if str, err = node.getValue(meta); err == nil {
			if err = setBasicValue(value, meta, str); err != nil {
				return node.newError("%s", err.Error())
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
	case reflect.Interface:
		value.Set(reflect.ValueOf(node))
		node.markUsed(true)
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
	path = filepath.Clean(path)
	if err := checkGlobPattern(path); err != nil {
		return newIncludeError(root, &path, err.Error())
	}

	absPath, err := filepath.Abs(path)
	if err != nil {
		return newIncludeError(root, &path, err.Error())
	}

	// If a path is relative, pad it with a directory of the current config file
	if path != absPath {
		confDir := filepath.Dir(GetCurrentConfigPath())
		path = filepath.Join(confDir, path)
	}

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

func checkGlobPattern(path string) error {
	if strings.HasPrefix(path, "*") {
		return errors.New("path should be absolute")
	}

	var isGlob, hasSepLeft, hasSepRight bool

	for _, p := range path {
		switch p {
		case '*':
			isGlob = true
		case filepath.Separator:
			switch isGlob {
			case true:
				hasSepRight = true
			case false:
				hasSepLeft = true
			}
		}
	}

	if (isGlob && !hasSepLeft && hasSepRight) || (isGlob && !hasSepLeft && !hasSepRight) {
		return errors.New("path should be absolute")
	}

	return nil
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

func addObject(parent *Node, v interface{}) error {
	if attr, ok := v.(map[string]interface{}); ok {
		if _, ok := attr["Nodes"]; ok {
			node := &Node{}
			if err := setObjectNode(node, attr); err != nil {
				return err
			}
			parent.Nodes = append(parent.Nodes, node)
		} else {
			value := &Value{}
			if err := setObjectValue(value, attr); err != nil {
				return err
			}
			parent.Nodes = append(parent.Nodes, value)
		}
	} else {
		return fmt.Errorf("invalid object type %T", v)
	}
	return nil
}

func setObjectValue(value *Value, attr map[string]interface{}) error {
	var line float64
	var ok bool
	if line, ok = attr["Line"].(float64); !ok {
		return fmt.Errorf("invalid line attribute type %T", attr["Line"])
	}
	value.Line = int(line)

	var err error
	var data string
	if data, ok = attr["Value"].(string); !ok {
		return fmt.Errorf("invalid value type %T", attr["Value"])
	}

	if value.Value, err = base64.StdEncoding.DecodeString(data); err != nil {
		return err
	}

	return nil
}

func setObjectNode(node *Node, attr map[string]interface{}) error {
	var line float64
	var ok bool

	if line, ok = attr["Line"].(float64); !ok {
		return fmt.Errorf("invalid line attribute type %T", attr["Line"])
	}
	node.Line = int(line)

	if node.Name, ok = attr["Name"].(string); !ok {
		return fmt.Errorf("invalid node name type %T", attr["Name"])
	}

	var nodes []interface{}
	if nodes, ok = attr["Nodes"].([]interface{}); !ok {
		return fmt.Errorf("invalid node children type %T", attr["u"])
	}

	for _, a := range nodes {
		if err := addObject(node, a); err != nil {
			return err
		}
	}

	return nil
}

// Unmarshal unmarshals input data into specified structure. The input data can be either
// a byte array ([]byte) with configuration file or interface{} either returned by Marshal
// or a configuration file Unmarshaled into interface{} variable before.
// The third is optional 'strict' parameter that forces strict validation of configuration
// and structure fields (enabled by default). When disabled it will unmarshal part of
// configuration into incomplete target structures.
func Unmarshal(data interface{}, v interface{}, args ...interface{}) (err error) {
	rv := reflect.ValueOf(v)
	if rv.Kind() != reflect.Ptr || rv.IsNil() {
		return errors.New("Invalid output parameter")
	}

	strict := true
	if len(args) > 0 {
		var ok bool
		if strict, ok = args[0].(bool); !ok {
			return errors.New("Invalid mode parameter")
		}
	}

	var root *Node
	switch u := data.(type) {
	case nil:
		root = &Node{
			Name:   "",
			used:   false,
			Nodes:  make([]interface{}, 0),
			parent: nil,
			Line:   0}
	case []byte:
		root = &Node{
			Name:   "",
			used:   false,
			Nodes:  make([]interface{}, 0),
			parent: nil,
			Line:   0}

		if err = parseConfig(root, u); err != nil {
			return fmt.Errorf("Cannot read configuration: %s", err.Error())
		}
	case *Node:
		root = u
		root.markUsed(false)
	case map[string]interface{}: // JSON unmarshaling result
		root = &Node{}
		if err = setObjectNode(root, u); err != nil {
			return fmt.Errorf("Cannot unmarshal JSON data: %s", err)
		}
	default:
		return fmt.Errorf("Invalid input parameter of type %T", u)
	}

	if !strict {
		root.markUsed(true)
	}

	if err = assignValues(v, root); err != nil {
		return fmt.Errorf("Cannot assign configuration: %s", err.Error())
	}

	return nil
}

func Load(filename string, v interface{}) (err error) {
	var file std.File

	if file, err = stdOs.Open(filename); err != nil {
		return fmt.Errorf(`cannot open configuration file: %s`, err.Error())
	}
	defer file.Close()

	buf := bytes.Buffer{}
	if _, err = buf.ReadFrom(file); err != nil {
		return fmt.Errorf("cannot load configuration: %s", err.Error())
	}

	setCurrentConfigPath(filename)

	return Unmarshal(buf.Bytes(), v)
}

func LoadUserParams(v interface{}) (err error) {
	var file std.File

	if file, err = stdOs.Open(currentConfigPath); err != nil {
		return fmt.Errorf(`cannot open configuration file: %s`, err.Error())
	}
	defer file.Close()

	buf := bytes.Buffer{}
	if _, err = buf.ReadFrom(file); err != nil {
		return fmt.Errorf("cannot load configuration: %s", err.Error())
	}

	return Unmarshal(buf.Bytes(), v, false)
}

var stdOs std.Os

func init() {
	stdOs = std.NewOs()
}
