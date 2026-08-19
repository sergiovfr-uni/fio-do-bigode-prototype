-- Fio do Bigode MVP schema (MySQL 8+)

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL UNIQUE,
  cpf VARCHAR(14) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','active','blocked','deleted') NOT NULL DEFAULT 'pending',
  email_verified_at DATETIME NULL,
  phone_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_security (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_method ENUM('email','totp') NULL,
  totp_secret_encrypted TEXT NULL,
  failed_login_attempts INT NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(64) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE identity_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('CNH','CIN','RG') NOT NULL,
  document_number VARCHAR(80) NULL,
  document_status ENUM('pending','verified','rejected','manual_review') NOT NULL DEFAULT 'pending',
  cpf_match TINYINT(1) NOT NULL DEFAULT 0,
  name_match TINYINT(1) NOT NULL DEFAULT 0,
  birthdate_match TINYINT(1) NOT NULL DEFAULT 0,
  face_match_score DECIMAL(5,2) NULL,
  liveness_score DECIMAL(5,2) NULL,
  provider VARCHAR(100) NULL,
  provider_reference VARCHAR(190) NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_identity_user_status (user_id, document_status)
);

CREATE TABLE risk_assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  negotiation_id BIGINT UNSIGNED NULL,
  score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  level ENUM('low','medium','high','critical') NOT NULL,
  decision ENUM('allow','review','block') NOT NULL,
  reasons JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE legal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL,
  version VARCHAR(20) NOT NULL,
  type ENUM('terms','privacy','responsibility','negotiation_responsibility') NOT NULL,
  title VARCHAR(190) NOT NULL,
  content_hash CHAR(64) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  published_at DATETIME NOT NULL,
  UNIQUE KEY uq_legal_code_version (code, version)
);

CREATE TABLE legal_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  legal_document_id BIGINT UNSIGNED NOT NULL,
  negotiation_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent TEXT NULL,
  device_id VARCHAR(190) NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (legal_document_id) REFERENCES legal_documents(id)
);

CREATE TABLE plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  trial_days INT NOT NULL DEFAULT 0,
  active_listing_limit INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  metadata JSON NULL
);

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('trial','active','past_due','canceled','expired') NOT NULL,
  gateway VARCHAR(60) NULL,
  external_subscription_id VARCHAR(190) NULL,
  starts_at DATETIME NOT NULL,
  renews_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (plan_id) REFERENCES plans(id),
  INDEX idx_subscription_user_status (user_id, status)
);

CREATE TABLE listings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  seller_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(14,2) NOT NULL,
  status ENUM('draft','active','paused','sold','expired','rejected') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id) REFERENCES users(id),
  INDEX idx_listing_status (status, published_at)
);

CREATE TABLE listing_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id BIGINT UNSIGNED NOT NULL,
  file_url TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE negotiations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  listing_id BIGINT UNSIGNED NULL,
  seller_id BIGINT UNSIGNED NOT NULL,
  buyer_id BIGINT UNSIGNED NOT NULL,
  origin ENUM('direct','classified') NOT NULL DEFAULT 'direct',
  status ENUM('draft','proposal_sent','under_review','counter_offer','accepted','management_defined','awaiting_signatures','active','overdue','settled','canceled') NOT NULL DEFAULT 'draft',
  management_type ENUM('manual','wallet') NULL,
  current_proposal_version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id),
  FOREIGN KEY (seller_id) REFERENCES users(id),
  FOREIGN KEY (buyer_id) REFERENCES users(id),
  INDEX idx_negotiation_party (seller_id, buyer_id),
  INDEX idx_negotiation_status (status)
);

CREATE TABLE proposals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id BIGINT UNSIGNED NOT NULL,
  version INT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL,
  down_payment DECIMAL(14,2) NOT NULL DEFAULT 0,
  installments INT NOT NULL,
  monthly_interest DECIMAL(8,4) NOT NULL DEFAULT 0,
  installment_amount DECIMAL(14,2) NOT NULL,
  status ENUM('sent','accepted','rejected','superseded') NOT NULL DEFAULT 'sent',
  accepted_by BIGINT UNSIGNED NULL,
  accepted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (accepted_by) REFERENCES users(id),
  UNIQUE KEY uq_proposal_version (negotiation_id, version)
);

CREATE TABLE negotiation_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  declared_value DECIMAL(14,2) NULL,
  metadata JSON NULL,
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE
);

CREATE TABLE negotiation_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id BIGINT UNSIGNED NOT NULL,
  type ENUM('identity','proposal','contract','signed_contract','receipt','other') NOT NULL,
  file_url TEXT NOT NULL,
  file_hash CHAR(64) NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE negotiation_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_event_negotiation (negotiation_id, created_at)
);

CREATE TABLE installments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id BIGINT UNSIGNED NOT NULL,
  number INT NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending','paid','overdue','canceled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE,
  UNIQUE KEY uq_installment_number (negotiation_id, number)
);

CREATE TABLE wallets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  provider VARCHAR(60) NULL,
  external_wallet_id VARCHAR(190) NULL,
  status ENUM('demo','pending','active','blocked') NOT NULL DEFAULT 'demo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE wallet_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_id BIGINT UNSIGNED NOT NULL,
  negotiation_id BIGINT UNSIGNED NULL,
  type ENUM('credit','debit') NOT NULL,
  method ENUM('pix','internal','manual') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending','confirmed','failed','reversed') NOT NULL,
  external_reference VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (negotiation_id) REFERENCES negotiations(id)
);

CREATE TABLE advertisers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  advertiser_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  status ENUM('draft','active','paused','finished') NOT NULL DEFAULT 'draft',
  FOREIGN KEY (advertiser_id) REFERENCES advertisers(id)
);

CREATE TABLE campaign_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  placement VARCHAR(80) NOT NULL,
  headline VARCHAR(190) NULL,
  cta VARCHAR(80) NULL,
  media_url TEXT NULL,
  destination_url TEXT NULL,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

CREATE TABLE ad_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type ENUM('impression','click') NOT NULL,
  placement VARCHAR(80) NOT NULL,
  session_id VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_ad_campaign_event (campaign_id, event_type, created_at)
);

CREATE TABLE reputation_scores (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  level VARCHAR(40) NOT NULL,
  completed_negotiations INT NOT NULL DEFAULT 0,
  overdue_negotiations INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  payload JSON NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_notification_user (user_id, read_at, created_at)
);

INSERT INTO plans (code,name,monthly_price,trial_days,active_listing_limit) VALUES
('free_trial','Free Trial',0,60,1),
('bronze','Bronze',0,0,3),
('prata','Prata',0,0,10),
('ouro','Ouro',0,0,30);
