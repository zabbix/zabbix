ALTER TABLE expressions ALTER COLUMN expressionid SET WITH DEFAULT NULL
/
REORG TABLE expressions
/
ALTER TABLE expressions ALTER COLUMN regexpid SET WITH DEFAULT NULL
/
REORG TABLE expressions
/
DELETE FROM expressions WHERE NOT regexpid IN (SELECT regexpid FROM regexps)
/
ALTER TABLE expressions ADD CONSTRAINT c_expressions_1 FOREIGN KEY (regexpid) REFERENCES regexps (regexpid) ON DELETE CASCADE
/
