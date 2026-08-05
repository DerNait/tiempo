PRESUPUESTO DE TIEMPO — SKIN PARA RAINMETER
================================================

INSTALACIÓN
1. Abre TimeBudget_1.0.0.rmskin.
2. Pulsa Install.
3. Rainmeter cargará TimeBudget\TimeBudget.ini.

PROBAR EL DISEÑO
- La skin viene con DemoMode=1 y muestra datos de ejemplo.
- El botón ACTUALIZAR funciona en demo sin hacer peticiones.

CONECTAR LA API
1. Clic derecho sobre la skin > Editar configuración.
2. Abre @Resources\Settings.inc.
3. Configura ApiUrl, ApiToken y DashboardUrl.
4. Cambia DemoMode=0.
5. Guarda y refresca la skin.

FRECUENCIA
- Update=1000 y ApiUpdateRate=900 equivalen a una petición cada 15 minutos.
- ACTUALIZAR fuerza una consulta inmediata usando el comando Reset de WebParser.
- El cronómetro de la actividad actual continúa localmente cada segundo, sin consultar al servidor.

SEGURIDAD
- Usa HTTPS.
- Crea un token exclusivo de solo lectura con permiso time:read.
- No compartas Settings.inc porque contiene el token.
- Nunca pongas tu contraseña en Rainmeter.

CONTRATO
Consulta @Resources\API_CONTRACT.md. El endpoint devuelve texto plano con un orden fijo para que Rainmeter pueda extraer todos los datos con una sola petición.

DESINSTALACIÓN MANUAL
Elimina Documentos\Rainmeter\Skins\TimeBudget y refresca Rainmeter.
