# Arquitectura y decisiones

## El problema real

Casi todo el riesgo de este producto está en dos sitios: que los números no
cuadren entre pantallas, y que registrar cueste demasiado esfuerzo. Todo lo
demás es CRUD. Las decisiones de abajo salen de ahí.

## Instantes en UTC, límites en la zona del usuario

`time_entries.started_at` y `ended_at` son instantes absolutos en UTC. Ninguna
consulta guarda hora local.

Lo que sí depende de la zona horaria son los **límites**: dónde empieza "hoy" y
dónde empieza "esta semana". `PeriodResolver` los calcula en la zona del usuario
y devuelve un `Period` (dos instantes). Así, una entrada que cruza medianoche o
el lunes 00:00 no necesita ningún tratamiento especial: se reparte sola cuando
se intersecta con cada periodo.

Consecuencia práctica: cambiar de zona horaria reinterpreta los límites de los
días pasados, pero nunca mueve un registro. Es el comportamiento correcto —
los eventos ocurrieron cuando ocurrieron.

Un detalle que costó una tanda de pruebas en rojo: el query builder vincula
objetos `DateTime` con su reloj de pared, sin convertir la zona. Un `Period` en
`America/Guatemala` comparado contra columnas UTC daba seis horas de desfase.
Por eso `TimeEntry::scopeOverlapping()` normaliza a UTC en el propio scope, y no
en cada llamada.

Por lo mismo, `week_start` se guarda y se consulta como cadena `Y-m-d` mediante
un `Attribute`: el cast `date` de Eloquent produce `Y-m-d H:i:s`, que en SQLite
deja de coincidir con la clave de semana que usan los `where`.

## Una sola capa de agregación

`TimeAggregationService` es el único sitio que convierte entradas en minutos. La
API web, los reportes y el endpoint de Rainmeter llaman a los mismos métodos, de
modo que **no pueden discrepar**. El requisito de que la skin y la web muestren
lo mismo no se resuelve con disciplina, se resuelve eliminando el segundo
camino.

El cálculo es siempre el mismo: la intersección entre el `Period` consultado y
el periodo que ocupa cada entrada, donde una entrada abierta ocupa hasta `now`.
De ahí salen totales, cobertura, huecos y desglose por categoría.

`coverage` se limita a 1.0 por defensa, aunque los solapamientos ya se impiden
en la escritura: un dato viejo o importado nunca debe dibujar una barra al 130%.

## Un único invariante de escritura

Toda escritura que cree o cierre actividades pasa por `TimeTrackingService`
dentro de una transacción, con `lockForUpdate` sobre el usuario y sobre la
entrada abierta. El invariante es: *como máximo una entrada abierta por usuario
y ningún solapamiento*.

Iniciar una actividad cierra la anterior **en el mismo instante**, no en dos
instantes cercanos: así no se inventan huecos ni se duplica tiempo.

Dos toques dentro del mismo segundo son un caso real en un teléfono. En vez de
guardar una entrada de duración cero, la primera se descarta (o se devuelve tal
cual si es la misma categoría, haciendo la operación idempotente). Es la única
forma de que "clics simultáneos" no dejen basura en el historial.

## Autorización: dos habilidades, no una

El endpoint de Rainmeter exige `time:read`. Eso por sí solo no bastaba: un token
con `time:read` seguía autenticando contra el resto de `/api`, así que podía
iniciar y detener actividades.

La solución es que **todas** las rutas de la app exijan `abilities:time:write`.
Las sesiones de navegador pasan siempre (Sanctum concede todas las habilidades a
una sesión), y un token de Rainmeter nunca. Hay una prueba que lo fija.

## Frontend

- **Estado optimista con reversión.** Pulsar una categoría pinta la actividad
  nueva de inmediato y guarda el estado anterior; si el servidor rechaza, se
  restaura y se muestra el mensaje del servidor. Sin esto, el registro de un
  toque no se siente instantáneo en móvil.
- **El cronómetro no consulta al servidor.** Un `tick` local avanza cada
  segundo; el estado completo se re-sincroniza cada minuto y solo con la pestaña
  visible. Un teléfono con la app abierta no debe martillear la API.
- **Detección de solapamientos en el cliente.** `lib/overlap.ts` replica la
  regla del servidor para convertir un 422 en aviso en línea mientras se edita.
  El servidor sigue siendo la autoridad.
- **La lógica pura vive fuera de los componentes.** Formato de duraciones,
  conversión de zona horaria y solapamientos son módulos sin Vue, y son los que
  tienen pruebas unitarias.

## Accesibilidad y tono

Las categorías se distinguen por icono y nombre, no solo por color. Los gráficos
van acompañados siempre de una lista con los mismos datos: el `canvas` es
decorativo. La navegación es operable con teclado y los focos son visibles.

El lenguaje evita convertir el descanso en fracaso: los huecos sin registrar se
describen como "lo que aún no observaste", los presupuestos `reference` no
puntúan, y durante la auditoría inicial no se evalúa nada.

## Qué no se hizo, y por qué

- **Sin cola de trabajos real.** El worker está desplegado por si hace falta,
  pero hoy no hay trabajos en segundo plano. No se añadieron recordatorios
  programados porque no aportan valor sin haber usado la app una semana.
- **Sin registro público.** Es una app de un solo usuario; el alta se hace con
  el seeder.
- **Sin modo claro.** El requisito era compatibilidad visual con una skin de
  Rainmeter oscura. El acento sí es configurable.
