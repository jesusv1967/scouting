-- init.sql
-- Crear base de datos y tablas para la app de scouting

CREATE DATABASE IF NOT EXISTS scouting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scouting;

-- Equipos
CREATE TABLE IF NOT EXISTS teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jugadores
CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  number VARCHAR(20),
  position VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partidos
CREATE TABLE IF NOT EXISTS matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_date DATETIME NOT NULL,
  home_team_id INT NOT NULL,
  away_team_id INT NOT NULL,
  location VARCHAR(255),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE RESTRICT,
  FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notas de scouting
CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  player_id INT DEFAULT NULL,
  quarter TINYINT UNSIGNED DEFAULT NULL, -- cuarto 1..4 (o OT)
  time_remaining VARCHAR(20) DEFAULT NULL, -- formato mm:ss
  note_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;