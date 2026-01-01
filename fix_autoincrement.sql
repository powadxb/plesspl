-- Fix broken AUTO_INCREMENT in user_permissions table
-- Run these in order:

DELETE FROM user_permissions WHERE id = 0;

ALTER TABLE user_permissions AUTO_INCREMENT = 1;
