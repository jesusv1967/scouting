

-- Desactivar temporalmente comprobaciones de FK
SET FOREIGN_KEY_CHECKS = 0;

-- Borrar datos dependientes (orden pensado para evitar violaciones FK)
-- Ajusta si tu esquema tiene otras tablas dependientes
DELETE FROM match_players WHERE 1;
DELETE FROM match_media WHERE 1;
DELETE FROM matches WHERE 1;

DELETE FROM players WHERE 1;
DELETE FROM teams WHERE 1;
DELETE FROM seasons WHERE 1;
DELETE FROM categories WHERE 1;

-- Si tienes otras tablas relacionadas (por ejemplo events, stats, etc.), añádelas aquí:
-- DELETE FROM events WHERE 1;
-- DELETE FROM player_stats WHERE 1;


-- Reiniciar AUTO_INCREMENT para tablas limpias (opcional)
ALTER TABLE matches AUTO_INCREMENT = 1;
ALTER TABLE match_players AUTO_INCREMENT = 1;
ALTER TABLE match_media AUTO_INCREMENT = 1;
ALTER TABLE players AUTO_INCREMENT = 1;
ALTER TABLE teams AUTO_INCREMENT = 1;
ALTER TABLE seasons AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;

-- Reactivar FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- Resultado: mostrar recuentos por tabla tras limpieza
SELECT 
  (SELECT COUNT(*) FROM users) AS users_count,
  (SELECT COUNT(*) FROM matches) AS matches_count,
  (SELECT COUNT(*) FROM match_players) AS match_players_count,
  (SELECT COUNT(*) FROM match_media) AS match_media_count,
  (SELECT COUNT(*) FROM players) AS players_count,
  (SELECT COUNT(*) FROM teams) AS teams_count,
  (SELECT COUNT(*) FROM seasons) AS seasons_count,
  (SELECT COUNT(*) FROM categories) AS categories_count;