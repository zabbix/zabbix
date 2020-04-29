package log

import "fmt"

type Logger interface {
	Tracef(format string, args ...interface{})
	Debugf(format string, args ...interface{})
	Warningf(format string, args ...interface{})
	Infof(format string, args ...interface{})
	Errf(format string, args ...interface{})
	Critf(format string, args ...interface{})
}

type loggerImpl struct {
	prefix string
}

func New(module string) Logger {
	var prefix string
	if module != "" {
		prefix = fmt.Sprintf("[%s] ", module)
	}
	return &loggerImpl{prefix: prefix}
}

func (l *loggerImpl) Critf(format string, args ...interface{}) {
	Critf(l.prefix+format, args...)
}

func (l *loggerImpl) Infof(format string, args ...interface{}) {
	Infof(l.prefix+format, args...)
}

func (l *loggerImpl) Warningf(format string, args ...interface{}) {
	Warningf(l.prefix+format, args...)
}

func (l *loggerImpl) Tracef(format string, args ...interface{}) {
	Tracef(l.prefix+format, args...)
}

func (l *loggerImpl) Debugf(format string, args ...interface{}) {
	Debugf(l.prefix+format, args...)
}

func (l *loggerImpl) Errf(format string, args ...interface{}) {
	Errf(l.prefix+format, args...)
}
