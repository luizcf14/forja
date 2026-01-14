-- 002_add_ai_status.sql

-- Add ai_status column to conversations table
-- Note: SQLite does not support IF NOT EXISTS for ADD COLUMN directly in standard syntax easily within one statement safely across versions without error if exists.
-- However, for this migration system, we assume if the migration hasn't run, the column doesn't exist.
ALTER TABLE conversations ADD COLUMN ai_status TEXT DEFAULT 'active';
