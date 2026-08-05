# Skin de Rainmeter

Panel de escritorio que consulta `GET /api/rainmeter/status` y muestra la
actividad en curso, la cobertura de registro y el presupuesto semanal.

Esta carpeta es la copia versionada. La copia que Rainmeter ejecuta vive en
`Documentos\Rainmeter\Skins\TimeBudget`; ver *Sincronizar* más abajo.

## Instalar

```powershell
# Desde la raíz del repositorio, en PowerShell
Copy-Item -Recurse -Force rainmeter\TimeBudget "$env:USERPROFILE\Documents\Rainmeter\Skins\"
Copy-Item "$env:USERPROFILE\Documents\Rainmeter\Skins\TimeBudget\@Resources\Settings.inc.example" `
          "$env:USERPROFILE\Documents\Rainmeter\Skins\TimeBudget\@Resources\Settings.inc"
```

Después edita `@Resources\Settings.inc`:

1. `ApiToken=` con un token creado en **Ajustes → Rainmeter** de la app web.
   Solo tiene la habilidad `time:read`; no puede iniciar ni detener nada.
2. `DemoMode=0` para dejar de ver datos de ejemplo.
3. Refresca la skin (clic derecho → *Actualizar skin*).

`Settings.inc` está en `.gitignore` **porque contiene el token**. Al repositorio
solo sube `Settings.inc.example`, sin secreto.

## Sincronizar cambios

La skin se edita en la carpeta de Rainmeter, así que hay que traerse los
cambios de vuelta antes de commitear:

```bash
# Traer al repositorio lo editado en la carpeta de Rainmeter
rsync -a --exclude 'Settings.inc' --exclude '*.bak' \
    "/mnt/c/Users/$USER/Documents/Rainmeter/Skins/TimeBudget/" rainmeter/TimeBudget/
```

## Uso

- **Actualizar**: fuerza una consulta inmediata. Sin pulsarlo, la skin sondea
  cada 15 minutos (`ApiUpdateRate=900` × `Update=1000 ms`).
- **Abrir panel**: abre la app web.
- **Botón `–`** (bajo la insignia de estado): pasa a la barra compacta, que
  muestra solo el cronómetro y queda **siempre encima** de las demás ventanas.
  Clic en la barra o en `+` para volver. También está en el menú contextual.

El modo elegido se conserva: `Compact` se guarda en `Settings.inc` y el
"siempre encima" en el `Rainmeter.ini` del propio Rainmeter.

El cronómetro de la actividad en curso avanza localmente cada segundo, sin
consultar al servidor. La contrapartida del sondeo es que, si detienes una
actividad desde la web, la skin la seguirá mostrando en curso hasta la
siguiente consulta.

## Dos trampas de Rainmeter que costaron un rato

Están anotadas también en el código, porque no son evidentes y es fácil
reintroducirlas al editar.

### Las medidas hijas de WebParser devuelven la coincidencia completa

En una medida hija (`URL=[MeasureApi]`), `StringIndex=1` **no** devuelve el
primer grupo de captura sino el match entero. Con esto:

```ini
RegExp=(?si).*?ok=([01])(?:\r?\n|$)
StringIndex=1
```

el valor era `ok=1` en vez de `1`. Como no es un número, el valor numérico de
la medida quedaba en `0` y la skin creía que no había red, con los datos ya
descargados delante. Por eso cada campo usa un lookbehind anclado a inicio de
línea, de forma que la coincidencia entera ya es el valor:

```ini
RegExp=(?sim)(?<=^ok=)[01]
```

### Rainmeter intercambia cadenas con Lua en ANSI

Un acento escrito en `TimeBudget.lua` (UTF-8) se dibujaba como `Sin conexiÃ³n`.
Por eso **todos los textos visibles viven en `TimeBudget.ini`** (UTF-16, que sí
los respeta) y el `.lua` se mantiene en ASCII puro. Si añades un texto con
tilde, ponlo como variable en el `.ini` y léelo con `uiText()`.

Por lo mismo, `.gitattributes` marca los archivos UTF-16 con
`working-tree-encoding=UTF-16LE-BOM`: el repositorio guarda UTF-8 (diffs
legibles) y el checkout reconstruye el UTF-16 con BOM que Rainmeter necesita.

## Contrato

[`@Resources/API_CONTRACT.md`](TimeBudget/@Resources/API_CONTRACT.md) fija el
formato exacto de la respuesta. Del lado del servidor lo garantiza
`tests/Feature/RainmeterContractTest.php`, que compara el cuerpo completo
contra una cadena esperada.
