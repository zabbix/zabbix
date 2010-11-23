ALTER TABLE users MODIFY userid bigint unsigned NOT NULL;
UPDATE users SET theme='css_ob.css' WHERE theme='default.css';