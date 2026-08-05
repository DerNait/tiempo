# Tiempo

Registro y auditoría de uso del tiempo con presupuesto semanal en minutos.
La app es la fuente de verdad; se usa desde PC y teléfono, y expone una API de
solo lectura en texto plano para una skin de Rainmeter.

La filosofía no es exprimir cada minuto: es comprobar si los minutos reales
reflejan las prioridades elegidas conscientemente. El sueño, el descanso y el
ocio son tiempo válido, no fracaso.

- **Producción:** <https://tiempo.dernait.com>
- **Stack:** Laravel 12 · PHP 8.3 · Vue 3 + TypeScript · Vite 7 · Tailwind 4 ·
  MySQL 8.4 · Sanctum · Pest · Vitest · Chart.js
- **Iconos:** los de la interfaz son [Font Awesome Free](https://fontawesome.com)
  (CC BY 4.0), dibujados desde su path data por `components/FaIcon.vue`; los de
  las categorías son emoji, porque son datos del usuario y tienen que verse en
  cualquier sitio, incluida la skin de Rainmeter.
- **Zona horaria:** los instantes se guardan en UTC; los límites de día y semana
  se calculan en la zona del usuario (`America/Guatemala` por defecto). La
  semana empieza el lunes.

## Instalación local

Requiere Docker y Docker Compose.

```bash
cp .env.example .env
# Rellena DB_PASSWORD, DB_ROOT_PASSWORD, PERSONAL_USER_EMAIL y PERSONAL_USER_PASSWORD.

docker compose up -d mysql
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d

docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed                       # cuenta personal + categorías
docker compose exec app php artisan db:seed --class=DemoSeeder    # datos de ejemplo
```

- App: <http://localhost:8080>
- Vite dev server: <http://localhost:5173>

El `DemoSeeder` crea `demo@tiempo.test` / `password` con una semana de registros
variados. Nunca se ejecuta en `APP_ENV=production`.

## Pruebas

```bash
# Backend (Pest, SQLite en memoria): 61 pruebas
docker compose exec app php vendor/bin/pest
# o, sin levantar el stack:
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" -e HOME=/tmp \
    php:8.3-cli php vendor/bin/pest

# Frontend (Vitest): 28 pruebas
npm test

# Tipos
npm run typecheck
```

## Despliegue en tiempo.dernait.com

El despliegue sigue el patrón por releases del servidor: se construye una imagen
etiquetada en local, se exporta como tarball, se copia por SSH y se activa
cambiando el symlink `current`. El nginx del host hace de proxy al contenedor en
`127.0.0.1:8330` y Certbot gestiona el certificado.

```bash
# 1. Construir el release (assets + composer --no-dev + imagen amd64 + tarball)
./scripts/prepare-production.sh 20260804-1

# 2. Subir y activar
SSH_TARGET=root@167.233.42.11 SSH_PORT=17 ./scripts/deploy-production.sh 20260804-1

# 3. Solo la primera vez: vhost del host + certificado
ssh -p 17 root@167.233.42.11 'BASE_DIR=/var/www/tiempo /var/www/tiempo/current/scripts/configure-host-nginx.sh'
```

`deploy-production.sh` ejecuta en el servidor, en este orden:
`initialize-production.sh` (crea `shared/.env` con secretos aleatorios la primera
vez y no lo toca después) → `docker load` → symlink `current` →
`docker compose up -d --force-recreate app worker nginx` →
`php artisan migrate --force` → `php artisan db:seed --force` →
`php artisan optimize`.

El seeder de producción es idempotente: crea la cuenta personal si no existe y
completa las categorías por defecto que falten. No borra nada.

### Estructura en el servidor

```
/var/www/tiempo
├── current -> releases/<id>     # symlink activo
├── releases/<id>/               # código del release
├── images/                      # tarballs de imágenes
└── shared/
    ├── .env                     # secretos, chmod 600
    ├── deploy.env               # APP_IMAGE del release activo
    ├── storage/                 # persistente entre releases
    └── bootstrap/cache/
```

### Cloudflare y HTTPS

El subdominio está detrás de Cloudflare. El origen sirve HTTP en el puerto 80 y
Certbot obtiene un certificado Let's Encrypt por HTTP-01, igual que el resto de
subdominios del servidor. Laravel confía en los proxies (`trustProxies(at: '*')`)
y fuerza `https` en producción, así que enlaces y cookies se generan bien detrás
de la doble capa de proxy. Las cookies de sesión son `Secure`, `HttpOnly` y
`SameSite=lax`.

Si Cloudflare está en modo *Full (strict)*, el certificado del origen debe ser
válido: emítelo con `configure-host-nginx.sh` antes de activar el proxy naranja.

### Rollback

```bash
ssh -p 17 root@167.233.42.11
cd /var/www/tiempo
printf 'APP_IMAGE=tiempo-app:<release-anterior>\n' > shared/deploy.env
ln -sfn releases/<release-anterior> current
docker compose --env-file shared/.env --env-file shared/deploy.env \
    -f current/docker-compose.production.yml up -d --force-recreate app worker nginx
```

### Backups

`deploy-production.sh` instala `/etc/cron.d/tiempo-backup`, que ejecuta
`scripts/backup-production.sh` cada día a las 03:23 UTC: `mysqldump` comprimido
en `/var/backups/tiempo`, verificado con `gzip -t` y con rotación a 14 días.
Restaurar:

```bash
gzip -dc /var/backups/tiempo/mysql-<stamp>.sql.gz | \
docker compose --env-file /var/www/tiempo/shared/.env \
    --env-file /var/www/tiempo/shared/deploy.env \
    -f /var/www/tiempo/current/docker-compose.production.yml \
    exec -T mysql sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

### Health check

`GET /up` responde 200 cuando el framework arranca. El contenedor nginx lo usa
como healthcheck.

## Token de Rainmeter

1. Entra en **Ajustes → Rainmeter**.
2. Elige la *categoría prioritaria* (se compara con su meta `minimum` de la
   semana) y la *categoría de fuga* (se compara con su límite `maximum`).
3. En **Tokens de solo lectura**, escribe un nombre y pulsa **Crear**.
4. Copia el token en ese momento: solo se muestra una vez.

El token recibe únicamente la habilidad `time:read`. Con él no se puede iniciar,
detener, editar ni eliminar actividades: el resto de la API exige `time:write`,
que solo tienen las sesiones del navegador.

### Endpoint

```http
GET https://tiempo.dernait.com/api/rainmeter/status
Authorization: Bearer <token>
Accept: text/plain
```

Respuesta `text/plain; charset=UTF-8`, exactamente 16 líneas en este orden:

```text
ok=1
server_time_unix=1785864330
current_activity_active=1
current_activity_name=Proyecto de Unity
current_activity_started_at=10:02
current_activity_elapsed_seconds=5010
today_tracked_minutes=563
today_elapsed_minutes=685
week_tracked_minutes=1768
week_elapsed_minutes=2125
priority_name=Proyecto de Unity
priority_actual_minutes=318
priority_budget_minutes=600
leak_name=Doom Scrolling
leak_actual_minutes=130
leak_limit_minutes=120
```

Sin actividad abierta: `current_activity_active=0`, nombre e inicio vacíos y
`current_activity_elapsed_seconds=0`. Sin presupuesto de ese tipo esta semana:
`0`. Los saltos de línea en los nombres se sustituyen por espacios.

Límite de 30 solicitudes por minuto y por token; la skin consulta cada 15
minutos. El formato está fijado por `tests/Feature/RainmeterContractTest.php`,
que compara la respuesta completa contra la cadena esperada.

### La skin

El panel de escritorio que consume este endpoint vive en
[`rainmeter/`](rainmeter/), con sus instrucciones de instalación. Un detalle
que no es evidente: en una medida hija de WebParser, `StringIndex=1` devuelve
la coincidencia completa y no el grupo de captura, así que cada campo se
extrae con un lookbehind anclado a inicio de línea:

```ini
[MeasureApiOk]
Measure=WebParser
URL=[MeasureApi]
RegExp=(?sim)(?<=^ok=)[01]
StringIndex=1
```

## API

Todas las rutas de la app viven bajo `/api` con sesión de navegador
(`web` + `auth:sanctum` + `abilities:time:write`).

| Método | Ruta | Qué hace |
| --- | --- | --- |
| POST | `/api/login` · `/api/logout` | Sesión |
| GET | `/api/status` | Actividad actual y totales de hoy/semana |
| POST | `/api/tracking/start` · `/api/tracking/stop` | Iniciar/cambiar y detener |
| GET POST PATCH DELETE | `/api/time-entries` | CRUD de registros |
| GET | `/api/time-entries/export` | CSV filtrable |
| GET POST PATCH DELETE | `/api/categories` (+ `/reorder`) | CRUD y orden |
| GET | `/api/reports/day` · `/api/reports/week` | Reportes |
| GET POST | `/api/budgets` (+ `/copy-previous`, `/apply-template`) | Presupuestos |
| GET POST | `/api/weekly-review` · GET `/api/weekly-reviews` | Revisión semanal |
| GET PATCH | `/api/settings` | Preferencias |
| GET POST DELETE | `/api/tokens` | Tokens de Rainmeter |
| GET | `/api/rainmeter/status` | Texto plano, `time:read` |

## Documentación

- [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md) — decisiones de diseño.
- [`rainmeter/README.md`](rainmeter/README.md) — instalación de la skin y las
  dos trampas de Rainmeter que costaron depurar.
- [`docs/ESTADO.md`](docs/ESTADO.md) — qué está implementado y probado, y qué no.
