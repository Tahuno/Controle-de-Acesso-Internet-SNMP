-- =========================================
-- Criação do banco
-- =========================================
CREATE DATABASE IF NOT EXISTS snmpdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE snmpdb;

-- =========================================
-- Tabela de Salas (rooms)
-- =========================================
CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela de Switches
-- compatível com App\Models\SwitchModel
-- =========================================
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
  ports_count INT UNSIGNED NOT NULL DEFAULT 0,
  room_id INT UNSIGNED DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela de Hosts
-- compatível com App\Models\HostModel
-- =========================================
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
  updated_at DATETIME NULL,
  UNIQUE KEY uq_hosts_mac (mac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela de Agendamentos
-- compatível com App\Models\ScheduleModel
-- =========================================
CREATE TABLE IF NOT EXISTS schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requested_by VARCHAR(100) NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  target_mac VARCHAR(32) DEFAULT NULL,   -- NULL => sala inteira; preenchido => host específico
  start_at DATETIME NOT NULL,
  end_at DATETIME DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending', 
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela de Logs de Ações
-- compatível com App\Models\ActionsLogModel
-- =========================================
CREATE TABLE IF NOT EXISTS actions_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,          -- ex.: manual-block, schedule-block-host-start, etc.
  target_mac VARCHAR(32) DEFAULT NULL,
  switch_ip VARCHAR(64) DEFAULT NULL,
  port_ifindex INT DEFAULT NULL,
  result TEXT DEFAULT NULL,              -- JSON de resultado / erro
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela de Usuários
-- (para login / papéis, caso use via DB)
-- =========================================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','professor') NOT NULL DEFAULT 'professor',
  room_id INT UNSIGNED DEFAULT NULL,  -- sala associada ao professor, por exemplo
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- (Opcional) Seed de sala e switch de teste
-- Ajuste IP/comunidade conforme seu cenário
-- =========================================

-- Sala de teste
INSERT INTO rooms (name, description, created_at)
VALUES ('Sala 1', 'Sala de testes', NOW())
ON DUPLICATE KEY UPDATE name = name;

-- Switch de teste apontando para Sala 1
INSERT INTO switches (name, ip, snmp_version, community_rw, room_id, ports_count, created_at)
VALUES ('Switch Teste', '192.168.0.200', 'mock', 'public', 1, 24, NOW())
ON DUPLICATE KEY UPDATE name = name;

