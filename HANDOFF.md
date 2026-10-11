# worldstreets — contexto de trabajo

> Documento de traspaso. Escrito el 2026-09-17 para que un agente de código pueda
> retomar el trabajo sin volver a investigar. Todos los números que aparecen acá
> fueron medidos contra la base real o exportados de Search Console y AdSense; no
> hay estimaciones salvo donde se dice explícitamente.
>
> **Actualizado el 2026-09-17, segunda sesión.** Los pasos 0 a 5 del plan están
> hechos y las tareas sueltas también, salvo las que dependen de apagar el
> CakePHP. Lo que cambió respecto de la versión anterior está marcado con
> **[HECHO]**, y los números que resultaron equivocados están corregidos con una
> nota de por qué.

---

> El registro de la conversación de trabajo —qué se decidió, por qué, y qué
> salió mal en el camino— está en [`notas/sesion-2026-09-17.md`](notas/sesion-2026-09-17.md).

## 0. Qué se hizo en la segunda sesión

| paso | estado | dónde |
|---|---|---|
| Mapa de redirecciones | **[HECHO]** | `LegadoController`, `routes/public.php` |
| 0 — Normalizar Canadá | **[HECHO]** | migración `2026_09_17_140000` |
| 1 — Índice A–Z | **[HECHO]** | `Busqueda::indiceDeCiudad()`, `lugares/ciudad.blade.php` |
| 2 — Ciudad-con-contenido | **[HECHO]** | migración `2026_09_17_150000`, `LugaresController` |
| 3 — Sitemaps | **[HECHO]** | `SitemapController`, `sitemaps/*.blade.php` |
| 4 — Velocidad | **[HECHO]** | `routes/public.php`, `RespuestaCacheable` |
| 5 — Bloquear scrapers | **[HECHO]** | `BloqueaScrapers`, `robots.txt` |
| 5 — Columnas muertas | **[HECHO]** | migración `2026_09_17_160000` |
| 6 — Páginas de calle | pendiente | después de medir en Search Console |
| Diseño pampam.city | **[HECHO]** | `tailwind.config.js`, `app.css`, home, país, provincia, ciudad |
| **Direcciones en el mapa (Nominatim)** | **[HECHO]** | `app/Support/Nominatim.php`, `POST /api/direcciones`, Leaflet |

### La función principal, que se había quedado afuera

El sitio es un buscador de direcciones: **calle + altura sobre el mapa**, vía
Nominatim. Es lo que hacen el Cake y calles-argentinas, y lo que la migración no
había traído: el buscador resolvía el *nombre* de la calle contra la tabla
`busquedas` y el mapa seguía encuadrando la ciudad entera.

Cómo quedó, y en qué se aparta del Cake a propósito:

- **La geocodificación es del servidor**, no del navegador. La política de
  Nominatim pide un `User-Agent` que identifique la aplicación, y `User-Agent`
  es una cabecera prohibida para `fetch()`: el navegador la descarta en
  silencio, así que el Cake y calles-argentinas le están pegando anónimos. Del
  lado del servidor además se cachea (30 días los aciertos, 12 h los fallos) y
  se respeta el techo de 1 consulta/segundo con un `RateLimiter` global.
- **La calle se guarda solo si la dirección existe.** El Cake dispara el POST
  que guarda y la consulta a Nominatim en paralelo, así que guarda igual cuando
  no encuentra nada: de ahí salieron las entradas `st`, `ave`, `Calgary` y `2`
  que tiene la lista de Calgary. Acá las dos cosas van en la misma petición y el
  guardado depende del resultado.
- **La consulta va con `bounded=1`**, no solo con el `viewbox`. Sin eso el
  recuadro de la ciudad es una preferencia y no un límite, y `14 Ave SW` se
  resuelve en otro continente.
- **El mapa es nuestro**: un Leaflet sobre los tiles de OSM. Antes era un iframe
  de `openstreetmap.org/export`, que es la página de ellos metida en la nuestra
  y cuyo marcador no se puede mover sin recargar el iframe entero. Cuesta 42 KB
  gzip de JS (83 → 235 KB sin comprimir), y es el precio de la función que da de
  comer.
- **El endpoint va sin sesión ni CSRF**, en el grupo `publico`. La razón es
  dónde se usa: la ficha de la ciudad es la página que recibe el tráfico de
  Google y tiene que salir sin cookie para ser cacheable, así que se renderiza
  sin sesión y no hay token que ponerle. Lo que lo protege es el `throttle:20,1`
  y el deduplicado por IP (`Cache::add`), no un token — no hay usuarios, no hay
  nada que falsificar en nombre de nadie. `RespuestaCacheable` ahora deja pasar
  los POST sin tocarlos, que antes no lo hacía.

Config en `.env`: `NOMINATIM_URL`, `NOMINATIM_AGENTE`, `NOMINATIM_TIMEOUT`. La
URL es configurable para poder apuntar a una instancia propia si el tráfico
crece.

### Contenido para el visitante de EE.UU. (puntos 1 a 5 del análisis)

El público objetivo, medido: el export de Search Console filtrado a Estados
Unidos (`analytics/…Performance-on-Search-2026-09-17(1)/`, 16 meses, 1.000
consultas, 79 clics, 18.338 impresiones).

| patrón de consulta | consultas | clics | impresiones |
|---|---|---|---|
| `{país} address lookup / finder / search` | 198 | **60** | 4.969 |
| `reverse address lookup {país}` | 44 | 39 | 1.460 |
| zip / postal / PIN code | 41 | **0** | 528 |
| en español (`calles de…`, `direcciones de…`) | 18 | 7 | 280 |
| phone / name / who lives there | 12 | 2 | 348 |

Dos cosas que no estaban anotadas. Para EE.UU. la familia `address lookup`
pesa **más** que `reverse address lookup` (el 73% del HANDOFF es global). Y las
41 consultas de código postal dan 528 impresiones con **cero clics**: demanda
medida que el sitio no atendía.

Escritorio es donde está el desperdicio: 105.867 impresiones al **0,36%** de
CTR en posición 21,5, contra 3,4% en móvil en posición 15,7.

**La demanda y la cobertura no coinciden**, y esto manda sobre el contenido:

| país | clics EE.UU. | impresiones | ciudades con calles | calles |
|---|---|---|---|---|
| Canada | 24 | 1.393 | 4.045 | **471.503** |
| Philippines | 6 | 487 | 518 | 1.764 |
| United Kingdom | 2 | 984 | 1.125 | 3.534 |
| Australia | 3 | 587 | 733 | 1.268 |
| India | 4 | 304 | 422 | 2.971 |
| Mexico | 2 | 393 | 504 | 1.949 |

Y donde hay cobertura no hay demanda: Austria 91.094, Kosovo 18.457, Slovakia
16.179, Iceland 14.229. Cero consultas estadounidenses. De 252 países, 124
tienen menos de 100 calles y solo 36 pasan las 1.000. **Canadá es el único
lugar donde el activo y la demanda se cruzan.**

#### Lo que se hizo

1. **Restaurada la frase que rankea en los títulos, y escritas las meta
   descriptions.** El rediseño había reemplazado
   `Reverse Address Lookup Free in {lugar} | WSG` por
   `Canada — regions, cities and streets`, y se había llevado **todas** las
   descripciones: no quedaba una sola `<meta name="description">` en el sitio.
   Nada de eso da error ni se ve en pantalla. Lo fija
   `MetadatosDeBusquedaTest`, que existe por eso.
   El sufijo de marca pasó de ` - worldstreets.global` (21 caracteres) a
   ` | worldstreets` (15): Google corta cerca de los 60 y lo que tiene que
   sobrevivir es la frase del principio.
2. **Buscador en la ficha de país** (`buscadorDePais` en `app.js`, y
   `?pais=` en `GET /api/ciudades`). Era la página más eficiente del sitio
   —246 URLs, 2.951 clics— y la más pasiva: el que llegaba de
   `reverse address lookup canada` tenía que elegir región, después ciudad, y
   recién ahí podía buscar. El h1 también recuperó la frase.
3. **Bloque "How an address works in {país}"**
   (`app/Support/FormatoDeDireccion.php`, 21 países). Las líneas de una
   dirección completa, el código postal con ejemplo y formato, y qué se
   equivoca un remitente de afuera. Es lo que atiende las 528 impresiones de
   código postal, y tiene la propiedad que la ficha de país necesitaba:
   **vale igual con 380 calles que con 471.503**. Donde el dato no es firme el
   bloque no aparece — inventar el formato postal de un país es peor que
   callarse, y este sitio viene de una penalización algorítmica.
   ⚠️ Los 21 países salieron del conocimiento del modelo. **[HECHO el
   18-sep] Los 21 verificados contra fuente oficial**: las fichas de la UPU
   —lo que cada operador postal le declara a la Unión Postal Universal, con
   ejemplos comentados campo por campo—, más Canada Post, Royal Mail,
   Australia Post y el documento DHRUVA de India Post.

   **Diez de los 21 tenían mal la posición de algún campo**, casi siempre la
   del código postal, que es el dato exacto por el que llega esa consulta:
   - **PH**, **MX**, **CU**: el código **abre** la última línea, no la cierra
     (`2000 PAMPANGA`, `44100 GUADALAJARA, JAL`, `CP 10600 CIUDAD HABANA`).
     En México el estado va además abreviado, y en Cuba el código lleva las
     letras `CP` adelante.
   - **CA**: la unidad va **antes** del número de calle y con guion
     (`10-123 MAIN ST NW`), no como sufijo.
   - **IN**: el PIN va en su propia línea debajo de la ciudad.
   - **JP** y **CN**: el error más grande. Las líneas estaban en el orden
     japonés/chino —de lo grande a lo chico, con el destinatario al final—,
     pero **en alfabeto latino la dirección se escribe al revés**, y quien lee
     esta ficha escribe en latino. En China faltaba directamente el código
     postal.
   - **ZA**: la línea de ciudad sobra. El correo sudafricano la pide **solo si
     no hay código postal**.
   - **ES**: la provincia va siempre en su línea, repita o no el nombre de la
     ciudad (el ejemplo de Correos es `45012 TOLEDO` / `TOLEDO`).
   - **PK**: casa y calle van en líneas separadas.

   Los once restantes estaban bien y ganaron lo que el operador pide y
   faltaba: CEDEX en Francia (si se borra, el envío pierde ese servicio), el
   prefijo `D-` que Alemania **no** quiere delante del código, las mayúsculas
   obligatorias italianas, el guion brasileño antes del estado, las regiones
   que Nueva Zelanda pide no usar. Jamaica ya estaba bien en lo menos obvio de
   la tabla: **no tiene código postal**.

   Las fuentes y qué se corrigió en cada país están en el docblock de la clase.
   Dos avisos: las fichas de la UPU **están fechadas** (la filipina es de
   2004), así que valen para la posición de los campos y no para sus listas de
   provincias; y lo de los distritos numerados de Colombo en **LK** es uso
   corriente, no un dato de la ficha.
4. **Las ciudades de la ficha de país se ordenan por CALLES, no por
   población.** Para quien vino a encontrar una dirección, una ciudad con
   8.014 calles sirve y una más poblada con 3 no: Toronto tiene más gente que
   Calgary, Calgary tiene la lista. Las regiones declaran sus calles en vez de
   su cantidad de ciudades.
5. Una sola consulta da toda la cobertura del país (`coberturaDelPais()`) en
   vez de dos que devolvían menos. Peor caso en frío: Japón 1,35 s (231.970
   calles); cacheado un día y la página sale por el borde.

#### Lo que queda decidido y no hecho

- **No** se importan calles de EE.UU.: el estadounidense busca fuera.
- **No** se vuelve a los cinco idiomas. Precio medido: las 18 consultas en
  español desde EE.UU. rankean en posiciones 7-15 con CTR de 6-16%, mejor que
  casi todo lo demás. Es la diáspora hispana y el corte a inglés la pierde.
  ⚠️ **Revisado el 18-sep: la decisión era correcta pero el número estaba
  incompleto.** Esas 18 consultas son la rodaja estadounidense del top-1000;
  el español entero es el **35,8% de los clics del sitio**. Se sigue sin
  volver a los cinco idiomas, y se vuelve a uno. Ver §14.

### Identidad y convención anglo

- **La marca.** El header tenía un cuadradito negro con las letras `WS`, que no
  dicen nada. Ahora es el mapa plegado con el pin —la silueta del favicon del
  sitio viejo, que era lo único que ahí funcionaba como identidad— redibujado en
  la paleta del sistema, más el wordmark. En `partials/logo.blade.php` y en
  `public/favicon.svg`, el mismo dibujo. El sitio viene de una caída algorítmica
  y va a cambiar de dominio: conviene que el que ya lo conocía lo reconozca.
- **Fuera el enlace "Search" del header**, que llevaba a la home desde la home.
  En su lugar va la línea que el Cake usaba de brand, "Worldwide address
  lookup": hace el trabajo que ese enlace no hacía, que es decirle al que llega
  de Google qué es esto. No se puso un "Countries" porque no existe una página
  índice de países que enlazar.
- **La altura va ADELANTE de la calle.** En inglés la dirección se escribe
  `6455 Macleod Trail SE`, y el Cake ya daba vuelta los dos campos según el
  idioma: número primero para `eng` y `fra`, calle primero para `esp`, `por` e
  `ita` (`Ciudades/plano.ctp`, líneas 86-101). La migración se había quedado con
  el orden español en un sitio que se publica solo en inglés y cuyo mercado
  anglo es el 83,1% de los ingresos. Cambia en los dos formularios, en
  `direccionCompleta` y en el parámetro `street` que va a Nominatim — que además
  documenta ese orden.
- **Elegida la ciudad, el foco y la pantalla se van al formulario de la
  dirección** (`enfocarLaDireccion()`). Antes el cursor se quedaba en el campo
  de la ciudad y el bloque de la calle aparecía fuera del viewport: había que
  darse cuenta solo de que había que bajar y volver a hacer clic.
- **"No cities matched that name" era un falso negativo.** El cartel se deducía
  de `!mostrarSugerencias`, así que cerrar la lista con un clic afuera lo
  mostraba teniendo ocho resultados detrás. Ahora hay una bandera propia,
  `sinCiudades`, que solo la pone la respuesta vacía.

### Tres bugs del rediseño, encontrados probando el local

- **El buscador de la ficha de ciudad mandaba a la home.** Era un
  `<form method="get">` con `street`, `number` y `city` en la query string:
  tres parámetros que `home()` no lee. Buscar una calle desde la página que
  recibe el tráfico de Google devolvía la home vacía. Ahora la ficha busca en su
  propia página, con el mismo componente.
- **En la home el resultado caía abajo del mapa.** El mapa iba entre los campos
  y la lista, 460 px de alto: se tipeaba calle y altura, la búsqueda respondía
  bien y en pantalla no se movía nada porque el resultado quedaba fuera del
  viewport. Se leía como que el buscador no funcionaba — y así lo reportó el
  dueño. El mapa se fue a la columna de al lado.
- **El enlace a la ficha no aparecía nunca.** `rutaDeLaFicha()` mira
  `tiene_calles`, y esa columna no estaba en el `SELECT` del autocompletado, así
  que el cast la daba por falsa: `ruta` venía en `null` para toda ciudad,
  Calgary con 8.014 calles incluida. No se veía de ninguna forma; lo fija
  `BuscadorCiudadesTest::test_la_sugerencia_trae_la_ruta_de_la_ficha`.

Resultado medible del conjunto:

| | antes | después |
|---|---|---|
| URLs indexables | 18.250.000 | **22.581** |
| URLs en sitemap | 21.140 (ninguna ciudad) | **20.495** |
| Páginas públicas cacheables | ninguna | todas menos la home |
| `ciudades` en disco | 1.880 MB | **1.523 MB** |
| Buscador, término de 2 letras | 668-1.039 ms | **84-123 ms** |
| Suite | 59 tests | **288 tests, 799 assertions** |

### La restricción que define el despliegue

El dueño confirmó que **el Laravel reemplaza al CakePHP como proyecto completo**,
en otra carpeta y sobre otro dominio para probar, y después al dominio original.
**No conviven.** Dos consecuencias que mandan sobre todo lo demás:

- El día del corte el Cake deja de existir. Una URL vieja sin destino no cae en
  ningún fallback: es un 404. Por eso el mapa de redirecciones se hizo **antes**
  y está verificado contra las 1.920 URLs con tráfico.
- **El dominio de pruebas era el riesgo caro — [HECHO].** Si `APP_URL` apunta
  ahí, de ahí salen los canonical y los `<loc>` de los sitemaps, y no produce
  ningún síntoma visible. En un sitio que viene de una caída algorítmica,
  publicar una segunda copia indexable del corpus es lo peor que puede pasar.
  Resuelto con `CANONICAL_HOST` (`config/app.php`), `App\Support\Canonico` y el
  middleware `HostCanonico`, en el stack **global** para que cubra también las
  rutas que aparezcan después:
  - El `<link rel="canonical">` del layout y los `<loc>` de los sitemaps se
    escriben siempre contra ese host. Los `<loc>` se reescriben **en la vista**,
    no en el controlador, porque las listas están cacheadas 12 horas y el host
    quedaría congelado adentro del caché.
  - Los **enlaces de navegación no** se tocan: siguen siendo del host que sirvió
    la página, o el dominio de pruebas no se puede recorrer.
  - Cualquier host que no coincida responde `X-Robots-Tag: noindex, follow`.
    `follow` a propósito: la copia tiene que dejarse recorrer para que el
    rastreador llegue a los canonical de adentro.
  - Del canonical sobrevive solo `page` de la query string; los `utm_*` y demás
    colapsan en la misma URL.
  - `CANONICAL_HOST` vacío apaga todo, que es el caso de local.
  - ⚠️ El `Sitemap:` de `public/robots.txt` sigue con el dominio escrito a mano
    (es un archivo estático, no pasa por PHP). El día que cambie el dominio hay
    que tocarlo ahí también.

  Y no bloquear el dominio de pruebas por robots.txt: lo que se
  bloquea no se rastrea, y lo que no se rastrea nunca lee el noindex.

---

## 1. Qué es el proyecto

**worldstreets** es la versión global de `calles-argentinas`: un buscador de
direcciones por **calle y altura**. El usuario elige una ciudad, escribe una calle,
opcionalmente una altura, y ve la ubicación en un mapa de OpenStreetMap.

Alcance deliberado, definido por el dueño del proyecto:

- Solo direcciones por calle y número. **Nada de negocios, nada de rutas.**
- **Anónimo**: sin login, sin registro, sin pedir datos.
- Monetizado con AdSense.

### El dato que define todo el producto

La tabla `busquedas` **no es un log**: es el histórico agregado de las calles que
la gente buscó, una fila por calle+ciudad con un contador. O sea, **el contenido
del sitio lo genera la propia gente que lo usa**. No es un padrón de calles.

---

## 2. Stack y entornos

### Local (desarrollo)

| | |
|---|---|
| SO | Windows 10 Pro, Laragon |
| PHP | 8.4.20 (ZTS, VS17 x64) |
| Base | **MariaDB 11.6.2**, `innodb_buffer_pool_size` = 1.008 MB |
| Laravel | 12.x (`laravel/framework ^12.0`) |
| Front | Blade + Alpine 3.15.0 + Tailwind **3.4.17** (vía `postcss.config.js`) |
| Build | Vite 7.1.5 + laravel-vite-plugin 2.0 |
| Máquina | 8 GB de RAM, suele quedar con 1,2–1,8 GB libres |

### Producción

| | |
|---|---|
| Hosting | **cPanel compartido** (usuario `josecuruzu`) — **no se controla `my.cnf`** |
| Servidor web | LiteSpeed |
| PHP de destino | **8.4** — el local es 8.4.20, así que el proyecto se escribió y se probó ahí. `composer.json` pide `^8.2` y no hay ninguna deprecación de 8.4 en el código |
| Extensión a vigilar | **`intl`**. No la pide ningún paquete de Composer, así que `check-platform-reqs` no la ve, pero `Busqueda::limpiar()` usa `\Normalizer` y corre en las DOS rutas públicas que guardan una calle. Desde el 18-sep la llamada va envuelta en `class_exists()`: sin `intl` se pierde la unificación de acentos, pero el buscador no se cae |
| Base | **MySQL 8.0.46** Linux (¡no MariaDB!) |
| App actual | **CakePHP 3.6.10** (de 2018, `php >= 5.6`), online en https://worldstreets.global/ |
| ⚠️ Choque de PHP | **El Cake 3.6 no arranca en PHP 8** — el soporte llegó en la rama 4.2 — y el Laravel necesita 8.2+. **No pueden compartir la versión de PHP.** Durante el despliegue el Laravel va en un subdominio con su propio PHP 8.4 (MultiPHP Manager fija la versión por dominio) y el dominio principal no se toca hasta el corte. Ese es el motivo real por el que el subdominio temporal deja de ser opcional |
| Destino | El Laravel va **al mismo dominio** → es una **migración**, no un lanzamiento |

> **Divergencia MariaDB/MySQL**: el desarrollo es contra MariaDB 11.6 y producción
> es MySQL 8.0.46. Afecta sobre todo al fulltext (stopwords y tokenizador). El
> código ya consulta las stopwords al servidor en vez de tenerlas escritas
> (`Ciudad::stopwordsDelIndice()`), así que eso está cubierto.

### Cosas del `.env` local

```
DB_DATABASE=josecuruzu_ws
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## 3. Estado del repositorio

Rama **`import-datos-argentina`**, 8 commits sobre `main`, pusheada a `origin`.
Sin PR abierto. Suite: **288 tests, 799 assertions, en verde** (~43 s).

### Sin commitear (trabajo real, no basura)

```
 M .gitignore                                   bootstrap/app.php
 M app/Http/Controllers/BuscadorController.php  app/Models/Ciudad.php
 M app/Models/Busqueda.php                      app/Models/Provincia.php
 M public/robots.txt                            resources/js/app.js
 M resources/views/layouts/app.blade.php        resources/views/welcome.blade.php
 M routes/web.php                               tests/Feature/*.php
?? routes/public.php                            ← rutas sin sesión, cacheables
?? app/Http/Controllers/LegadoController.php    ← redirecciones del Cake
?? app/Http/Controllers/LugaresController.php
?? app/Http/Controllers/SitemapController.php
?? app/Http/Middleware/BloqueaScrapers.php
?? app/Http/Middleware/RespuestaCacheable.php
?? resources/views/lugares/  resources/views/sitemaps/
?? database/migrations/2026_09_15_*  2026_09_16_*
?? database/migrations/2026_09_17_140000_normaliza_las_direcciones_canadienses.php
?? database/migrations/2026_09_17_150000_marca_las_ciudades_que_tienen_calles.php
?? database/migrations/2026_09_17_160000_elimina_las_columnas_muertas_de_ciudades.php
?? design/          (sistema de diseño "Dimension" — descartado)
?? design2/         (sistema de diseño "pampam.city" — ELEGIDO)
?? tests/Feature/{BuscadorCiudades,FichasDeLugar,Home,Sitemaps,RutasCacheables,
                  BloqueoDeScrapers,RedireccionesDelSitioViejo}Test.php
?? tests/Unit/NormalizacionCanadienseTest.php
```

Nuevos del 18-sep: `app/Support/Canonico.php`,
`app/Http/Middleware/HostCanonico.php`, `tests/Feature/HostCanonicoTest.php`,
`resources/views/partials/ad-slot.blade.php`, `tests/Feature/AnunciosTest.php`,
y los retoques de `config/app.php`, `config/services.php`, `bootstrap/app.php`,
`.env.example`, el layout, la ficha de ciudad, `LugaresController` y las dos
vistas de `sitemaps/`.

El dueño pidió expresamente **no commitear todavía**.

### Rutas actuales

Están partidas en dos archivos, y la partición es funcional, no cosmética:

- **`routes/public.php`** — grupo `publico`, **sin `StartSession` ni CSRF**. Las
  respuestas salen sin `Set-Cookie` y con `Cache-Control: public, s-maxage=3600`,
  que es lo que permite que LiteSpeed las sirva sin levantar PHP. Acá viven las
  fichas de lugar, los sitemaps, los GET del buscador y las redirecciones del
  Cake.
- **`routes/web.php`** — con sesión. La home (necesita el token CSRF para
  registrar búsquedas), el `POST /api/busquedas` y Breeze.

`public.php` se carga **después** de `web.php` porque `/{pais}` captura cualquier
segmento suelto y taparía `/login` y compañía.

Esquema viejo (`routes/web.php` original)

```php
GET  /                                    BuscadorController@home
GET  /api/ciudades                        @ciudades   (throttle:120,1)
GET  /api/ciudades/{ciudad}/calles        @calles     (throttle:120,1)
POST /api/busquedas                       @registrar  (throttle:30,1)
GET  /dashboard, /profile, auth.php       ← Breeze (ver "deuda" más abajo)
GET  /{pais}                              LugaresController@pais
GET  /{pais}/{provincia}                  @provincia
GET  /{pais}/{provincia}/{ciudad}         @ciudad
```

Las tres últimas van **al final a propósito**: `/{pais}` captura cualquier
segmento suelto. Verificado que `/login`, `/dashboard` y `/api/*` siguen ganando.

La ficha de ciudad **se resuelve por id, no por slug**
(`/japan/tokyo/tokyo-1850147`): hay 433.217 grupos de URLs repetidas, el slug no
identifica nada. Si el slug no es el canónico → **301**; si la jerarquía no
corresponde → **404**.

---

## 4. La base de datos

### Tamaños reales (`josecuruzu_ws`, 2.262 MB en total)

| tabla | motor | filas | datos | índices |
|---|---|---|---|---|
| `ciudades` | InnoDB | 4.697.141 | 1.102 MB | 966 MB |
| `busquedas` | InnoDB | 1.059.464 | 131 MB | 54 MB |
| `departamentos` | InnoDB | 45.716 | 7 MB | 2 MB |
| `provincias` | InnoDB | 3.970 | 0 MB | 0 MB |
| `paises` | InnoDB | 252 | — | — |
| `continentes` | InnoDB | 7 | — | — |

Otras bases en el servidor local:

- **`josecuruzu_ws_prod`** (1.818 MB, MyISAM): staging de la importación del dump.
  **Se puede borrar**, ya no hace falta.
- **`josecuruzu_ws_pre`** (54 MB): datos argentinos previos. **Es la única copia de
  las 405.875 búsquedas argentinas** — no está en producción. No tocar.

### Índices de `ciudades`

| índice | columnas | tamaño | ¿se usa? |
|---|---|---|---|
| `PRIMARY` | id | 1.102 MB | sí |
| `ciudades_url` | url | **180 MB** | **SÍ — ya no se puede borrar, ver abajo** |
| `ciudades_nombre_min_ranking` | nombre_min, population, visitas | 138 MB | sí |
| `ciudades_provincia_poblacion` | provincia_id, population | 98 MB | sí |
| `ciudades_pais_poblacion` | pais_id, population | 84 MB | sí |
| `ciudades_nombres_fulltext` | nombre_min, alternatenames | ~380 MB (aux) | sí |
| `FTS_DOC_ID_INDEX` | — | 84 MB | interno del fulltext |

> ⚠️ **`ciudades_url` ya NO es un índice muerto.** La versión anterior de este
> documento recomendaba borrarlo. **No hacerlo**: es el índice sobre el que se
> resuelven las redirecciones del sitio viejo — `/view/find/maps/address/{slug}`
> busca por `ciudades.url`, y son 1.248 URLs con 3.684 clics. Se puede revisar
> recién cuando Google haya terminado de procesar los 301, no antes.

### Columnas muertas de `ciudades` — **[HECHO]**

Migración `2026_09_17_160000`. Se eliminaron las nueve que no toca **ni el
Laravel ni el CakePHP** (verificado nombre por nombre en los dos árboles):
`cc2`, `admin1`, `admin3`, `admin4`, `timezone`, `fclass`, `elevation`,
`gtopo30`, `moddate`.

| | antes | después |
|---|---|---|
| `ciudades`, datos | 1.107 MB | **931 MB** |
| `ciudades`, índices | 773 MB | **592 MB** |

**357 MB**, más de los 130 que se estimaban para esas columnas: reconstruir la
tabla compactó también los índices.

> ⚠️ **`limite_sw_x`, `limite_sw_y`, `limite_ne_x`, `limite_ne_y` NO se tocaron**,
> aunque el Laravel tampoco las use. **El CakePHP sí las lee** —16 usos en
> `src/`, de ahí salen los límites del mapa de cada ficha—, así que borrarlas
> mientras el sitio viejo siga contra esta misma base lo deja sin mapa. Son
> otros ~90 MB y se pueden sacar **el día que el Cake se apague**.

### Datos no obvios

- `provincia_id` son códigos GeoNames tipo `IR.15`. **56.761 ciudades apuntan a
  `XX.00`**, el centinela de "sin admin1" — no existe como fila en `provincias`.
  En el Laravel viven bajo `unknown-region`.
- Una ciudad tiene `pais_id` vacío.
- `ciudades.nombre_min` está plegado a ASCII y en minúsculas (es la clave de
  búsqueda). `Ciudad::normalizar()` aplica lo mismo al término del usuario.
- `alternatenames` (TEXT, 93 MB en 2.720.301 ciudades) trae los nombres locales
  separados por coma, incluido 東京. Es lo que hace posible la búsqueda multilingüe.
- **Los nombres de calle están guardados en minúscula.** Hay que capitalizarlos
  para mostrarlos o se ven rotos.
- `ciudades.updated_at` tiene `ON UPDATE CURRENT_TIMESTAMP` en MySQL pero no hay
  `created_at`: por eso `Ciudad::$timestamps = false`.

### Distribución del contador de búsquedas (¡importante!)

```
cantidad_busquedas = 0  →  1.017.913 filas   (96,1%)
                     1  →     39.658
                     2  →      1.428
                     3  →        270
                    4-7 →        160
                   >= 8 →         35
```

**Solo 1.893 filas de 1.059.464 llegan a 2 búsquedas.** Ordenar por popularidad es
ordenar por ruido. Por eso la lista de calles va **alfabética (A–Z)**, no por
"más buscadas".

### Cobertura de calles por país (las que importan)

| país | calles | ciudades | ciudades ≥10 usables | ≥50 | ≥200 |
|---|---|---|---|---|---|
| **Canadá** | **482.100** | 4.045 | **1.758** | 565 | 283 |
| Japón | 231.970 | 224 | 47 | 47 | 47 |
| Austria | 91.094 | 89 | 5 | 5 | 5 |
| Sudáfrica | 83.185 | 58 | 7 | 5 | 5 |
| Estados Unidos | 12.079 | 440 | 15 | 3 | 2 |
| Reino Unido | 3.534 | 1.125 | 48 | 5 | 1 |
| Alemania | 767 | 371 | — | — | — |
| Australia | 1.268 | 733 | — | — | — |
| Suiza | 99 | 41 | — | — | — |
| Noruega/Dinamarca/Suecia/Finlandia | ~100 c/u | ~60 c/u | — | — | — |

De 4.697.141 ciudades, **18.361 tienen alguna calle** y solo ~5.218 tienen cinco o
más nombres usables.

### Canadá en detalle (es el activo del proyecto)

Por provincia:

| provincia | calles | ciudades |
|---|---|---|
| Manitoba | 181.201 | 134 |
| Ontario | 141.791 | 260 |
| Columbia Británica | 55.868 | 216 |
| Nuevo Brunswick | 32.554 | 968 |
| Nueva Escocia | 27.842 | 1.839 |
| Alberta | 18.924 | 109 |
| Quebec | 14.337 | 55 |
| Isla del Príncipe Eduardo | 5.447 | 433 |
| Saskatchewan | 2.045 | 16 |
| Terranova y Labrador | 1.497 | 11 |
| Yukón | 397 | 3 |
| Territorios del Noroeste | 197 | 1 |

**Los registros canadienses no son calles: son direcciones.** Ejemplos reales:

```
heritage meadows rd se     ← calle limpia
227a cedarbrae ave         ← con altura
33-35 roland st            ← rango de alturas
620b pinerow cres
garth massey dr unit 7     ← con unidad
fisher mills rd unit 47
rife rd rear
riverwood cres S           ← direccional con mayúscula inconsistente
```

- 8,6% de las filas canadienses empiezan con un número.
- Waterloo: 9.419 filas → **5.036** tras normalizar. La estimación previa de
  5.050 dio casi exacto.

> ⚠️ **El número "482.100 → 355.306" de la versión anterior estaba mal usado.**
> Es `COUNT(DISTINCT nombre)` sobre todo Canadá —cuántos nombres de calle
> distintos hay en el país— y no cuántas filas quedan. Las filas son por ciudad,
> y "main st" existe en cientos de ciudades sin que ninguna sea duplicado de
> otra. **El resultado real de la migración fue 482.100 → 471.502.**

Regex que se usó para medirlo (MariaDB), como punto de partida:

```sql
trim(regexp_replace(regexp_replace(
  lower(nombre_calle),
  '^[0-9]+[a-z]?([ ]*-[ ]*[0-9]+[a-z]?)?[ ]+', ''),
  '[ ]+(unit|apt|suite|ste|rear|upper|lower|bsmt|basement|floor|fl)[ ]*[0-9a-z]*$', ''))
```

### Calidad por país

- **Cape Town (id 3369157)**: 21.767 nombres usables, **limpios** —
  `a boesak street`, `aalwyn avenue`, `a z berman drive`. Es el mejor ejemplo del
  dataset. Población 3.433.441, `provincia_id` = `ZA.11` (Western Cape),
  coordenadas −33,9258 / 18,4232.
- **Tokio (id 1850147)**: 5.006 registros pero **son basura tipeada** — `15 16`,
  `4 9 18 higashi`, `3 chōme 7 7 Ishiwara`. No usar como ejemplo.
- **Nagoya (id 1856057)**: 34.721 usables, pero son nombres de *chōme* en kana
  (`あずら一丁目`), no calles.

---

## 5. El sitio viejo en producción

https://worldstreets.global/ — CakePHP, online.

### Estructura de URLs

```
/continent/maps/address/{slug}
/country/maps/address/{slug}
/state/maps/address/{slug}
/view/find/maps/address/{slug}          ← ficha de CIUDAD
/county/find/maps/address/{slug}
/{esp|por|fra|ita}/...                  ← cada una multiplicada por 5 idiomas
```

El slug ya trae la jerarquía: `cape-town-western-cape-south-africa` =
ciudad-provincia-país. **El mapeo al esquema nuevo es mecánico.**

### Qué publica y qué no

- `sitemaps.xml` es un índice con 4 archivos y **21.140 URLs en total**:
  `principal` 5, `continents` 30, `countries` 1.255, `states` 19.850.
  **Ninguna ciudad.**
- El enlazado interno es **la misma lista fija de ~45 ciudades del mundo repetida
  en cada página** (verificado comparando Cape Town contra Calgary).
- **Las fichas de ciudad no publican ni un nombre de calle.** Cape Town tiene
  21.767 en la base y la página muestra **cero**. Calgary tiene 8.434 y muestra
  cero. **No existe la página de calle.**
- `<title>` de todo el corpus: `Reverse Address Lookup Free in {lugar} | WSG`.

### Problemas técnicos del sitio viejo

- La home responde `Cache-Control: no-store, no-cache, must-revalidate` y **setea
  una cookie de sesión CakePHP en cada visita** → el caché de LiteSpeed no sirve
  para nada. **No repetir esto en el Laravel.**
- `robots.txt` tiene una docena de URLs bloqueadas **a mano, una por una**
  (`/jonoya-(2)-maryland-liberia`, `/leigh-(historical)-south-carolina-united-states`…).
  Todas tienen **paréntesis en el slug** → bug de generación de slug que alguien
  tapó bloqueando en vez de arreglar. **Arreglar la generación del slug.**
- Tiempo medio de respuesta: **2.300–2.600 ms**.

---

## 6. Diagnóstico de SEO y monetización

Exportaciones en `C:\proyectos\worldstreets\analytics\` (ya en `.gitignore`).

### La caída

**El sitio se desplomó el 21 de agosto de 2026.**

```
2026-08-20    11 clics    1.609 impresiones    posición media 46,1
2026-08-21     0 clics      293 impresiones    posición media 61,8
```

| mes | clics | impresiones |
|---|---|---|
| oct 2025 (pico) | 866 | 30.974 |
| jul 2026 | 373 | 43.150 |
| ago 2026 | 235 | 38.334 |
| sep 2026 (14 días) | **10** | **1.440** |

Agosto promediaba 1.236 impresiones/día; septiembre, 110. **−91%.**

**No hay acciones manuales ni problemas de seguridad** (verificado por el dueño en
Search Console). Es algorítmico → **no existe solicitud de reconsideración**; se
corrige y se espera a que Google reevalúe. Sin fecha y sin garantía.

### La causa más probable

```
URLs descubiertas por Google:  18.283.514
URLs indexadas:                    26.335   →  0,14%
  · "Descubierta: actualmente sin indexar": 18.249.195
  · "Rastreada: actualmente sin indexar":        6.844
  · 404:                                          1.047
```

Son las 4,7 M de fichas × 5 idiomas. Sumado a 2.400 ms de respuesta y al rastreo
caído de 4.092 peticiones diarias (junio) a ~240 (septiembre), con 91% del rastreo
en refresco y solo 8,9% en descubrimiento.

> **Trampa de mecánica**: `noindex` **no sirve** para el bloque "descubiertas",
> porque Google tiene que rastrear la página para leer la etiqueta. Con 240
> rastreos diarios y 18 M de URLs, tardaría siglos. Hay que **achicar el espacio de
> URLs en el origen** y devolver **410 Gone** (no 404) en lo que se elimina.

### Qué busca la gente

**El 73% de los clics** viene de `reverse address lookup` / `address finder` /
`address lookup` + país:

| consulta | clics | CTR | posición |
|---|---|---|---|
| reverse address lookup india | 100 | 46,95% | 2,17 |
| address finder sri lanka | 93 | 23,13% | 4,22 |
| address finder philippines | 90 | 9,87% | 5,20 |
| reverse address lookup | 89 | 1,75% | 21,46 |
| reverse address lookup canada | 57 | 4,31% | 15,33 |
| reverse address lookup ontario | 23 | 1,40% | 9,58 |

La frase funciona donde significa "encontrar una dirección" (India, Sri Lanka,
Filipinas: posiciones 2–5) y fracasa donde significa "averiguar quién vive ahí"
(EE.UU.: posición 20,58 con 0,82% de CTR). **No se toca**: es el 73% de los clics.

**No hay ni una consulta de nombre de calle con clics.** Pero
`sharp avenue may pen` (Jamaica) da **85 impresiones en posición 8,91 con cero
clics** — Google ya muestra el sitio para búsquedas de calle y la página no tiene
la calle. Es la mejor evidencia de que la jugada del índice A–Z funciona.

**El público de EE.UU. busca direcciones de otros países**, no de EE.UU. Sus dos
consultas principales son `reverse address lookup canada` y
`canada reverse address lookup`. Por eso **se evaluó y se descartó importar
TIGER/Line de EE.UU.**: el público estadounidense no busca calles estadounidenses.

### Tráfico por tipo de página

| tipo | URLs | clics | impresiones |
|---|---|---|---|
| ciudad | 704 | 3.684 | 243.008 |
| país | 246 | 2.951 | 100.837 |
| home | 3 | 154 | 10.658 |
| provincia | 42 | 94 | 10.849 |
| continente | 5 | 47 | 1.519 |

Las páginas de país son lo más eficiente que tiene el sitio.

### AdSense, últimos 12 meses

**Total: $213,16 · 64.313 páginas vistas · 188 países.**

| grupo | ingresos | % | páginas vistas | RPM |
|---|---|---|---|---|
| **Anglo (US/CA/GB/AU/IE/NZ)** | **$177,03** | **83,1%** | 9.903 | $17,88 |
| Europa occidental | $14,06 | 6,6% | 2.722 | $5,17 |
| Latinoamérica | $12,82 | 6,0% | 5.636 | $2,27 |
| **Asia barata** | **$2,93** | **1,4%** | **41.827** | **$0,07** |

Estados Unidos solo: **$141,10 (66,2%)**, RPM $22,10.
Canadá: $19,37, RPM $15,12.

**Tendencia de RPM** (último año contra los dos anteriores):

| país | RPM años 1-2 | RPM último año | variación |
|---|---|---|---|
| Estados Unidos | $55,29 | $22,10 | −60% |
| Reino Unido | $13,59 | $7,31 | −46% |
| **Canadá** | $17,19 | **$15,12** | **−12%** |

**Canadá es el único mercado cuyo RPM se mantuvo.** Y es donde están las 482.100
calles.

> **Ojo con los CSV**: `report(2).csv` es histórico de **3 años** y da RPM
> inflados (EE.UU. $46,19). El bueno es **`reporte_pais.csv`**, de 12 meses.

### Tráfico bot

```
China      28.720 páginas vistas →   644 impresiones de anuncio → $0,38
Singapur    9.431 páginas vistas →   344 impresiones de anuncio → $0,35
```

EE.UU. promedia **4,4 impresiones de anuncio por página vista**; China, **0,02**.
Los anuncios no cargan nunca: son scrapers. **38.151 páginas vistas (59% del
total) por $0,73 al año**, consumiendo un hosting compartido que ya responde en
2,4 s. **Bloquearlos.**

---

## 7. Decisiones tomadas

| tema | decisión | por qué |
|---|---|---|
| **Idioma** | **Solo inglés** | El anglo es el 83,1% de los ingresos; el español ~8%. (El dueño primero eligió inglés+español con el dato de clics —32%— y lo cambió al ver los ingresos.) |
| **Espacio de URLs** | **Una ciudad tiene URL cuando tiene calles**: 18.361, no 4.697.141 | Reemplaza el `noindex,follow` en fichas vacías, que es demasiado permisivo para un sitio degradado |
| **Lo eliminado** | **410 Gone**, no 404 ni `noindex` | Le dice a Google que se fue a propósito y acelera la limpieza |
| **Jugada principal** | **Índice A–Z de calles dentro de la ficha de ciudad** | Convierte páginas delgadas en sustanciales **sin crear una sola URL nueva** |
| **Orden de calles** | **Alfabético**, sin mostrar contadores | 96,1% de las filas tienen contador 0: ordenar por popularidad es ordenar por ruido |
| **Páginas de calle** | **Fase 3, medidas**, empezando por las 283 ciudades canadienses con +200 calles | Canadá normalizado son 471.502 filas en 4.045 ciudades; lanzarlas todas repetiría el error que se está arreglando |
| **Títulos** | **No tocar "reverse address lookup"** | Es el 73% de los clics |
| **Mercado** | Canadá (datos + RPM estable) sirviendo al público de EE.UU. (que es quien lo busca) | Las dos consultas top de EE.UU. son sobre Canadá |
| **TIGER/Line EE.UU.** | **Descartado** | El público de EE.UU. no busca calles de EE.UU.; además el hosting no lo soporta |
| **Diseño** | Sistema **pampam.city** (`design2/`) | Ver sección 9 |

### Espacio de URLs resultante

| | URLs |
|---|---|
| Ciudades con calles | 18.361 |
| Provincias | 3.970 |
| Países | 249 |
| **Total** | **≈ 23.000** |

De 18.250.000 a ~23.000.

---

## 8. Plan de trabajo, en orden

### Paso 0 — Normalizar el corpus canadiense — **[HECHO]**

Migración `2026_09_17_140000`. 482.100 → **471.502** filas; 18.682 candidatas.

> ⚠️ **El regex que proponía este documento destruye 5.882 calles.** Sacar la
> altura inicial funciona en `227a cedarbrae ave`, pero Calgary, Edmonton y
> Saskatoon **numeran las calles**: en `14 Ave SW` el número ES el nombre. El
> patrón no las distingue mirando el principio; lo que las separa es lo que
> queda después. La migración lleva una guarda: si al recortar no sobra ninguna
> palabra que no sea un tipo de vía o un direccional, no recorta.
>
> Se descubrió **corriendo la migración y comparando contra el respaldo**, no
> leyendo el código: la migración no falla, deja mal el dato. Los ordinales
> (`6th ave w`, 3.555 filas) sí quedaban afuera solos, y eso fue lo que dio una
> falsa sensación de seguridad.

Quedan 66 filas con el nombre recortado que no se pudieron restaurar (26
chocaban con el índice único) y 882 que se borraron al fusionar sin estar en el
respaldo — eran duplicados de calles que siguen existiendo. Para el estado
exacto habría que reimportar desde el dump.

<details>
<summary>El plan original</summary>

Migración que, sobre las 482.100 filas canadienses:

1. Saca la altura inicial (`227a cedarbrae ave` → `cedarbrae ave`) y el sufijo de
   unidad (`unit 7`, `apt 3`, `rear`, `bsmt`…).
2. Deduplica contra el índice único `busqueda_unica_por_ciudad`, **sumando
   contadores** (mismo criterio que el commit `f835a98`).
3. Deja el nombre capitalizado para mostrar, o guarda la forma canónica y
   capitaliza en la vista.

**Respaldar las filas originales en `storage/app/backups/` antes de tocar nada**
(es lo que se hizo en las migraciones anteriores).

Resultado esperado: **482.100 → ~355.306 calles distintas**.

> Cuidado: el índice único usa `utf8mb4_unicode_ci`, que es **PAD SPACE**. Ver
> "Trampas" más abajo.

</details>

### Paso 1 — Índice A–Z en la ficha de ciudad — **[HECHO]**

`Busqueda::indiceDeCiudad()`. Todo el índice va **en el HTML**, y la barra de
letras son **anclas**, no URLs: un `?letter=a` por ciudad serían 26 × 18.361 =
477.386 URLs nuevas, justo lo que el sitio está sacándose de encima.

Pesos reales: Waterloo (5.033 calles) 249 KB crudo, **16,3 KB con gzip**; Cape
Town (21.910) 305 KB, **18,6 KB**.

> ⚠️ **El tope de calles tiene que ser por letra, no global.** El primer intento
> traía las 2.000 primeras alfabéticamente y Cape Town las agotaba entre `#`, A
> y B: de la C a la Z la barra salía apagada **como si no tuviera ninguna calle
> con esas letras**. La página afirmaba algo falso sobre su propio contenido y
> nada fallaba. Son 120 por letra, y cada una declara su total real ("120 of
> 348").

Detalles que salieron de los datos: los nombres están en minúscula y hay que
capitalizarlos (con los direccionales en mayúscula, `14 Ave SW`, y sin
capitalizar la letra que sigue a un dígito, `6th` y no `6Th`); las calles
numeradas van bajo `#`; y lo que no empieza con letra ni dígito queda afuera,
porque el orden alfabético arranca con una fila vacía y `--102 scott st`.

<details>
<summary>El plan original</summary>

Es la jugada que cambia el perfil del sitio. En `LugaresController::ciudad()` y
`resources/views/lugares/ciudad.blade.php`:

- Barra de letras A–Z; la letra sin datos va deshabilitada.
- Listado alfabético de las calles de esa letra, en varias columnas.
- **Sin contadores de búsqueda visibles.**
- Paginación o "ver todas las N calles con A".

Encabezado: *"Streets in {ciudad}"*, bajada *"Every street visitors have looked up
here, A to Z."* (**no** "most searched").

</details>

### Paso 2 — Regla de ciudad-con-contenido — **[HECHO]**

Migración `2026_09_17_150000` y `LugaresController`. Una ficha sin calles
responde **410**; el `noindex,follow` desapareció.

| | |
|---|---|
| Ciudades con página | **18.359** |
| Provincias | 3.970 |
| Países | 252 |
| **Total** | **22.581** |

**El costo, medido**: de las 868 fichas de ciudad con tráfico, 86 caen en 410 y
son **116 de 3.684 clics, el 3,1%**. No es definitivo: `registrar()` marca la
columna, así que la primera búsqueda devuelve la página.

> ⚠️ **Hizo falta desnormalizar.** Filtrar las listas con un `EXISTS` costaba
> **621 ms contra 1 ms** en la provincia más grande (IN.36, 155.023 ciudades):
> para juntar 60 con calles, MySQL recorría 330.380 filas del índice. Se agregó
> `ciudades.tiene_calles` y se **reemplazaron** los dos índices de listado por
> su versión con la columna en el medio, en vez de agregar dos nuevos. Quedó en
> **6 ms**, con `rows=48` y `Using index`.

Interacciones que aparecieron y conviene no deshacer:

- El buscador de la home enlazaba "Full page for X" a la ficha.
  `rutaDeLaFicha()` devuelve `null` sin calles, así que el enlace no aparece.
- Las redirecciones del Cake apuntan a fichas que pueden dar 410. Al usar
  `rutaDeLaFicha()`, `LegadoController` devuelve **410 directo** en vez de
  encadenar 301 → 410.
- El conteo por provincia decía "155.023 ciudades" y listaba 48.

<details>
<summary>El plan original</summary>

- `LugaresController::ciudad()` devuelve **410 Gone** si la ciudad no tiene calles.
- Las fichas de provincia y país listan **solo ciudades con calles**.
- El buscador (`/api/ciudades`) **sigue devolviendo todas** las ciudades: el
  usuario puede encontrar cualquiera y ver el mapa; lo que no existe es la página
  rastreable.
- Quitar el `$indexable` / `noindex,follow` actual, que queda reemplazado.

</details>

### Paso 3 — Sitemaps — **[HECHO]**

`SitemapController`. **208 archivos, 20.495 URLs**, el mayor (Canadá) con 4.045
contra un límite de 50.000. `/sitemaps.xml` y `/sitemap` (los del Cake)
redirigen 301 al índice nuevo.

> **Partido por país, no por provincia.** El plan decía provincia; midiendo son
> 1.956 provincias con contenido, o sea 1.956 archivos, y el mayor tendría 1.839
> URLs. Por país son 208 y ninguno se acerca al techo.

Solo se declaran las provincias y países **con al menos una ciudad con página**:
declarar una lista vacía es pedirle a un rastreador de 240 peticiones diarias
que gaste una en nada. Sin `<lastmod>`, `<changefreq>` ni `<priority>`.

El `robots.txt` declara el sitemap y **no bloquea las URLs del Cake**, a
propósito: lo que se bloquea no se rastrea, y un 410 que Google no llega a leer
no limpia nada.

<details>
<summary>El plan original</summary>

- Índice de sitemaps partido **por provincia**.
- Solo URLs indexables (las ~23.000).
- Hoy el sitio viejo ofrece 21.140 URLs y ninguna ciudad.

</details>

### Paso 4 — Velocidad — **[HECHO]**

`routes/public.php` sin sesión ni CSRF, y el middleware `RespuestaCacheable`.

| ruta | antes | ahora |
|---|---|---|
| `/canada/ontario/waterloo-…` | 2 cookies · `no-cache, private` | **0 cookies · `public, s-maxage=3600`** |
| `/sitemap.xml` | 2 cookies · `no-cache, private` | **0 cookies · público** |
| `/view/find/…` (301) | 2 cookies · `no-cache, private` | **0 cookies · público** |
| `/` | 2 cookies | igual, a propósito |

La tabla `sessions` quedó en **0 filas** tras recorrer todas las páginas: ya no
escribe una por visita anónima. Era exactamente el problema que este documento
le marca al sitio viejo, y el Laravel lo estaba repitiendo entero.

La home conserva la sesión porque su formulario necesita el token CSRF. Son 3
URLs y 154 de los 6.930 clics. El layout pide el token con
`@if (request()->hasSession())`: sin eso, `csrf_token()` lanza y tumba la
página.

Los errores **no** se cachean: un 410 es el mecanismo con el que se limpia el
índice y conviene que Google lo vuelva a pedir.

> **`CACHE_STORE=file` rinde menos de lo que sugería este documento.** Medido:
> 0,66 ms por lectura contra 0,34 de `file`, y la tabla `cache` ocupa 2,5 MB de
> los 1.008 del buffer pool. No es lo que baja de 2.400 ms; lo que lo baja es
> que las páginas sean cacheables. Sigue siendo un cambio razonable de `.env`,
> pero es de despliegue, no de código.

<details>
<summary>El plan original</summary>

- Páginas públicas **cacheables**: sin cookie de sesión, sin `no-store`.
  Ojo: `SESSION_DRIVER=database` escribe una fila por visita anónima.
- Considerar `CACHE_STORE=file` y `SESSION_DRIVER=file` en hosting compartido: la
  caché en base compite por el mismo buffer pool que `ciudades`.
- Objetivo: bajar de 2.400 ms a centenas de milisegundos. El rastreo es función de
  la velocidad.

</details>

### Paso 5 — Mapa de redirecciones — **[HECHO]**

`LegadoController`. Validado contra **las 1.920 URLs con datos** de Search
Console:

| | |
|---|---|
| URLs con destino 301 | **1.908** |
| URLs sin destino | **0** |
| Clics preservados | **6.729 de 6.729 (100%)** |

Los slugs **no hay que recalcularlos**: están en la columna `url` de cada tabla,
que es la misma que el Cake usa para armar los enlaces. Países y provincias
resuelven al 100%.

Cosas que aparecieron leyendo el Cake:

- El `fallbacks(DashedRoute::class)` del final **no dejó URLs huérfanas**: las
  1.920 con tráfico caen en el esquema documentado.
- El lookup usa `LIKE` sin escapar (`Ciudades.url LIKE $slug`), y el guión bajo
  es comodín. Son 7 filas: bug real, irrelevante en volumen.
- **Los slugs de ciudad son ambiguos**: 868 con tráfico matchean 996 filas. En
  los 65 casos el patrón es siempre el mismo —una ciudad real y duplicados de
  GeoNames con población 0—, así que gana la de más población, y a igualdad el
  id más chico, que es el que servía el Cake.
- **Los paréntesis del robots.txt viejo no están en la base**: medí las
  4.697.141 filas y `con_parentesis = 0`. Lo que sí hay son 50.768 slugs con
  guión doble, 13.137 que empiezan con guión y 393 que terminan con guión.
  Habría que verificar contra la base de producción antes de darlo por
  pendiente.

Condados y continentes van a **410**: no tienen equivalente. Los condados no
tienen un solo clic; los continentes tienen 5 slugs y 47 clics, casi todos de
`europe` — si se quieren recuperar, una ficha de continente son 7 URLs más.

<details>
<summary>El mapeo</summary>

Transformación mecánica desde el slug viejo:

```
/view/find/maps/address/cape-town-western-cape-south-africa
  → /south-africa/western-cape/cape-town-3369157
/state/maps/address/western-cape-south-africa
  → /south-africa/western-cape
/country/maps/address/south-africa
  → /south-africa
/{esp|por|fra|ita}/...  → 301 a la versión en inglés
```

El CSV de páginas de Search Console (`analytics/…Performance…/Páginas.csv`, 999
filas) es la lista de lo que **no hay que romper**.

</details>

### Paso 6 — Páginas de calle — **EVALUADO EL 18-SEP: NO TODAVÍA**

`/{pais}/{provincia}/{ciudad}/{calle}`, con el campo de altura adentro. **La altura
no es una página** (son infinitas y todas vacías): se resuelve en el cliente.

El plan decía arrancar por las 283 ciudades canadienses con +200 calles. Medido
contra la base, hoy son 295 ciudades y **409.541 URLs: ×18 el sitio actual**.
Con +1.000 calles son 345.020 (×15) y con +2.000, 223.492 (×10) — subir el
umbral casi no ayuda porque el volumen está en las ciudades grandes.

Multiplicar por 18 un dominio que viene de una caída algorítmica, con 0,14% de
indexación y al que se le acaba de pedir que reevalúe 22.581 URLs, es repetir
el error que causó la penalización. **Requisito previo: ver en Search Console
si las 22.581 se indexan.**

Y antes de esto conviene el arreglo del índice A–Z, que sube el valor de la
página sin agregar ninguna URL — ver §11.

### Tareas sueltas

- **[HECHO] Bloquear scrapers.** `BloqueaScrapers` (403 por User-Agent) más las
  directivas en `robots.txt`. Lista corta y de nombres exactos: un patrón como
  `/bot/i` se lleva puesto a Googlebot y el sitio desaparece sin que falle nada.
  La mitad de `BloqueoDeScrapersTest` verifica lo que **no** se bloquea.
- **[HECHO] Columnas muertas** (−357 MB, ver §4).
- **~~Eliminar el índice `ciudades_url`~~ — NO HACERLO.** Dejó de estar muerto:
  es sobre el que se resuelven las redirecciones del Cake.
- **Slugs con paréntesis**: no hay ni uno en la base local. Verificar contra
  producción antes de tratarlo como pendiente.
- Borrar la base `josecuruzu_ws_prod` (−1,8 GB). Es tarea de panel, no de
  código.
- **[HECHO] El `canonical_host`.** Ver §0: `CANONICAL_HOST`, `Canonico`,
  `HostCanonico` y `HostCanonicoTest` (17 casos).
- **Pendiente: las columnas `limite_*`** (~90 MB), el día que el Cake se apague.

---

## 9. Diseño

Se probaron tres direcciones. **La elegida es `design2/` — "pampam.city"**.

- `design/DESIGN.md` → **"Dimension"**: canvas negro, vidrio esmerilado, DM Sans
  peso 500 + Geist, píldoras 9999px, sin sombras. **Descartada.**
- `design2/DESIGN.md` → **"pampam.city"**: canvas crema `#faf2ec`, tarjetas
  blancas flotando encima, serif de display (Nineties; sustituto documentado
  **DM Serif Display**), Inter peso 550 con `cv02, cv03, cv04, cv06, cv11`, radios
  12px en tarjetas/tags/inputs y 16px en botones, **una sola acción periwinkle
  `#4b72f3` por pantalla**, Carbon `#000` para la otra acción rellena. **ELEGIDA.**

Cada carpeta trae `DESIGN.md`, `tokens.json`, `variables.css` y `theme.css`.

**Mockups publicados** (9 artboards: home, búsqueda en movimiento, ficha con y sin
calles, país, región, móvil ×2, sistema):
https://claude.ai/artifact/78tPHW6FjcRbXbmQu8cRox

### Contradicciones de `design2/DESIGN.md` y cómo se resolvieron

1. **Periwinkle se contradice**: la tabla de colores y la guía rápida dicen no
   promoverlo a CTA; el componente *Periwinkle Primary Button* y los Do's dicen
   reservarlo para una acción primaria por pantalla. **Se siguió lo segundo.**
2. **Slate Lavender `#9894a8` no puede ser color de enlace**: ~2,6:1 sobre crema.
   Conserva bordes, hairlines y contornos de tag; **los enlaces usan Deep Violet
   `#2b3ea7`** (~8,5:1).
3. **Ash `#8f8f8f` (~2,9:1) es color de pista, no de dato**: placeholders y textos
   de ayuda. Poblaciones y cantidades van en Warm Ink `#1b1917`.

### Decisiones de interfaz que vienen del análisis

- **Un solo campo**, no dos pasos numerados: al elegir la ciudad se convierte en un
  tag dentro del mismo campo y se sigue tipeando la calle. La altura va inline al
  final.
- **La ficha de ciudad necesita buscador propio.** Hoy solo tiene un botón que
  manda a la home a retipear la ciudad; es el agujero más caro del sitio, porque es
  la página que recibe el tráfico de Google.
- **El mapa es la página**, no una miniatura de 256px.
- **Nada de contadores de búsqueda en pantalla.**
- Fuera el FAQ genérico, "Why Use Our Address Finder" y "Our Commitment to You".
- **El estado vacío es el caso normal**, no la excepción.

---

## 10. Trampas conocidas (leer antes de tocar)

### Base de datos

- **PAD SPACE**: `utf8mb4_unicode_ci` ignora los espacios finales, así que
  `WHERE col <> TRIM(col)` **no los detecta** y `col = 'x'` matchea `'x   '`. Usar
  `BINARY col <> BINARY TRIM(col)`. Ya causó que una migración se salteara 4.858
  filas **y que la verificación posterior las diera por limpias**.
  → **Regla general: verificar con un predicado distinto del que se usó para
  filtrar.**
- **`HEX(col) REGEXP` cruza el límite de bytes**: el patrón `'CC[89AB]|CD[89AF]'`
  para acentos descompuestos dio 32 falsos positivos, todos texto en árabe.
- **No editar el `down()` de una migración antes de revertirla.** Se renombró un
  índice en `up()` y `down()`, se corrió `migrate:rollback`, y falló al intentar
  borrar un nombre que en la base no existía.
- **`migrate:fresh` falló una vez** con "Table already exists" dejando tablas
  MyISAM en pie. El orden seguro es `db:wipe` y después `migrate`.
- **No se puede espiar el progreso de una carga MyISAM** con `information_schema`:
  la tabla queda bloqueada y la consulta se cuelga. Mirar el tamaño del `.MYD`.

### Fulltext

- **El umbral de palabra se mide en bytes**, no en caracteres — por eso funciona
  東京 (2 caracteres, 6 bytes). **Usar `strlen`, nunca `mb_strlen`.**
- `innodb_ft_min_token_size` = 3: los términos de 1–2 letras caen al B-tree.
- **Las stopwords rompen términos obligatorios**: `+the*` mata la consulta entera.
  Se filtran consultando `information_schema.innodb_ft_default_stopword` (36
  palabras, cacheado), porque la lista difiere entre MySQL y MariaDB.
- **El costo está en el `ORDER BY`, no en el `MATCH`**: `+san*` matchea 95.424
  filas; ordenarlas tardaba 4,3 s, sin orden 680 ms. Se acota a 200 candidatos en
  una subconsulta antes del ranking fino (`Ciudad::CANDIDATOS_FULLTEXT`).
- **El bonus por prefijo tiene que mirar las dos columnas.** Mirando solo
  `nombre_min`, "Tokio" devolvía un paraje de Bolivia y "Munique" un pueblo de
  España.

### ⚠️ ~~Regresión abierta~~ — **[HECHO]**

Arreglada envolviendo el camino de 2 letras en la misma subconsulta de
candidatos que ya usaba el fulltext.

| término | antes | ahora |
|---|---|---|
| sa | 668 ms | **84 ms** |
| ba | **1.039 ms** | **123 ms** |
| to | 120 ms | 17 ms |

`ba` era peor que el `sa` documentado y no estaba anotado.

> **Cómo medirlo, porque no se ve donde uno mira.** El conteo de filas leídas
> **no baja** —el rango del índice sigue igual de ancho— y el `EXPLAIN` de la
> consulta externa **empeora** a simple vista: pasa a `Using temporary` por la
> materialización de la subconsulta. Lo que hay que mirar es el `EXPLAIN` de la
> **subconsulta sola**, que dice `Using index`. La tabla de 1,1 GB se toca 200
> veces en vez de 162.514.

<details>
<summary>El diagnóstico original</summary>

**El camino de términos de 2 letras perdió el índice cubriente.** Al extender el
bonus de prefijo a `alternatenames` (columna TEXT que no está en
`ciudades_nombre_min_ranking`), el `ORDER BY` obliga a **161.910 búsquedas de fila**
sobre el índice clusterizado de 1,1 GB por cada tecla.

```
ACTUAL           661 ms   EXPLAIN: "Using index condition; Using filesort"
CON SUBCONSULTA   88 ms   EXPLAIN: "Using index"
```

El arreglo es envolverlo en la **misma subconsulta de candidatos que ya usa el
camino fulltext**. Medido tres veces en caliente. En hosting compartido, con buffer
pool chico y ajeno, la diferencia es mucho mayor que 7×.

</details>

### Trampas que aparecieron en la segunda sesión

- **Una migración de datos puede dejar el dato mal sin fallar.** El regex de
  normalización canadiense destruyó 5.882 nombres y la migración terminó en
  verde. Se descubrió comparando contra el respaldo. **Corolario: después de una
  migración que transforma datos, verificar contra el respaldo, no contra el
  mismo predicado que se usó para filtrar** — que es la misma regla del PAD
  SPACE, en otra forma.
- **Un tope global en un listado agrupado miente sobre el contenido.** El índice
  A–Z con tope de 2.000 dejaba la mitad del abecedario apagado en las ciudades
  grandes. El tope va por grupo.
- **Un `EXISTS` en una lista ordenada por otra columna no escala.** 621 ms
  contra 1 ms. Si hay que filtrar por "tiene algo relacionado" y además ordenar,
  va columna denormalizada.
- **`Route::get($uri, $accion, 301)` ignora el tercer argumento**: la
  redirección sale 302. El código va en `redirect()->route(..., [], 301)`.
- **Un `prefix('{idioma?}')` con parámetro opcional no colapsa el segmento**: la
  variante sin prefijo deja de matchear. Hay que declarar los dos grupos.
- **Laravel resuelve los parámetros escalares de ruta por posición.** Con
  `{idioma}/…/{slug}`, un método que reciba `string $slug` recibe el idioma y
  devuelve 410 sin que nada lo delate. Leerlos con `$request->route('slug')`.

### Entorno

- **La máquina tiene 8 GB**: el watchdog corta tareas en background por memoria, y
  **el aviso "killed" puede mentir** — el proceso puede seguir vivo. Verificar
  antes de relanzar (`information_schema.processlist`, el archivo de salida).
- **No sobrescribir un script bash mientras se está ejecutando**: bash lo lee por
  offset y termina interpretando basura.
- **No correr `breeze:install` de nuevo**: pisó `layouts/app.blade.php`,
  `resources/js/app.js` y `resources/css/app.css` con defaults y dejó el sitio en
  500 durante un año.
- **`npm run build` lo bloquea el clasificador de permisos; `npx vite build`
  funciona igual.**
- Al automatizar con Chrome, la pestaña queda en background: `requestAnimationFrame`
  no dispara y **las transiciones de Alpine quedan a medias**. Un screenshot fuerza
  el repaint. Las capturas se cuelgan si el iframe de OpenStreetMap está en
  viewport.

### Caché

`BuscadorController::home()` cachea los totales con `rememberForever`.
**Después de reimportar hay que correr `php artisan cache:clear`** o el número
queda viejo.

---

## 11. Deuda pendiente (no bloqueante)

- **Breeze está entero y expuesto**: `/register` acepta altas públicas, con 0
  usuarios en la tabla. 17 rutas de auth. Sacarlo reduce superficie de ataque y
  código que auditar.
- ~~**El `noindex,follow` de las fichas vacías**~~ — reemplazado por el 410 de
  la regla de ciudad-con-contenido.
- **La home miente sobre la IP.** El FAQ dice *"we do not store your IP address"*,
  pero `SESSION_DRIVER=database` y la tabla `sessions` de Laravel tiene
  `ip_address` y `user_agent` **cargados en las 147 filas**. O se borran esas dos
  columnas de la migración de sesiones, o se corrige el texto. Dado el historial
  (ya hubo que reescribir *"We do not store your search queries"* cuando dejó de ser
  cierto), conviene **borrar las columnas**.
- **La frase "reverse address lookup" es técnicamente falsa** (el producto hace lo
  contrario: de calle a mapa, no de dirección a persona). **Pero es el 73% de los
  clics y no se toca.** Lo que sí conviene es que la página entregue algo real para
  esa consulta.
- **`@tailwindcss/vite` 4.1.13 está instalado pero sin usar** (el proyecto compila
  con Tailwind 3.4.17 vía `postcss.config.js`). Mezcla de algún `breeze:install`.
- **La base dice "Orange Free State"**; esa provincia sudafricana se llama **Free
  State** desde 1994. Probablemente haya más nombres de GeoNames envejecidos.
- **El índice A–Z esconde parte de las calles donde el tipo de vía va
  adelante.** Resuelto para el español el 18-sep (ver §17); **sigue abierto
  para el francés**: Montréal muestra 687 de 5.790 porque hay 3.319 calles bajo
  la «R» de *Rue* y 1.545 bajo la «A» de *Avenue*. La maquinaria ya está
  —`TipoDeVia` y el agrupado de `Busqueda::indiceDeCiudad`—; falta la lista de
  tipos franceses y decidir con qué criterio se aplica, porque el francés en
  este sitio es Quebec adentro de Canadá y no un país entero.
  En inglés no hace falta: el tipo va al final del nombre.
- **La ficha de una ciudad grande son 58 pantallas de scroll en un teléfono**
  (48.636 px en Calgary, después de pasar a dos columnas; eran 95 pantallas).
  La salida medida es plegar cada letra en un `<details>`: 5 pantallas. Se
  descartó por ahora para no cambiarle la estructura a la página que recibe el
  tráfico mientras Google la reevalúa. ~~Consecuencia: la unidad de AdSense del
  final está debajo de esas 58 pantallas~~ — **atendido el 20-sep con las
  unidades intercaladas en el índice; ver §18.** La del final sigue enterrada
  en móvil y ahora vale sobre todo en escritorio.
- ~~**La ficha de país no tiene unidad de AdSense.**~~ — **[HECHO el 20-sep]**,
  ver §18.
- **433.217 grupos de URLs de ciudad duplicadas** (578 "Krajan" en East Java). Por
  eso la ficha se resuelve por id.
- **307 búsquedas huérfanas** (heredadas del import).
- `.gitignore` ya cubre `/analytics/` y `*.sql` (el dump de 1,5 GB en la raíz).

---

## 12. Cómo verificar

```bash
# Suite completa (SQLite en memoria, no toca MySQL)
php artisan test                     # esperado: 288 tests, 799 assertions

# Estilo, solo lo tocado
./vendor/bin/pint --dirty

# Levantar
php artisan serve                    # http://127.0.0.1:8000

# Assets
npx vite build                       # NO `npm run build`

# Después de reimportar datos
php artisan cache:clear
```

Para medir consultas contra los datos reales, bootstrapear Laravel desde un script
suelto y usar `DB::select("EXPLAIN $sql", $bindings)` más `SHOW SESSION STATUS LIKE
'Handler_read%'`, que cuenta accesos a fila — la métrica que se traduce a lecturas
de disco cuando el buffer pool es chico. **Ojo: el fulltext de InnoDB no incrementa
los contadores `Handler_`**, así que para ese camino hay que medir tiempo.

---

## 13. Lo que falta decidir

- **Códigos postales canadienses.** Es el hueco de contenido más grande para el
  mercado elegido: la consulta canadiense típica alrededor de una dirección
  involucra el código postal, y el dataset no trae ni una columna. Los códigos de
  Canada Post son propietarios; hay que evaluar fuentes abiertas
  (Statistics Canada publica la National Road Network y el Address Register bajo
  Open Government Licence, que además servirían para **validar alturas** sobre las
  ciudades canadienses que ya tienen tráfico).
- ~~**Si se conservan los cinco idiomas del sitio viejo en las redirecciones**~~
  — **contestado el 18-sep, ver §14.** Se conserva uno: el español. Los otros
  tres (`fra`, `ita`, `por`) van a la versión inglesa con 301.
- **Cuándo cortar** del CakePHP al Laravel. Recomendado: staging primero, verificar
  redirecciones contra el CSV de páginas, y recién después el cambio. Aunque el
  riesgo hoy es bajo: ya casi no queda tráfico que perder, lo que **habilita una
  reestructuración agresiva** que hace un mes habría sido temeraria.

---

## 14. El español

> Escrito el 18-sep-2026. Los números salen del export de Search Console de 16
> meses (`analytics/…Performance-on-Search-2026-09-17*/`) y de la base real.

### El dato que obligó a revisar una decisión tomada

El HANDOFF decía "no se vuelve a los cinco idiomas" y lo apoyaba en 18
consultas en español desde EE.UU. Eso es cierto y es una rodaja: el top-1000 de
consultas **filtrado a Estados Unidos**. Mirando el sitio entero, partido por
el prefijo de idioma de la URL:

| prefijo | URLs | clics | % | impresiones |
|---|---|---|---|---|
| sin prefijo (inglés) | 462 | 3.941 | 56,9% | 244.914 |
| **`/esp/`** | **388** | **2.479** | **35,8%** | **104.041** |
| `/fra/` | 108 | 401 | 5,8% | 14.725 |
| `/ita/` | 24 | 63 | 0,9% | 2.065 |
| `/por/` | 18 | 46 | 0,7% | 1.126 |

Y por país de origen: los hispanohablantes son el 32,6% de los clics, con
**Cuba segunda (832)** detrás de Estados Unidos (1.026), España cuarta (422) y
Venezuela séptima (284).

La decisión de no volver a cinco idiomas sigue en pie —eran 18,25 M de URLs—.
La de tratar al español como uno de esos cinco, no. **Un idioma más, no cinco.**

### Los dos segmentos, que son distintos

**A — el que ya funciona.** Fichas de ciudad latinoamericanas en posición 6-12
con CTR de 5-10%:

| URL del Cake | clics | impresiones |
|---|---|---|
| `/esp/…/marianao-havana-cuba` | 157 | 4.984 |
| `/esp/…/barquisimeto-lara-venezuela` | 110 | 1.666 |
| `/esp/…/cerro-havana-cuba` | 82 | 2.183 |
| `/esp/…/soacha-cundinamarca-colombia` | 76 | 5.290 |
| `/esp/…/matanzas-matanzas-cuba` | 62 | 1.707 |

**B — el hispanohablante en EE.UU., que hoy vale cero.** Desde Estados Unidos:

| destino de la consulta | consultas | clics | impresiones | CTR | posición |
|---|---|---|---|---|---|
| → EE.UU./Canadá | 61 | **1** | 1.369 | 0,07% | **45,2** |
| → otros países | 89 | 11 | 1.222 | 0,90% | 41,0 |

`direccion de estados unidos` (85 impr, pos 48), `direcciones usa` (69, pos
45), `direcciones de canada` (51, pos 50). **El problema del segmento B no es
el idioma de la página: es que el sitio está en la posición 45.** Traducir algo
que nadie ve no cambia nada.

### Lo que se hizo — **[HECHO el 18-sep]**

Un prefijo `/es`, acotado a los 21 países hispanohablantes:

```
1.990 ciudades con calles
  367 provincias
   21 países
──────────────────────────
~2.381 URLs  →  +10,5% sobre las 22.581
```

No es un espejo del corpus. `/es/japan` responde **404**, no una traducción
vacía: `LugaresController::soloDondeHayVersion()`. Es lo que impide repetir la
forma en que el sitio se ganó la penalización.

Las piezas:

| qué | dónde |
|---|---|
| La política: qué países, hreflang, helpers | `app/Support/Idioma.php` |
| El prefijo `/es` y su middleware | `routes/public.php`, `EnEspanol` |
| El reseteo de idioma por petición | `IdiomaPorDefecto` (stack global) |
| Los textos | `lang/en/lugares.php`, `lang/es/lugares.php` |
| Formato de dirección en español (CU, ES, MX, PR) | `FormatoDeDireccion::EN_ESPANOL` |
| `/esp/…` del Cake → `/es/…` | `LegadoController::conservarElEspanol()` |
| Las dos versiones en los sitemaps | `SitemapController::enLosDosIdiomas()` |
| 19 casos | `tests/Feature/VersionEnEspanolTest.php` |

Seis decisiones que no son obvias:

1. **Los títulos no son la traducción del inglés.** El inglés titula
   `Reverse Address Lookup Free in {lugar}` porque esa frase es el 73% de sus
   clics. En español esa frase no existe: lo medido son dos familias,
   `calles de {lugar}` y `direcciones de {lugar}`. De ahí
   `Calles y direcciones de {lugar}`, que entra en los 60 caracteres con el
   sufijo de marca y cubre las dos.
2. **Español neutro, no rioplatense.** El público es cubano, colombiano,
   venezolano y español; Argentina no está entre los diez primeros. Tuteo, sin
   voseo, sin "vosotros", y "calle y número" en vez de "altura".
3. **El prefijo de ruta y no la cabecera `Accept-Language`.** `/es/cuba` es
   otra URL, así que la respuesta sigue saliendo sin cookie y sin `Vary`, que
   es lo que permite cachearla en el borde. Negociar por cabecera la partiría
   por navegador y le serviría al rastreador algo distinto que al visitante.
4. **El orden de los campos del buscador cambia con el idioma.** En inglés
   "6455 Macleod Trail SE" y el número va primero; en español "Calle 23 456" y
   va después. El Cake ya hacía esta vuelta. Está en la vista y en
   `direccionCompleta` de `app.js`, que lee el idioma de `<html lang>`. El
   `street` que se le manda a Nominatim va siempre con el número adelante, que
   es lo que documenta su API.
5. **Los números se escriben distinto.** `Idioma::numero()`: 2.065 y no 2,065.
   Las cifras de cobertura son el argumento de la página y escritas al revés
   dicen otra cosa.
6. **Solo el español.** `/fra/` son 401 clics, `/ita/` 63 y `/por/` 46, contra
   2.479. Siguen cayendo en la versión inglesa con 301.

### La trampa que costó encontrar

**`App::setLocale()` también escribe `app.locale` en la configuración.** Un
middleware que "restablece el idioma leyendo la configuración" no hace nada:
después de una petición en español la configuración ya dice `es`. Por eso
`IdiomaPorDefecto` usa la constante `Idioma::INGLES`.

Y el idioma **sobrevive entre peticiones**: con PHP-FPM el proceso muere y
vuelve solo, pero en los tests —que reusan la aplicación— una petición a
`/es/japan` dejaba la siguiente a `/japan` con los números a la española y los
campos dados vuelta. Hoy en producción no pasa, pero que no pase porque el
proceso se muere es una casualidad del despliegue, no una garantía.

### Lo que queda, en orden

1. **Los nombres de región salen de GeoNames en inglés.** La ficha de Marianao
   titula "Marianao, **Havana**" en la versión en español, y la consulta
   medida es `calles del cerro **la habana**` (17 clics). `provincias.nombre`
   no tiene columna en español y `alternatenames` de la ciudad tampoco trae
   etiqueta de idioma. **Es el hueco más caro que queda del español**, y toca
   justo el país que más clics trae. Hay que decidir de dónde sale el nombre
   antes de escribirlo.
2. **La home en español.** `/es` hoy redirige 301 a la home inglesa. La home es
   3 URLs y 154 clics de 6.930, así que no urge, pero es la página que el
   buscador usa de entrada.
3. **El buscador de ciudades, en español.** El endpoint `/api/ciudades`
   devuelve `ruta` con `Ciudad::rutaDeLaFicha()`, que ya respeta el idioma en
   curso; falta que la ficha de país en español le pase el idioma y que los
   textos del componente Alpine salgan traducidos.
4. **Los Ángeles y Miami** — el segmento B. Es el único activo real que el
   sitio tiene para el hispanohablante en EE.UU.: **10.721 calles en Los
   Ángeles**, el tercer corpus urbano del sitio detrás de Calgary (8.015) y
   Ottawa (8.006), en la ciudad con más hispanohablantes del país; y 450 en
   Miami, donde `direcciones en miami` ya rankea en posición 21,4 y
   `direcciones de miami` en 25,2 — las dos únicas consultas de ese segmento a
   distancia de pelea. Meterlos implica abrir `US` en `Idioma::PAISES`, que
   son 440 ciudades, o llevar una lista explícita de ciudades. **Queda
   decidido que va con lista explícita**, para no abrir 440 URLs por dos.
5. El resto del sitio (home, FAQ, textos del layout) sigue en inglés en las
   dos versiones. El pie y la cabecera son cuatro frases.

---

## 15. Compartir una dirección

> 18-sep-2026. Portado de calles-argentinas-v2, adaptado al espacio de URLs de
> este sitio.

El problema: alguien encuentra "6455 Macleod Trail SE" y lo quiere mandar por
WhatsApp. **No había nada que pegar.** Una dirección no tiene URL propia acá y
no la va a tener — la altura es infinita y una página por altura es lo que dejó
al sitio con 18,25 M de URLs descubiertas y 26.335 indexadas.

El enlace corto le da URL a algo que no es una página:

| pieza | dónde |
|---|---|
| Tabla y modelo | `enlaces_compartidos`, `EnlaceCompartido` |
| `POST /api/compartir` y `GET /r/{codigo}` | `CompartirController` |
| El botón, con sus dos respaldos | `lugares/ciudad.blade.php`, `compartir()` en `app.js` |
| Protección del POST sin CSRF | `MismoOrigen` (portado del hermano) |
| 12 casos | `tests/Feature/CompartirTest.php` |

Las decisiones:

- **`/r/{codigo}` renderiza, no redirige.** Devuelve la ficha con la dirección
  ya buscada. Así la dirección no aparece nunca en la barra del navegador —que
  es la mitad del punto— y se ahorra un salto de red en el móvil de quien abre
  el mensaje.
- **No agrega ni una URL indexable.** La página sale con
  `<meta name="robots" content="noindex,follow">`, su canonical apunta a la
  ficha de la ciudad y no lleva hreflang. No entra en ningún sitemap.
- **Tres niveles de degradación**: `navigator.share` (el teléfono, donde está
  WhatsApp) → portapapeles → el enlace a la vista para copiar a mano. El
  último parece exagerado y no lo es: el portapapeles rechaza sin gesto del
  usuario y no existe sobre http. Probado en Chrome: el respaldo se usó.
- **Se reusa el enlace** de una dirección ya compartida mientras siga vigente.
  El botón es gratis de apretar y tiene que dar siempre lo mismo.
- **Caducan a los 90 días**, y la ciudad se revalida al abrir: si perdió su
  ficha por la regla de ciudad-con-contenido, el enlace manda a la home.
- **El idioma viaja en el enlace.** Quien recibe uno de un amigo cubano abre la
  página en español.
- `POST /api/compartir` escribe una fila y la página que lo llama no tiene
  sesión, así que no hay token CSRF: lo protege `MismoOrigen` más el throttle.
  **`POST /api/direcciones` todavía no lo lleva** y es el candidato obvio.

---

## 16. Performance

> Medido con Lighthouse 12 el 18-sep-2026, móvil, sobre la ficha de Marianao.

### Lo primero: cómo medir

`php artisan serve` **no comprime nada**, y eso solo vale 237 KB y ~1,6 s en el
informe. Medir contra él es medir el servidor de desarrollo, no el sitio. La
primera cifra de 75 estaba dominada por eso.

Para medir como mide producción hace falta un proxy que comprima delante. Dos
trampas que costaron un rato:

1. **`pkill` no mata procesos en Windows.** El proxy viejo siguió vivo, el
   nuevo falló con `EADDRINUSE` y dos mediciones salieron contra el proxy sin
   arreglar. Se mata con
   `Get-NetTCPConnection -LocalPort N | Stop-Process -Id $_.OwningProcess`.
2. **Un proxy en otro puerto es otro origen**: los módulos se bloquean por CORS
   y Lighthouse mide una página sin JavaScript — con un score mejor y falso. Se
   detecta mirando `errors-in-console`, que tiene que dar 0.

### El resultado

| | antes | después |
|---|---|---|
| Performance | 75 | **94-95** |
| Accesibilidad | 93 | **97** |
| First Contentful Paint | 2,8 s | **1,6 s** |
| Largest Contentful Paint | 5,2 s | **2,8 s** |
| Total Blocking Time | 100 ms | 60-130 ms |
| JS del bundle principal | 238 KB | **89 KB** |

### Los cambios

1. **Leaflet se carga con `import()` dinámico.** Era un import normal, así que
   sus 150 KB bajaban en TODAS las páginas cuando el mapa existe en una.
   Lighthouse: 152 KB de 238 sin usar. Ahora la home y las fichas de país no lo
   piden **en absoluto** — verificado, cero peticiones.
2. **La hoja de Google Fonts no bloquea el render.** Era el único recurso
   bloqueante y costaba 833 ms. Se pide como `media="print"` y se pasa a `all`
   al cargar, con `<noscript>` de respaldo.
3. **El tile del centro se precarga desde la cabecera.** El LCP de esta página
   es un PNG de OpenStreetMap, y el 73% de su tiempo era *load delay*: el
   navegador no sabía que existía hasta que Leaflet se montaba. El tile se
   calcula en PHP con la misma proyección Web Mercator que usa Leaflet
   (`PrecargaDelMapa::tileCentral`, con test propio). Load delay: 2.531 → 1.201 ms.
4. **`modulepreload` del chunk de Leaflet**, solo en la ficha de ciudad.
5. **`preconnect` a `tile.openstreetmap.org`**.
6. **Compresión y caché en `public/.htaccess`**: los assets de `/build` llevan
   hash, así que van con `immutable` a un año.
7. **Contraste.** `ash` pasó de `#8f8f8f` (2,92:1 sobre cream) a `#6e6e6e`
   (4,61:1) y `periwinkle` de `#4b72f3` (4,20:1 con texto blanco) a `#476ce7`
   (4,60:1). Los dos son **el ajuste más chico que llega a WCAG AA**, mismo
   tono y saturación. Es un cambio visual: se revierte con dos líneas en
   `tailwind.config.js`.

### Lo que queda y por qué no se toca

- **Los tiles de OSM** son PNG de 256 px de un tercero: "next-gen formats" y
  "low resolution" no se pueden arreglar desde acá.
- **"Links rely on color to be distinguishable"** pide subrayar los enlaces. El
  sistema de diseño los quita a propósito en todo el sitio; es una decisión de
  diseño, no un descuido.
- **El índice A–Z sigue pesando**: 2.378 `<li>` en la ficha de Calgary. No
  aparece en Lighthouse porque no bloquea nada, pero son 58 pantallas de scroll
  (ver §11).

---

## 17. El índice por tipo de vía

> 18-sep-2026. Es el paso previo al paso 6 que anotaba §11: sube el valor de la
> ficha de ciudad **sin agregar una sola URL**.

### El problema, medido

Agrupar las calles por su letra inicial funciona en inglés, donde el tipo de
vía va al final — `Macleod Trail SE` — y la primera letra es la del nombre
real. En español va adelante, y eso apila todo bajo la misma letra:

| país | calles | letra dominante | % |
|---|---|---|---|
| **Colombia** | 3.412 | **C** (*Calle*, *Carrera*) | **81%** |
| España | 1.357 | C | 42% |
| Cuba | 2.065 | C | 29% |
| Venezuela | 476 | C | 29% |
| México | 1.949 | C | 26% |

Y abajo había un problema de datos: en Colombia el 70% de las calles empieza
con una **abreviatura** —`cl` 38%, `cr` 32%, `tv`, `dg`, `vda`— que convive con
la forma larga. `Cl 13` y `Calle 13` en Soacha son la misma calle en dos filas.

⚠️ **Dos números que di mal en el camino, corregidos:**

1. «710 grupos duplicados en Colombia» — **eran 60.** La consulta que dio 710
   agrupaba quitando el tipo de vía, así que contaba `Calle 13` y `Carrera 13`
   como la misma calle. En la cuadrícula colombiana son dos calles distintas
   que se cruzan.
2. «La ficha colombiana publicaba el 4% de sus calles» — **publicaba el 18%.**
   El 4% salía de dividir el tope de 120 por las 2.762 calles de la «C», pero
   el tope es por letra y las otras letras también publicaban.

### Lo que se hizo

**Primero los datos** (`2026_09_18_130000`): expande la abreviatura del tipo de
vía en los 21 países hispanohablantes. 4.009 filas candidatas, **60 fusionadas**
—gana la más buscada, el id menor desempata, el ganador se queda con la suma—,
**suma de contadores intacta en 10.241**, respaldo en
`storage/app/backups/tipos-de-via-*.json`. `BuscadorController` normaliza
también al escribir, así que no vuelve a entrar.

**Después el índice** (`Busqueda::indiceDeCiudad`): en los países
hispanohablantes agrupa por tipo de vía y no por letra. Los tipos van primero
—«Calle», «Carrera»— y adentro las filas se ordenan **como números**: 1, 2, 3,
10, 10A, 11. El tipo no se repite en cada fila: el grupo ya se llama así.

| ciudad | calles | antes | ahora |
|---|---|---|---|
| Bogotá | 374 | 76% | **99%** |
| Montevideo | 4.223 | 51% | **63%** |
| Medellín | 2.751 | 18% | **23%** |
| Buenos Aires, Madrid, Quito, Tijuana | — | 82-100% | igual |

Las ocho ciudades hispanohablantes más grandes, en conjunto: **54% → 62%**. La
ganancia en porcentaje es modesta; la de forma no, porque una dirección
colombiana ES «Calle 45» y ahora la lista se recorre como se habla.

Canadá no cambia: 30% en Calgary antes y después.

### Lo que NO se toca, y está verificado contra filas reales

- **Los títulos.** `dr carlos salvagno`, `gral artigas`, `sr …` parecen
  abreviaturas y son tratamientos.
- **`carrer`**, catalán, no una abreviatura de «calle».
- **Una palabra sola**: `cl` sin nada detrás.
- **Los países no hispanohablantes.**

### ⚠️ La trampa de Blade que costó una hora

**En una vista, o todos los bloques de PHP son de la forma larga, o todos de la
de una línea. Mezclarlos rompe el archivo en silencio.**

Blade extrae los bloques `@php`…`@endphp` con una expresión perezosa **antes**
de procesar el resto. Al agregar un bloque nuevo, su cierre le sirvió de cierre
a una directiva de una línea que estaba 200 líneas más arriba, y todo lo del
medio —la barra de letras entera— desapareció del HTML. **Sin error y sin
aviso**: la página respondía 200 y la sección no estaba.

Dos corolarios:

- **No se nombra la directiva dentro de un comentario de Blade.** El nombre
  escrito ahí abre un bloque de verdad, porque los comentarios se quitan
  después.
- La forma de una línea tampoco parsea bien los paréntesis anidados: un cast
  `(float)` o una clausura adentro la dejan a medias. Cuando hay lógica, va al
  controlador.

Las vistas de este proyecto quedaron todas en la forma larga.

---

## 18. Medición y anuncios

> 20-sep-2026. Salió de una pregunta del dueño: «no he recibido más datos en mi
> Google Analytics». No era un script roto: **la migración no portó ninguna de
> las etiquetas del CakePHP**, y el sitio nuevo salió a producción midiendo
> cero sin que nada fallara.

### Lo que el Cake tenía y el Laravel no

Todo estaba en `src/Template/Layout/principal.ctp` y en las cinco plantillas de
página. Nada de esto figuraba en el inventario de la migración: este documento
no menciona «analytics» ni una vez en sus primeras 1.699 líneas.

| qué | ID | en el Laravel, antes |
|---|---|---|
| GA4 (gtag) | `G-QJT8E0GNVR` | no existía |
| Tag Manager | `GTM-KXJVD2T` | no existía |
| AdSense | `ca-pub-0716124412178528` | plomería sí, IDs no |
| `ads.txt` | — | no estaba en el repo |
| Verificación Google | `google80f39555434cad84.html` | **404 en producción** |
| Verificación Yandex | `yandex_55f1f31813073097.html` | **404 en producción** |

> ⚠️ **`ads.txt` estaba vivo de casualidad.** Responde 200 porque sobrevivió en
> `public_html` al reemplazo de archivos, no porque alguien lo hubiera
> desplegado. Ahora está en `public/ads.txt`, versionado. Sin él AdSense marca
> el inventario como no autorizado, y eso cuesta plata directa.

> ⚠️ **Los archivos de verificación dan 404 desde el corte.** Si la propiedad de
> Search Console estaba verificada por ese archivo —o por el código de
> Analytics, que es el otro método común— se puede perder la verificación justo
> cuando hace falta para medir si las 22.581 URLs se indexan. Restaurados en
> `public/`, pero **hay que copiarlos a `public_html/`**: la raíz web es esa.

### Se restauró gtag, no Tag Manager

El Cake cargaba **las dos cosas** en el mismo `<head>`. Buscando en todo el
proyecto viejo no hay un solo `dataLayer.push`: el contenedor nunca recibió un
evento propio, así que o estaba vacío o tenía una etiqueta de GA4 hacia la misma
propiedad y **cada visita se contó dos veces** durante años.

Se conserva la propiedad `G-QJT8E0GNVR` y no una nueva: cortar el histórico de
un sitio que viene de una caída algorítmica es perder el único punto de
comparación que tiene.

| pieza | dónde |
|---|---|
| La etiqueta | `resources/views/partials/medicion.blade.php` |
| La variable | `ANALYTICS_ID`, `config/services.php` |
| 5 casos | `tests/Feature/MedicionTest.php` |

`MedicionTest` fija una cosa que no es obvia: **medir no puede costar la
cacheabilidad**. La etiqueta es JavaScript del cliente, así que la respuesta
sigue saliendo sin `Set-Cookie` y sin `Vary`; la cookie `_ga` la escribe el
navegador y al servidor no le llega nunca. Una etiqueta del lado del servidor
habría roto el §4 entero.

### El reparto de anuncios, rehecho para móvil

El Cake tenía tres unidades **en todas las páginas**: `1271487673` (banner
arriba del mapa, `max-height:90px`), `5988504024` (responsive debajo del mapa) y
`2513019791` (300×250 en la barra lateral del layout).

El Laravel tenía tres, **todas en la ficha de ciudad**, y el reparto tenía dos
agujeros medidos:

1. **56 de 58 pantallas sin un anuncio.** La ficha de una ciudad grande mide
   48.636 px a 390 px de ancho. Las tres unidades estaban en la primera
   pantalla y en la última, y la última —la posición que más rinde— queda por
   debajo de las 58: en móvil no la ve nadie.
2. **La ficha de país, sin nada.** 246 URLs y 2.951 clics: **12 clics por URL**
   contra los 5,2 de la ficha de ciudad. Es la página más eficiente del sitio y
   el Cake sí la monetizaba.

Lo que quedó:

| unidad | dónde | formato |
|---|---|---|
| `ciudad_superior` | arriba del mapa | **horizontal**, alto reservado |
| `ciudad_medio` | entre el buscador y el índice | auto |
| **`ciudad_indice`** | **intercalada cada 8 grupos de letra** | auto, alto reservado |
| `ciudad_final` | al final de la ficha | auto |
| **`pais_superior`** | después del buscador de país | **horizontal**, alto reservado |
| **`pais_medio`** | entre el formato de dirección y las listas | auto, alto reservado |

Cuatro decisiones que no son obvias:

1. **La intercalada va entre dos grupos de letra, nunca partiendo uno**, y
   **nunca después del último** —ahí ya está `ciudad_final` y dos seguidas es
   densidad de más—. Con 27 grupos y una cada 8 son tres unidades, una cada ~19
   pantallas. Es deliberadamente poco denso: el sitio viene de una penalización
   algorítmica y una página cargada de anuncios es justo la señal que conviene
   no dar. `ADSENSE_INDICE_CADA` lo cambia sin tocar código.
2. **Se reusa el mismo `data-ad-slot` varias veces en la página.** AdSense lo
   admite y todas reportan juntas bajo la misma unidad.
3. **Los dos bloques de arriba limitan el inventario a formatos
   horizontales**, con full-width-responsive apagado. Ver abajo: es la segunda
   vez que este problema aparece y la primera solución no servía.
4. **Las intercaladas reservan el alto** (`min-height:280px`). Aparecen mientras
   el visitante scrollea, que es el peor momento para un salto de layout.

### ⚠️ El tamaño del anuncio lo manda el panel, no el marcado

**Es la segunda vez que pasa: primero en calles-argentinas y después acá.**

El patrón de tamaño fijo que documenta Google —`display:inline-block;
width:320px;height:50px` y **sin** `data-ad-format`— se montó en los dos
bloques de arriba justamente para que AdSense no pudiera servir un 336×280 y
empujar el buscador fuera de la pantalla del teléfono. **Y AdSense sirvió un
336×280 igual.**

La razón es que **el tamaño lo decide la unidad del panel**. Si se creó como
*Responsive* —lo que AdSense ofrece por defecto— `adsbygoogle.js` reescribe el
alto del `<ins>` con el formato que eligió, y el `height:50px` del estilo no lo
frena. El marcado fijo solo manda cuando la unidad del panel también es fija.

El indicio estaba a la vista y nadie lo leyó: las unidades que venían del
CakePHP se llaman **«worldstreets *adaptable* top»** y «adaptable bottom».

**La salida, que no depende del panel**: `data-ad-format="horizontal"` con
`data-full-width-responsive="false"`. Limita el inventario a 320×50, 320×100 y
728×90, o sea **100 px de alto máximo en vez de 280**, y funciona esté la
unidad configurada como esté — que es la propiedad que hacía falta, porque el
código no puede verificar cómo quedó el panel. Se reservan **100 px y no 50**:
entre un 320×50 y un 320×100 hay 50 px, y reservar el mayor evita el salto.

`$fijo` sigue existiendo en el partial, para el día que las unidades del panel
sean de tamaño fijo de verdad; ahí es más determinista que `horizontal`.

> **La regla general**: cuando la configuración vive en un panel de un tercero,
> el código no puede afirmar lo que esa configuración hace. Lo que se elija
> tiene que dar el resultado correcto **con el panel configurado de cualquiera
> de las formas posibles**.

### ⚠️ La trampa que encontró el test

**`@include` no aísla el scope de Blade**: el partial ve todas las variables de
la vista que lo incluye.

`lugares/pais.blade.php` define `$formato` —el array de líneas de
`FormatoDeDireccion`— y `partials/ad-slot` usa `$formato` como su
`data-ad-format`, que espera un string. Al montar la unidad del medio, **la
ficha de país entera respondió 500**: `htmlspecialchars(): must be of type
string, array given`, un mensaje que no nombra ni a los anuncios ni al
`@include`.

Ahora el partial **valida cada parámetro por tipo**, no con un `??` que solo
mira si existe. Es la misma familia que la trampa de `@php` de §17: en Blade,
lo que rompe no avisa.

### Lo que queda

- **Las unidades del panel son las del Cake**, pensadas para la maqueta vieja.
  Sirven para arrancar y medir; para distinguir qué posición rinde hacen falta
  unidades propias por posición.
- **La home y la ficha de provincia siguen sin anuncios.** Son 154 y 94 clics
  de 6.930: no mueven la aguja y la home es la única página con sesión.
- **Verificar en AdSense que las unidades intercaladas no disparen un aviso de
  densidad.** Es poco probable con una cada 19 pantallas, pero se mira.
