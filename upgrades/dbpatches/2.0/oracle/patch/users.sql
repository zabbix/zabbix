ALTER TABLE users MODIFY userid DEFAULT NULL;
UPDATE users SET theme='css_ob.css' WHERE theme='default.css';
