-- Archivo: sql/limpia_datos_reales.sql
-- Borra TODOS los datos de partidos, jugadores, temporadas, etc., pero mantiene:
-- - la estructura de las tablas
-- - el usuario admin (si su id = 1)

-- 1. Borra observaciones cualitativas de scouting
DELETE FROM match_player_observations;

-- 2. Borra eventos antiguos (si usaste match_events en pruebas)
DELETE FROM match_events;

-- 3. Borra medios adjuntos y su relación con partidos
DELETE FROM match_media;

-- 4. Borra jugadores asociados a partidos
DELETE FROM match_players;

-- 5. Borra partidos
DELETE FROM matches;

-- 6. Borra jugadores (solo si no los quieres conservar)
DELETE FROM players;

-- 7. Borra equipos (si quieres empezar de cero)
DELETE FROM teams;

-- 8. Borra categorías
DELETE FROM categories;

-- 9. Borra temporadas
DELETE FROM seasons;

-- 10. Opcional: si quieres conservar tu usuario admin (id=1), no toques users
-- Si quieres borrar todos los usuarios excepto el admin:
-- DELETE FROM users WHERE id != 1;

-- ⚠️ Importante: las tablas se mantienen, solo se limpian los datos.