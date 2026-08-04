# Prompt para Claude Code: sistema web de presupuesto y auditoría de tiempo

Quiero que construyas una aplicación web completa, desplegable en mi servidor, para registrar cómo utilizo mi tiempo y compararlo contra un presupuesto semanal en minutos. La aplicación será la fuente de verdad; la usaré desde PC y teléfono, y una skin de Rainmeter consultará una API de solo lectura cada 15 minutos.

## 1. Antes de programar

1. Inspecciona el repositorio actual y documenta brevemente qué existe.
2. Si ya hay una base Laravel/Vue, adáptate a ella sin destruir funcionalidades existentes.
3. Si el repositorio está vacío, crea el proyecto con **Laravel 12, Vue 3, TypeScript, Vite y Tailwind CSS**.
4. Usa Docker para desarrollo y producción si el repositorio todavía no tiene una estrategia de contenedores.
5. No inventes credenciales ni secretos. Crea `.env.example` completo.
6. Trabaja por fases verificables. Al terminar cada fase, ejecuta pruebas y corrige errores antes de continuar.

## 2. Objetivo del producto

El sistema debe permitirme:

- Registrar cada cambio de actividad con la mínima fricción posible.
- Iniciar una actividad desde botones rápidos; al iniciar una nueva, cerrar automáticamente la anterior.
- Detener la actividad actual.
- Registrar y corregir actividades manualmente.
- Usarlo cómodamente desde un teléfono.
- Ver cuánto tiempo registré hoy y esta semana.
- Agrupar el tiempo por categorías.
- Definir presupuestos semanales en minutos.
- Distinguir entre metas mínimas y límites máximos.
- Revisar cada semana qué funcionó, qué consumió demasiado tiempo y qué prioridad quedó descuidada.
- Proveer una API específica de solo lectura para Rainmeter.

La filosofía del sistema es: **no maximizar productividad en cada minuto, sino comprobar si mis minutos reflejan mis prioridades elegidas conscientemente**.

## 3. Stack y arquitectura

Usa:

- Laravel 12.
- PHP 8.3 o compatible con Laravel 12.
- Vue 3 con Composition API y TypeScript.
- Vite.
- Tailwind CSS.
- PostgreSQL 14+ o MySQL 8+, siguiendo lo que ya use el repositorio. No cambies el motor existente sin motivo.
- Laravel Sanctum para autenticación web y tokens de API.
- Pest o PHPUnit para backend.
- Vitest para lógica crítica del frontend.
- Chart.js para gráficas, salvo que el proyecto ya use otra librería adecuada.
- Zona horaria por defecto: `America/Guatemala`.
- La semana empieza el lunes.

La aplicación debe ser responsive y **mobile-first**. Conviértela en PWA instalable si se puede hacer de forma limpia y sin complicar el MVP.

## 4. Categorías iniciales

Crea estas categorías por defecto, pero permite crear, editar, ordenar, archivar y configurar categorías desde la interfaz:

### Trabajo
- Trabajo DC
- Trabajo CERI

### Universidad
- Universidad Clase
- Universidad Tareas
- Universidad Estudio

### Proyectos y aprendizaje
- Proyecto de Unity
- Otros Proyectos
- Aprendizaje

### Salud y mantenimiento
- Entrenamiento
- Sueño
- Descanso
- Higiene
- Comidas

### Tiempo personal
- Ocio
- Social / Familia
- Doom Scrolling

### Logística
- Transporte
- Tareas del Hogar
- Actividades Inesperadas
- Otros

Cada categoría necesita al menos:

- `id`
- `user_id`
- `name`
- `slug`
- `group_name`
- `icon`
- `color`
- `sort_order`
- `is_active`
- `is_favorite`
- timestamps

No dependas del color como única forma de distinguir categorías.

## 5. Modelo de datos

Diseña migraciones, modelos, factories y seeders para:

### users
Añade preferencias de usuario o una tabla separada para:

- timezone
- week_starts_on
- audit_mode_enabled
- audit_started_at
- categoría prioritaria mostrada en Rainmeter
- categoría de fuga/límite mostrada en Rainmeter

### categories
Según los campos definidos arriba.

### time_entries
Campos mínimos:

- `id`
- `user_id`
- `category_id`
- `description`, nullable
- `started_at`
- `ended_at`, nullable
- `duration_seconds`, nullable o materializado de forma coherente
- `source`: `web`, `mobile`, `rainmeter`, `manual`, `system`
- timestamps

Reglas:

- Solo puede existir una entrada abierta por usuario.
- Iniciar una nueva actividad debe cerrar atómicamente la anterior usando la misma hora de cambio.
- No se permiten solapamientos tras ediciones manuales.
- Las operaciones de inicio/cambio/detención deben usar transacciones y bloqueo apropiado para evitar dobles actividades por clics simultáneos.
- Una actividad puede cruzar medianoche o el inicio de una semana. Los reportes deben repartir correctamente su duración entre días y semanas.
- La actividad abierta cuenta hasta `now()` en todos los cálculos.

### weekly_budgets

- `id`
- `user_id`
- `category_id`
- `week_start`
- `budget_type`: `minimum`, `maximum`, `reference`
- `target_minutes`
- timestamps

Permite copiar el presupuesto de la semana anterior y definir valores predeterminados recurrentes.

### weekly_reviews

- `id`
- `user_id`
- `week_start`
- `biggest_time_leak`
- `most_neglected_priority`
- `what_worked`
- `what_did_not_work`
- `next_week_adjustment`
- `notes`
- timestamps

### personal_access_tokens
Usa Sanctum y habilidades/scopes. El token de Rainmeter solo tendrá `time:read`.

## 6. Flujos principales

### Iniciar o cambiar actividad

Desde la pantalla principal deben aparecer primero las categorías favoritas. Al pulsar una:

1. Obtén la hora actual del servidor.
2. Cierra la actividad abierta, si existe.
3. Crea la nueva actividad.
4. Devuelve el estado actualizado.
5. Actualiza la UI optimísticamente, pero revierte y muestra un error claro si el servidor falla.

La descripción es opcional y nunca debe impedir el inicio rápido.

### Detener actividad

Cierra la actividad actual con la hora del servidor. No crees entradas de duración cero salvo que sean necesarias y estén justificadas.

### Corrección manual

Permite:

- Crear una entrada pasada.
- Cambiar categoría, descripción, inicio y fin.
- Eliminar con confirmación.
- Detectar solapamientos antes de guardar.
- Mostrar claramente las horas en la zona horaria del usuario.

### Registro rápido móvil

La pantalla inicial en móvil debe tener:

- Actividad actual y cronómetro.
- Botón detener.
- Categorías favoritas grandes y fáciles de pulsar.
- Categorías agrupadas dentro de una sección expandible.
- Últimos registros con edición rápida.

El inicio o cambio de actividad debe requerir un solo toque.

## 7. Auditoría inicial y presupuestos

Implementa dos modos:

### Auditoría inicial

- Por defecto dura 7 días.
- Durante la auditoría se enfatiza medir con honestidad, no cumplir metas.
- Muestra cobertura de registro diaria y semanal.
- No presentes el Doom Scrolling como un fracaso moral; el objetivo es observar patrones.

### Presupuesto semanal

Después de la auditoría, permite asignar minutos por categoría:

- `minimum`: meta que conviene alcanzar, por ejemplo Proyecto de Unity o Entrenamiento.
- `maximum`: límite que no conviene superar, por ejemplo Doom Scrolling.
- `reference`: cantidad informativa sin evaluación positiva o negativa, por ejemplo Transporte.

Visualiza:

- minutos presupuestados
- minutos reales
- diferencia
- porcentaje
- estado: dentro de presupuesto, faltante o excedido

No conviertas Sueño, Descanso, Ocio o Social/Familia automáticamente en tiempo perdido.

## 8. Pantallas

### Onboarding

- Explica brevemente el enfoque.
- Selecciona zona horaria.
- Permite iniciar auditoría de 7 días.
- Permite escoger categorías favoritas.

### Hoy

- Actividad actual con cronómetro en vivo.
- Botones favoritos.
- Tiempo registrado hoy.
- Cobertura desde medianoche hasta ahora.
- Distribución por categorías.
- Línea de tiempo del día.
- Huecos sin registrar claramente visibles.
- Historial editable.

### Semana

- Selector de semana.
- Tiempo registrado y cobertura.
- Tabla presupuesto versus realidad.
- Gráfica por categorías.
- Prioridad más descuidada.
- Categoría que más excedió su límite.
- Comparación con semana anterior.

### Presupuestos

- Configurar meta mínima, límite máximo o referencia por categoría.
- Presupuesto en minutos y ayuda para mostrar horas equivalentes.
- Copiar semana anterior.
- Guardar plantilla recurrente.

### Historial

- Filtros por fecha, categoría y texto.
- Edición y eliminación.
- Exportación CSV.

### Revisión semanal

Formulario guiado para aproximadamente 20 minutos:

1. ¿Qué categoría consumió más tiempo del esperado?
2. ¿Qué prioridad recibió menos tiempo del planeado?
3. ¿Qué funcionó?
4. ¿Qué no funcionó?
5. ¿Qué ajuste concreto haré la próxima semana?

Muestra los datos relevantes junto al formulario para no depender de memoria.

### Configuración

- Perfil y zona horaria.
- Categorías.
- Favoritos.
- Categoría prioritaria de Rainmeter.
- Categoría de fuga de Rainmeter.
- Crear, listar y revocar tokens de Rainmeter.
- Mostrar el token solo una vez al crearlo.

## 9. API JSON de la aplicación

Diseña endpoints REST consistentes para:

- autenticación
- estado actual
- iniciar/cambiar actividad
- detener actividad
- CRUD de entradas
- CRUD de categorías
- presupuestos
- reportes diarios y semanales
- revisiones semanales
- exportación CSV

Usa Form Requests, Resources, Policies y Services/Actions donde ayuden a mantener el código claro. No pongas toda la lógica en controladores.

## 10. Endpoint exacto para Rainmeter

Implementa:

```http
GET /api/rainmeter/status
Authorization: Bearer <token>
Accept: text/plain
```

Requisitos de seguridad:

- Sanctum.
- Habilidad obligatoria `time:read`.
- Rate limit razonable, por ejemplo 30 solicitudes por minuto por token; Rainmeter normalmente consultará cada 15 minutos.
- HTTPS en producción.
- El token no puede iniciar, detener, editar ni eliminar actividades.

La respuesta debe usar `Content-Type: text/plain; charset=UTF-8` y contener **exactamente** estas líneas y en este orden, sin JSON, HTML, comentarios ni líneas adicionales:

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

Semántica:

- `server_time_unix`: Unix epoch actual del servidor.
- `current_activity_active`: `1` o `0`.
- `current_activity_name`: nombre sin saltos de línea.
- `current_activity_started_at`: `HH:mm` en la zona horaria del usuario, o vacío.
- `current_activity_elapsed_seconds`: duración hasta `server_time_unix`.
- `today_tracked_minutes`: total de hoy, incluyendo la actividad abierta hasta ahora.
- `today_elapsed_minutes`: minutos desde medianoche local hasta ahora.
- `week_tracked_minutes`: total desde el lunes 00:00 local hasta ahora.
- `week_elapsed_minutes`: minutos transcurridos desde el lunes 00:00 local hasta ahora.
- `priority_*`: categoría seleccionada por el usuario y su presupuesto de tipo `minimum` para esta semana.
- `leak_*`: categoría seleccionada por el usuario y su presupuesto de tipo `maximum` para esta semana.
- Sin actividad: active `0`, nombre e inicio vacíos y elapsed `0`.
- Sin presupuesto: valor de presupuesto/límite `0`.

Sanitiza los nombres reemplazando saltos de línea por espacios. Usa una clase dedicada, por ejemplo `RainmeterStatusService`, y una respuesta de texto explícita. No uses una vista Blade.

## 11. Cálculos y casos límite

Crea una capa de servicios bien probada para:

- repartir entradas que cruzan medianoche
- repartir entradas que cruzan el lunes 00:00
- incluir entradas abiertas hasta una hora de referencia
- calcular cobertura sin superar 100% por solapamientos, los cuales deberían impedirse
- trabajar siempre con instantes UTC en base de datos y convertir a la zona horaria del usuario en los límites y presentación
- manejar cambios de zona horaria con coherencia
- evitar diferencias entre reportes web y Rainmeter usando los mismos servicios de agregación

## 12. Experiencia y diseño

- Diseño oscuro, limpio y compacto, compatible visualmente con la skin de Rainmeter.
- Acento púrpura como valor inicial, pero configurable.
- Excelente contraste y navegación por teclado.
- Estados vacíos útiles.
- Confirmaciones solo donde evitan pérdidas; no agregues diálogos innecesarios al inicio rápido.
- Mensajes de error accionables.
- No uses gamificación agresiva, culpa ni lenguaje que convierta el descanso en fracaso.

## 13. Pruebas obligatorias

Backend:

- solo una actividad abierta por usuario
- cambio atómico de actividad
- solicitudes simultáneas
- detener sin actividad abierta
- rechazo de solapamientos
- entrada que cruza medianoche
- entrada que cruza semana
- actividad abierta incluida en totales
- zona horaria `America/Guatemala`
- presupuestos minimum y maximum
- endpoint Rainmeter exige `time:read`
- endpoint Rainmeter conserva exactamente orden, nombres y formato de líneas
- token revocado deja de funcionar

Frontend:

- timer de actividad actual
- inicio rápido
- cambio y detención
- estados de carga/error
- edición con detección de solapamiento
- representación correcta de minimum versus maximum

Incluye una prueba de contrato que compare la respuesta completa del endpoint Rainmeter contra un string esperado.

## 14. Datos de desarrollo

Crea un seeder de demostración con:

- categorías predeterminadas
- una semana de registros variados
- Proyecto de Unity como prioridad mínima de 600 minutos
- Doom Scrolling como límite máximo de 120 minutos
- entradas que permitan ver reportes, huecos y presupuestos

No ejecutes seeders destructivos en producción.

## 15. Despliegue

Prepara:

- Dockerfile(s) y `docker-compose.yml` si son necesarios
- configuración de producción
- colas si las utilizas
- scheduler para recordatorios/revisiones solo si aporta valor real
- health check
- guía para desplegar en `tiempo.dernait.com`
- configuración esperada detrás de Cloudflare y HTTPS
- comandos exactos de migración, build y puesta en marcha
- estrategia simple de backup de base de datos

No incluyas secretos reales en el repositorio.

## 16. Entregables

Al finalizar entrega:

1. Aplicación funcional.
2. Migraciones, modelos, seeders y factories.
3. API web JSON.
4. Endpoint Rainmeter de texto plano.
5. Pruebas backend y frontend pasando.
6. `.env.example`.
7. README con instalación local, despliegue y creación del token Rainmeter.
8. Documento corto de arquitectura y decisiones.
9. Lista de funciones implementadas y pendientes reales, sin afirmar que algo funciona si no fue probado.

Empieza presentando el plan de implementación basado en el estado real del repositorio y luego ejecútalo por fases sin detenerte a pedirme confirmación salvo que falte una credencial, una decisión irreversible o información que no pueda inferirse de forma segura.
