# Estado: qué está hecho, qué está probado y qué falta

Última actualización: 4 de agosto de 2026, release `20260804-4` en
<https://tiempo.dernait.com>.

La regla de este documento: nada figura como funcionando si no se ejecutó. Cada
punto dice **cómo** se comprobó.

## Verificado en producción

Comprobado por HTTPS contra tiempo.dernait.com después del despliegue:

- Login con sesión, `GET /api/status`, iniciar y detener actividad.
- Guardar presupuesto semanal (Unity 600 min `minimum`, Doom Scrolling 120 min
  `maximum`) y leerlo de vuelta.
- Reporte diario (cobertura, línea del día con huecos) y semanal (7 días,
  presupuesto, prioridad más descuidada).
- Revisión semanal: guardar y releer.
- Exportación CSV con horas ya convertidas a `America/Guatemala`.
- Crear token, consultar `/api/rainmeter/status` (200, `text/plain; charset=UTF-8`,
  16 líneas) y revocarlo.
- Con un token `time:read`: `start`, `stop` y `DELETE /api/time-entries/{id}`
  responden **403**.
- Sin token de CSRF desde el navegador: **419**, incluso añadiendo una cabecera
  `Authorization` falsa junto a la cookie de sesión (no hay bypass).
- Redirección 80 → 443, certificado Let's Encrypt válido hasta el 2 de noviembre
  de 2026, `GET /up` en 200.
- `backup-production.sh` ejecutado a mano: dump verificado en
  `/var/backups/tiempo`. Cron instalado en `/etc/cron.d/tiempo-backup`.
- `manifest.webmanifest`, `sw.js` e iconos servidos correctamente.

Los datos de prueba creados durante esa verificación se borraron. Se dejaron a
propósito los dos presupuestos de la semana en curso, porque coinciden con los
del enunciado y sirven de punto de partida.

## Probado automáticamente

**Backend — 58 pruebas Pest, 188 aserciones.** `docker compose exec app php vendor/bin/pest`

| Área | Qué se fija |
| --- | --- |
| `TimeTrackingTest` | una sola actividad abierta; cierre atómico en el instante del cambio; dos toques en el mismo segundo dejan una sola actividad; mismo botón dos veces es idempotente; detener sin nada abierto; rechazo de solapamientos al crear y al editar; fin anterior al inicio; duración materializada |
| `AggregationTest` | límites de día y semana en la zona del usuario; reparto de entradas que cruzan medianoche y el lunes 00:00; actividad abierta contada hasta ahora y nunca más allá del periodo; cobertura contra tiempo transcurrido; huecos; desglose por categoría; una zona horaria distinta (`Europe/Madrid`) |
| `BudgetTest` | `minimum` pendiente/cumplido; `maximum` solo excedido al superarse, no al igualarse; `reference` nunca puntúa; prioridad más descuidada y mayor exceso |
| `RainmeterContractTest` | **la respuesta completa contra una cadena esperada**, línea a línea; 16 claves en orden; caso sin actividad; caso sin presupuesto; nombres con saltos de línea; `time:read` obligatorio; sin token 401; token revocado deja de funcionar; token de solo lectura no puede escribir; límite de 30 req/min |
| `ApiTest` | sesión requerida; cambio y detención por HTTP; categoría ajena rechazada; 422 con la entrada en conflicto; no se puede editar ni borrar lo de otro usuario; categoría con historial se archiva; presupuestos y copia de la semana anterior; una revisión por semana; CSV; token mostrado una sola vez; ajustes y sello de auditoría; reportes |
| `ValidateCsrfTokenTest` | CSRF se omite solo para peticiones con Bearer y sin cookie de sesión |

**Frontend — 28 pruebas Vitest.** `npm test`

- `format.spec.ts`: duraciones, cronómetro más allá de 24 h, minutos a etiqueta,
  porcentajes, y round-trip de `datetime-local` en la zona del usuario.
- `overlap.spec.ts`: solapamiento parcial, límites que solo se tocan, entrada
  abierta como infinita, ignorar la entrada que se edita, rangos inválidos.
- `tracker.spec.ts`: cronómetro en vivo, inicio optimista antes de la respuesta,
  reversión y mensaje de error al fallar, detención y su reversión, agrupación
  de categorías.

**Tipos:** `npm run typecheck` (vue-tsc) sin errores.

## Implementado

- Registro de un toque con favoritos y buscador de categorías agrupadas.
- Cronómetro en vivo, detener, y corrección manual con detección de solapamiento
  antes de guardar.
- Categorías: crear, renombrar, recolorear, favoritas, archivar, reordenar. Las
  que tienen historial se archivan en vez de borrarse.
- Auditoría inicial configurable (7 días por defecto) con banner sin lenguaje de
  culpa.
- Presupuestos `minimum` / `maximum` / `reference`, copiar semana anterior y
  plantilla recurrente.
- Pantallas: onboarding, Hoy, Semana, Presupuestos, Historial, Revisión semanal,
  Ajustes.
- Historial con filtros de fecha, categoría y texto, edición y exportación CSV.
- PWA instalable, oscura, mobile-first, con acento configurable.
- Despliegue por releases, health check, backup diario y rollback documentado.

## Pendiente real

Cosas que **no** están hechas, dichas sin adornos:

- **No hay pruebas de navegador de punta a punta.** Las pruebas de frontend
  cubren la lógica y el store, no el DOM renderizado. El recorrido por la
  interfaz se comprobó a mano contra producción vía API, no con Playwright.
- **La instalación como PWA no se probó en un teléfono real.** El manifest, el
  service worker y los iconos se sirven correctamente, pero no se ha verificado
  el flujo "Añadir a pantalla de inicio" en iOS ni en Android.
- **Sin reordenar categorías arrastrando.** El endpoint `/api/categories/reorder`
  existe y funciona, pero la interfaz todavía no lo usa: el orden se cambia
  editando `sort_order`.
- **Sin trabajos en segundo plano.** El worker de colas está desplegado y en
  marcha, pero no hay ningún job encolado. No se añadieron recordatorios de
  revisión: sin haber usado la app una semana, cualquier cadencia sería inventada.
- **Sin registro ni recuperación de contraseña.** Es una app de un solo usuario;
  la cuenta se crea con el seeder y la contraseña se cambia por base de datos.
- **Sin modo claro.** Decisión consciente por la compatibilidad con la skin.
- **Zona horaria elegible de una lista corta.** Son seis zonas comunes, no el
  catálogo IANA completo. El backend acepta cualquier zona válida.
- **Sin paginación en la interfaz de Historial.** La API pagina; el cliente pide
  100 registros y no ofrece "cargar más".
