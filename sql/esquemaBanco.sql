CREATE DATABASE IF NOT EXISTS snmpdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE snmpdb;

-- Tabela switches
CREATE TABLE IF NOT EXISTS switches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  snmp_version VARCHAR(10) NOT NULL DEFAULT 'mock',
  community_rw VARCHAR(100) DEFAULT NULL,
  snmpv3_user VARCHAR(100) DEFAULT NULL,
  snmpv3_auth_proto VARCHAR(20) DEFAULT NULL,
  snmpv3_auth_pass VARCHAR(100) DEFAULT NULL,
  snmpv3_priv_proto VARCHAR(20) DEFAULT NULL,
  snmpv3_priv_pass VARCHAR(100) DEFAULT NULL,
  ports_count INT UNSIGNED DEFAULT 0,
  room_id INT UNSIGNED DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);

-- Tabela hosts
CREATE TABLE IF NOT EXISTS hosts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostname VARCHAR(100) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  mac VARCHAR(32) NOT NULL,
  switch_id INT UNSIGNED DEFAULT NULL,
  port_ifindex INT UNSIGNED DEFAULT NULL,
  port_descr VARCHAR(255) DEFAULT NULL,
  room_id INT UNSIGNED DEFAULT NULL,
  is_authorized_machine TINYINT(1) NOT NULL DEFAULT 0,
  is_protected TINYINT(1) NOT NULL DEFAULT 0,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  last_seen DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);

-- Tabela schedules
CREATE TABLE IF NOT EXISTS schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requested_by VARCHAR(100) NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  target_mac VARCHAR(32) DEFAULT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);

-- Tabela actions_log
CREATE TABLE IF NOT EXISTS actions_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  target_mac VARCHAR(32) DEFAULT NULL,
  switch_ip VARCHAR(64) DEFAULT NULL,
  port_ifindex INT DEFAULT NULL,
  result TEXT DEFAULT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);

-- Tabela users
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','professor') NOT NULL DEFAULT 'professor',
  room_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);

-- Usuário admin padrão (exemplo)
INSERT INTO users (username, password_hash, role, created_at)
VALUES (
  'admin',
  '$2y$10$ABCDEFGHIJKLMNOPQRSTUVWX1234567890abcdefghiJKLmnopq',
  'admin',
  NOW()
)
ON DUPLICATE KEY UPDATE username = username;

