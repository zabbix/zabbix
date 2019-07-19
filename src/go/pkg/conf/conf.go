// Package conf provides .conf file loading and unmarshaling
package conf

import (
	"bytes"
	"errors"
	"fmt"
	"io/ioutil"
	"reflect"
	"strconv"
	"strings"
)

// Node structure is used to store parsed conf file parameters or parameter components.
type Node struct {
	name   string
	used   bool
	values [][]byte
	nodes  []*Node
	parent *Node
	line   int
}

// Meta structure is used to stroe the 'conf' tag metadata.
type Meta struct {
	name         string
	defaultValue *string
	optional     bool
	min          int64
	max          int64
}

func isWhitespace(b byte) bool {
	switch b {
	case ' ':
		return true
	case '\n':
		return true
	case '\r':
		return true
	case '\t':
		return true
	}
	return false
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

	keyTail := valueStart
	for keyTail > 0 && isWhitespace(line[keyTail-1]) {
		keyTail--
	}
	if keyTail == 0 {
		return nil, nil, errors.New("missing variable name")
	}

	valueStart++
	valueTail := len(line)
	for valueStart < valueTail && isWhitespace(line[valueStart]) {
		valueStart++
	}
	if valueStart == valueTail {
		return nil, nil, errors.New("missing variable value")
	}

	if err = validateParameterName(line[:keyTail]); err != nil {
		return
	}

	return line[:keyTail], line[valueStart:], nil
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
			limits := strings.Split(tags[2], ":")
			if len(limits) > 0 && limits[0] != "" {
				m.min, _ = strconv.ParseInt(limits[0], 10, 64)
			}
			if len(limits) > 1 && limits[1] != "" {
				m.max, _ = strconv.ParseInt(limits[1], 10, 64)
			}
			fallthrough
		case 2:
			if tags[1] == "optional" {
				m.optional = true
			} else if tags[1] != "" {
				return nil, fmt.Errorf("unknown 'conf' tag: %s", tags[1])
			}
			fallthrough
		case 1:
			m.name = tags[0]
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
// have full node name (parametere name up to the node, including it) and the line
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

func parseConfig(root *Node, data []byte) (err error) {
	var tail int
	for offset, end, num := 0, 0, 1; end != -1; offset, num = offset+end+1, num+1 {
		end = bytes.IndexByte(data[offset:], '\n')
		if end != -1 {
			tail = offset + end
		} else {
			tail = len(data)
		}
		start := offset
		for start < tail && isWhitespace(data[start]) {
			start++
		}
		if start == tail || data[start] == '#' {
			continue
		}
		for start < tail && isWhitespace(data[tail-1]) {
			tail--
		}
		var key, value, inc []byte
		if key, value, err = parseLine(data[start:tail]); err != nil {
			return fmt.Errorf("Cannot parse configuration at line %d: %s", num, err.Error())
		}
		if string(key) == "Include" {
			filename := string(value)
			if inc, err = ioutil.ReadFile(filename); err != nil {
				return fmt.Errorf("Cannot read include file %s: %s", filename, err.Error())
			}
			if err = parseConfig(root, inc); err != nil {
				return fmt.Errorf("Cannot parse include file %s: %s", filename, err.Error())
			}
		} else {
			root.add(key, value, num)
		}
	}
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
		return err
	}

	if err = assignValues(v, root); err != nil {
		return fmt.Errorf("Cannot read configuration: %s", err.Error())
	}

	return nil
}

func Load(filename string, v interface{}) (err error) {
	var data []byte
	if data, err = ioutil.ReadFile(filename); err != nil {
		return fmt.Errorf("Cannot load configuration: %s", err.Error())
	}
	return Unmarshal(data, v)
}
