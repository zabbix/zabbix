package log

import (
	"errors"
	"fmt"
	"log"
	"os"
)

const Empty = 0
const Crit = 1
const Err = 2
const Warning = 3
const Debug = 4
const Trace = 5

const Info = 127

const Undefined = 0
const System = 1
const File = 2
const Console = 3

var logLevel int
var logger *log.Logger

func CheckLogLevel(level int) bool {
	if level != Info && (level > logLevel || Empty == level) {
		return false
	}
	return true
}

func IncreaseLogLevel() {
	if logLevel != Trace {
		logLevel++
	}
}

func DecreaseLogLevel() {
	if logLevel != Empty {
		logLevel--
	}
}

func Open(logType int, level int, filename string) error {

	if logType == Console {
		logger = log.New(os.Stdout, "", log.Lmicroseconds|log.Ldate)
	} else if logType == File {
		f, err := os.OpenFile(filename, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
		if err != nil {
			return err
		}
		logger = log.New(f, "", log.Lmicroseconds|log.Ldate)
	} else {
		return errors.New("invalid argument")
	}

	logLevel = level
	return nil
}

func Critf(format string, args ...interface{}) {
	if CheckLogLevel(Crit) {
		logger.Printf(format, args...)
	}
}

func Infof(format string, args ...interface{}) {
	logger.Printf(format, args...)
}

func Warningf(format string, args ...interface{}) {
	if CheckLogLevel(Warning) {
		logger.Printf(format, args...)
	}
}

func Tracef(format string, args ...interface{}) {
	if CheckLogLevel(Trace) {
		logger.Printf(format, args...)
	}
}

func Debugf(format string, args ...interface{}) {
	fmt.Println(Debug, logLevel)
	if CheckLogLevel(Debug) {
		logger.Printf(format, args...)
	}
}

func Errf(format string, args ...interface{}) {
	if CheckLogLevel(Err) {
		logger.Printf(format, args...)
	}
}
