// Package resp is an umbrella package which covers both the old RESP protocol
// (resp2) and the new one (resp3), allowing clients to choose which one they
// care to use
package resp

import (
	"bufio"
	"io"
)

// Marshaler is the interface implemented by types that can marshal themselves
// into valid RESP.
type Marshaler interface {
	MarshalRESP(io.Writer) error
}

// Unmarshaler is the interface implemented by types that can unmarshal a RESP
// description of themselves. UnmarshalRESP should _always_ fully consume a RESP
// message off the reader, unless there is an error returned from the reader
// itself.
//
// Note that, unlike Marshaler, Unmarshaler _must_ take in a *bufio.Reader.
type Unmarshaler interface {
	UnmarshalRESP(*bufio.Reader) error
}

// ErrDiscarded is used to wrap an error encountered while unmarshaling a
// message. If an error was encountered during unmarshaling but the rest of the
// message was successfully discarded off of the wire, then the error can be
// wrapped in this type.
type ErrDiscarded struct {
	Err error
}

func (ed ErrDiscarded) Error() string {
	return ed.Err.Error()
}

// Unwrap implements the errors.Wrapper interface.
func (ed ErrDiscarded) Unwrap() error {
	return ed.Err
}
