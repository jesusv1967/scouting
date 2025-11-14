```markdown
# Scouting - Aplicación de Notas de Baloncesto (MVP)

Este repositorio contiene un MVP para tomar notas de partidos de baloncesto usando PHP + MySQL.

Instrucciones rápidas:

1. Crear la base de datos:
   - Ejecuta `init.sql` en tu servidor MySQL para crear la base de datos y tablas.
     Por ejemplo:
     mysql -u root -p < init.sql

2. Configurar conexión:
   - Edita `src/config.php` con tus credenciales MySQL.

3. Colocar archivos en tu servidor web:
   - Sitúa la carpeta `public/` como raíz pública (document root).
   - `src/` contiene configuración y conexión.

4. Uso:
   - Visita `/public/index.php` para ver/crear partidos.
   - En cada partido puedes añadir notas por jugador/cuarto/tiempo.

Mejoras posibles:
- Autenticación de usuarios.
- Subida de imagenes o vídeo por nota.
- Exportar notas a CSV/PDF.
- Mejor gestión de jugadores (CRUD) y asignación dinámica a equipos.

Si quieres, creo una rama en tu repo y subo estos archivos, y abro un PR. Dime si deseas que lo haga y el nombre de la rama que prefieres.
```