```markdown
# Scouting - MVP inicial (PHP + MySQL)

Proyecto mínimo para registro de equipos, temporadas, categorías y creación de partidos.
Interfaz responsive pensada para móviles/tablets.

Estructura recomendada:
- public/         <- archivos servidos por el webserver (login.php, dashboard.php, ...)
- src/            <- lógica y utilidades (db.php, auth.php, helpers.php, create_admin.php)
- sql/            <- schema.sql
- assets/         <- css, js, imágenes

Requisitos:
- PHP 8.0+ con PDO MySQL
- MySQL / MariaDB
- Servidor web (Apache/Nginx) apuntando public/ como document root

Instalación rápida:
1. Clona o sube los ficheros al repo y al servidor.
2. Edita src/config.php con tus credenciales de base de datos.
3. Importa la base de datos:
   mysql -u root -p < sql/schema.sql
   (o desde tu cliente MySQL preferido)
4. Crea un usuario admin (desde CLI en el servidor):
   php src/create_admin.php admin TU_CONTRASEÑA "Admin visible"
5. Accede a /login.php y entra con ese usuario.

Notas de seguridad:
- En producción activa HTTPS y en src/config.php pon 'session_cookie_secure' => true.
- No subas credenciales reales al repo público.
- Implementa control de acceso/roles según evolucione la app.
- Los formularios usan token CSRF básico; revisa y mejora según necesidades.

Siguientes mejoras sugeridas:
- CRUD de jugadores y registro de eventos por partido (scouting en tiempo real).
- API REST para app móvil.
- Export CSV/PDF y dashboards con gráficos (Chart.js).
- Tests y validación más estricta de entradas.

Si quieres, hago un PR listo para tu repo con estos ficheros o te los doy listos para pegar. Dime si quieres que añada:
- paginación en listados,
- edición/borrado de registros desde la UI,
- o conversión a un micro-framework (Slim) o Laravel.
```