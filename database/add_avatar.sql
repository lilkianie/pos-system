-- Add avatar_url column to users table for profile images
-- Run this after schema.sql if users table already exists

USE pos_system;

ALTER TABLE users
ADD COLUMN avatar_url VARCHAR(255) NULL DEFAULT NULL
COMMENT 'Profile image path relative to uploads/avatars/'
AFTER full_name;
