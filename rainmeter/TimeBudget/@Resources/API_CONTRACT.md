# Contrato de API para Rainmeter

La skin hace **una sola petición GET** al endpoint configurado en `Settings.inc` y envía:

```http
Authorization: Bearer <TOKEN_DE_SOLO_LECTURA>
Accept: text/plain
```

El servidor debe responder `200 OK`, `Content-Type: text/plain; charset=UTF-8` y exactamente estas líneas, en este orden:

```text
ok=1
server_time_unix=1785864300
current_activity_active=1
current_activity_name=Proyecto de Unity
current_activity_started_at=10:02
current_activity_elapsed_seconds=5010
today_tracked_minutes=522
today_elapsed_minutes=685
week_tracked_minutes=3110
week_elapsed_minutes=3565
priority_name=Proyecto de Unity
priority_actual_minutes=425
priority_budget_minutes=600
leak_name=Doom Scrolling
leak_actual_minutes=190
leak_limit_minutes=120
```

## Reglas

- No agregues JSON, HTML, comentarios ni líneas extra.
- Todos los valores numéricos deben ser enteros no negativos.
- Los nombres no pueden contener saltos de línea.
- `server_time_unix` usa Unix epoch en segundos.
- `current_activity_elapsed_seconds` ya incluye el tiempo transcurrido hasta `server_time_unix`; Rainmeter continúa el contador localmente entre sincronizaciones.
- `today_elapsed_minutes` son los minutos transcurridos desde medianoche hasta `server_time_unix`, en la zona horaria del usuario.
- `week_elapsed_minutes` son los minutos transcurridos desde el lunes a las 00:00 hasta `server_time_unix`.
- Los totales deben incluir la actividad actualmente abierta hasta `server_time_unix`.
- Si no hay actividad activa: `current_activity_active=0`, nombre e inicio vacíos, y elapsed `0`.
- Si no hay presupuesto o límite configurado, devuelve `0`.
- El token debe tener únicamente el permiso `time:read`.
