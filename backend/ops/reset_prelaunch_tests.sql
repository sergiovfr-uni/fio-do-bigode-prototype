-- Pre-launch reset authorized by the owner. Requires an independently verified backup.
-- Keep admins, Google Play reviewers, plans, advertisers, campaigns and community partners.
-- Never TRUNCATE users or reset IDs: late Didit callbacks must not target newly-created users.
CREATE TEMPORARY TABLE fdb_users_to_clear (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO fdb_users_to_clear
SELECT id FROM users WHERE is_admin=0
AND email NOT IN ('review.seller@nofiodobigode.app.br','review.buyer@nofiodobigode.app.br');
START TRANSACTION;
DELETE FROM installment_delinquency_actions;
DELETE FROM wallet_transactions;
DELETE FROM deal_ratings;
DELETE FROM deal_events;
DELETE FROM notifications WHERE deal_id IS NOT NULL OR user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM deal_electronic_signatures;
DELETE FROM deal_witnesses;
DELETE FROM deal_offers;
DELETE FROM deal_documents;
DELETE FROM installments;
DELETE FROM deals;
DELETE FROM deal_invitations;
DELETE FROM listings;
DELETE FROM ad_events;
DELETE FROM didit_webhook_events WHERE session_id IN (SELECT session_id FROM didit_kyc_sessions WHERE user_id IN (SELECT id FROM fdb_users_to_clear));
DELETE FROM didit_kyc_sessions WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM kyc_checks WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM account_deletion_requests WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM consents WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM subscriptions WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM wallet_accounts WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
UPDATE wallet_accounts SET available_balance=0 WHERE user_id IN (SELECT id FROM users WHERE is_admin=0);
DELETE FROM personal_access_tokens WHERE tokenable_type='App\\Models\\User' AND tokenable_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM sessions WHERE user_id IN (SELECT id FROM fdb_users_to_clear);
DELETE FROM password_reset_tokens WHERE email IN (SELECT email FROM users WHERE id IN (SELECT id FROM fdb_users_to_clear));
DELETE FROM users WHERE id IN (SELECT id FROM fdb_users_to_clear);
SELECT 'remaining_users' AS item, COUNT(*) AS total FROM users
UNION ALL SELECT 'remaining_listings',COUNT(*) FROM listings
UNION ALL SELECT 'remaining_deals',COUNT(*) FROM deals
UNION ALL SELECT 'remaining_documents',COUNT(*) FROM deal_documents
UNION ALL SELECT 'preserved_campaigns',COUNT(*) FROM campaigns
UNION ALL SELECT 'preserved_partners',COUNT(*) FROM community_partners;
COMMIT;
