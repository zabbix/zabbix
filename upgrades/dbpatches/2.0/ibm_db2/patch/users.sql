ALTER TABLE users ALTER COLUMN userid SET WITH DEFAULT NULL;
REORG TABLE users;
UPDATE users SET theme='css_ob.css' WHERE theme='default.css';
