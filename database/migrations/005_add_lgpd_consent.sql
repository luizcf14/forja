-- 005_add_lgpd_consent.sql
-- Adiciona campos de consentimento LGPD à tabela conversations

ALTER TABLE conversations ADD COLUMN lgpd_consent_status TEXT DEFAULT 'pending';
-- Valores possíveis: 'pending' | 'accepted' | 'rejected'

ALTER TABLE conversations ADD COLUMN lgpd_consent_at DATETIME;
-- Timestamp do momento em que o usuário aceitou ou recusou

ALTER TABLE conversations ADD COLUMN lgpd_awaiting_response INTEGER DEFAULT 0;
-- Flag: 1 = política foi enviada e aguardando resposta do usuário, 0 = não está aguardando
