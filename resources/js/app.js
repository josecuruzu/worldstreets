import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/*
 * ── Leaflet se carga cuando hace falta, no antes ──────────────────────────
 *
 * Era un `import` normal, y eso metía Leaflet y su CSS en el bundle principal:
 * 238 KB de JS que bajaban en TODAS las páginas —la home, las fichas de país,
 * las de provincia— cuando el mapa existe solo en la ficha de ciudad. Medido
 * con Lighthouse sobre la ficha de Marianao: **152 KB de los 238 sin usar**, y
 * ~1,2 s de descarga y parseo que el visitante paga antes de ver nada.
 *
 * Ahora entra por `import()` dinámico la primera vez que hay un mapa que
 * dibujar. Vite lo saca a su propio chunk —JS y CSS— y el bundle principal se
 * queda con Alpine y el buscador, que es lo que la página necesita para
 * responder al primer tecleo.
 *
 * El mapa aparece unas décimas más tarde que antes. Es el intercambio
 * deliberado: el buscador es la función del sitio y está arriba del mapa; lo
 * que no se puede es hacer esperar al 100% de las páginas por algo que usa
 * una.
 */
let L = null;
let cargaDeLeaflet = null;

function cargarLeaflet() {
    if (cargaDeLeaflet) return cargaDeLeaflet;

    cargaDeLeaflet = (async () => {
        /*
         * Los tres PNG del marcador van acá adentro por lo mismo: Leaflet arma
         * la ruta de sus imágenes a partir de la URL del CSS, que con un
         * bundler no coincide con ninguna carpeta real, y sin importarlos el
         * marcador sale roto. Importados, Vite les pone hash y los sirve
         * desde /build — pero solo se piden con el resto del mapa.
         */
        const [leaflet, iconoUrl, iconoRetinaUrl, sombraUrl] = await Promise.all([
            import('leaflet'),
            import('leaflet/dist/images/marker-icon.png'),
            import('leaflet/dist/images/marker-icon-2x.png'),
            import('leaflet/dist/images/marker-shadow.png'),
            import('leaflet/dist/leaflet.css'),
        ]);

        L = leaflet.default ?? leaflet;

        L.Marker.prototype.options.icon = L.icon({
            iconUrl: iconoUrl.default,
            iconRetinaUrl: iconoRetinaUrl.default,
            shadowUrl: sombraUrl.default,
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41],
        });

        return L;
    })();

    return cargaDeLeaflet;
}

/*
 * Y se arranca a bajar apenas corre este script, si la página tiene mapa.
 *
 * Sin esto la cadena para pedir el primer tile es larguísima: HTML → módulo
 * principal → arranca Alpine → se evalúa el x-effect → recién ahí empieza el
 * import() de Leaflet → se crea el mapa → se piden los tiles. Lighthouse lo
 * midió: el LCP de la ficha de ciudad es un tile de OpenStreetMap y el **73%
 * de su tiempo es "load delay"** —3,74 s de los 5,1— o sea el rato que el
 * navegador tarda en enterarse de que ese tile existe.
 *
 * Disparándolo acá, la descarga de Leaflet corre en paralelo con el arranque
 * de Alpine en vez de esperarlo. `cargarLeaflet()` memoriza la promesa, así
 * que cuando `pintarMapa` la vuelve a pedir ya está en camino o resuelta.
 *
 * El `data-mapa` lo pone solo la ficha de ciudad: en las demás páginas esto
 * no dispara nada y el chunk no se baja.
 */
if (document.querySelector('[data-mapa]')) {
    cargarLeaflet();
}

/*
 * El mapa de Leaflet vive acá, fuera del componente de Alpine.
 *
 * Dos razones, las dos aprendidas a golpes:
 *
 * · Un mapa de Leaflet guardado en una propiedad del componente pasa por el
 *   proxy reactivo de Alpine, que no es lo que Leaflet espera de sus propios
 *   objetos internos.
 * · Y sobre todo: los métodos que se llaman desde un `x-on` dentro de un
 *   `x-for` —elegir una ciudad de la lista de sugerencias— corren con `this`
 *   apuntando al scope del for, no al del componente. Escribir el mapa en
 *   `this._mapa` desde ahí lo guardaba en el scope equivocado y el de al lado
 *   seguía en null, así que el mapa no se montaba nunca al elegir una ciudad
 *   del autocompletado. Con una variable de módulo el problema no existe:
 *   hay un mapa por página.
 */
let mapa = null;
let marcador = null;
let nodoDelMapa = null;

/** Zoom de calle. Es el que usa el sitio viejo cuando encuentra la dirección. */
const ZOOM_DIRECCION = 17;

/**
 * Dibuja el mapa: la ciudad, o la dirección encontrada con su marcador.
 *
 * Se llama desde `x-effect`, así que Alpine la vuelve a correr sola cuando
 * cambia la ciudad o el resultado. No hay que acordarse de invocarla.
 *
 * @param {HTMLElement} nodo
 * @param {{lat: number, lon: number, zoom: number, nombre: string}|null} ciudad
 * @param {{lat: number, lon: number, etiqueta: string}|null} resultado
 */
async function pintarMapa(nodo, ciudad, resultado) {
    if (!nodo || !ciudad) return;

    /*
     * `x-effect` evalúa la expresión —y por lo tanto lee `ciudad` y
     * `resultado`— antes de entrar acá, así que Alpine sigue registrando las
     * dependencias bien aunque la función sea asíncrona. Lo que NO se puede es
     * leer estado reactivo después del await: eso ya no se rastrea.
     */
    await cargarLeaflet();

    // Otro nodo: es otra página con otro contenedor.
    if (mapa && nodoDelMapa !== nodo) {
        mapa.remove();
        mapa = null;
        marcador = null;
    }

    if (!mapa) {
        mapa = L.map(nodo, {
            // Con el scroll capturado, bajar la página encima del mapa hace
            // zoom en vez de bajar. En móvil, que es el 76% del tráfico, deja
            // la página trabada.
            scrollWheelZoom: false,
        }).setView([ciudad.lat, ciudad.lon], ciudad.zoom || 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(mapa);

        nodoDelMapa = nodo;
    }

    if (marcador) {
        marcador.remove();
        marcador = null;
    }

    if (resultado) {
        marcador = L.marker([resultado.lat, resultado.lon])
            .addTo(mapa)
            .bindPopup(resultado.etiqueta)
            .openPopup();

        mapa.flyTo([resultado.lat, resultado.lon], ZOOM_DIRECCION, { duration: 0.8 });
    } else {
        mapa.setView([ciudad.lat, ciudad.lon], ciudad.zoom || 13);
    }

    /*
     * En la home el bloque del mapa arranca con display:none y aparece con una
     * transición cuando se elige la ciudad. Un Leaflet montado dentro de un
     * contenedor oculto se cree que mide 0×0 y no pide un solo tile: el
     * contenedor queda en blanco. Los dos avisos cubren el alta y el final de
     * la transición.
     */
    requestAnimationFrame(() => mapa?.invalidateSize());
    setTimeout(() => mapa?.invalidateSize(), 350);
}

/** Saca el marcador sin tocar el encuadre. */
function limpiarMarcador() {
    if (marcador) {
        marcador.remove();
        marcador = null;
    }
}

/**
 * Elegida la ciudad, el foco y la pantalla se van al formulario de la
 * dirección.
 *
 * Sin esto el cursor se quedaba en el campo de la ciudad y el bloque de la
 * calle aparecía más abajo, fuera del viewport: el visitante elegía la ciudad y
 * tenía que darse cuenta solo de que había que bajar y volver a hacer clic.
 *
 * Va por getElementById y no por $refs: los métodos que se llaman desde un
 * x-on dentro de un x-for —elegir una ciudad de la lista de sugerencias— corren
 * con `this` apuntando al scope del for, y ahí $refs no resuelve lo mismo.
 *
 * `preventScroll` porque el scroll lo hace scrollIntoView con animación; sin
 * eso el salto del foco pelea con la animación y la corta.
 */
function enfocarLaDireccion() {
    requestAnimationFrame(() => {
        const primero = document.getElementById('altura');

        if (!primero) return;

        primero.closest('section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        primero.focus({ preventScroll: true });
    });
}

/**
 * Buscador de direcciones de la home.
 *
 * Va en dos pasos porque así están los datos: primero se resuelve la ciudad
 * (tabla ciudades) y después la calle dentro de ella (tabla busquedas, que es
 * el histórico de lo que la gente buscó). Antes esto era un array de
 * direcciones hardcodeadas con un setTimeout simulando la demora.
 */
/**
 * El buscador de la ficha de país: encuentra una ciudad del país y lleva a su
 * página, donde está el buscador de calle + altura.
 *
 * Es un componente aparte y no el de la home porque el trabajo es otro: acá el
 * país ya está resuelto y el resultado es navegar, no buscar en el lugar. La
 * ficha de país es la página más eficiente del sitio —246 URLs se llevaron
 * 2.951 clics— y era la más pasiva: el que llegaba de `reverse address lookup
 * canada` tenía que elegir región, después ciudad, y recién ahí podía buscar.
 *
 * Corre en una página sin sesión, así que no registra nada: solo lee.
 */
function buscadorDePais(pais) {
    return {
        pais,
        query: '',
        ciudades: [],
        mostrar: false,
        sinResultados: false,
        cargando: false,
        error: null,

        _token: 0,
        _debounce: null,

        buscar() {
            const q = this.query.trim();
            this.error = null;

            if (q.length < 2) {
                this.ciudades = [];
                this.mostrar = false;
                this.sinResultados = false;

                return;
            }

            clearTimeout(this._debounce);
            this._debounce = setTimeout(async () => {
                const token = ++this._token;
                this.cargando = true;

                try {
                    const url = `/api/ciudades?q=${encodeURIComponent(q)}&pais=${encodeURIComponent(this.pais)}`;
                    const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });

                    if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);

                    const datos = await respuesta.json();

                    // Llegó tarde: ya hay una petición más nueva en curso.
                    if (token !== this._token) return;

                    this.ciudades = datos;
                    this.mostrar = datos.length > 0;
                    this.sinResultados = datos.length === 0;
                } catch (e) {
                    this.error = 'We could not search right now. Please try again.';
                    this.ciudades = [];
                } finally {
                    if (token === this._token) this.cargando = false;
                }
            }, 250);
        },

        /**
         * Enter: va a la primera ciudad que tenga página.
         *
         * `ruta` llega en null cuando la ciudad no tiene calles —su ficha
         * responde 410—, así que esas no son un destino.
         */
        irALaPrimera() {
            const destino = this.ciudades.find((c) => c.ruta);

            if (destino) {
                window.location.href = destino.ruta;
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('addressFinderApp', (direccionInicial = null) => ({
        // Paso 1 — ciudad
        ciudadQuery: '',
        sugerencias: [],
        mostrarSugerencias: false,
        /*
         * La búsqueda de ciudad terminó y no trajo nada. Es una bandera propia
         * porque antes el cartel se deducía de `!mostrarSugerencias`: cerrar la
         * lista con un clic afuera decía "No cities matched that name" teniendo
         * ocho resultados detrás.
         */
        sinCiudades: false,
        ciudad: null,

        // Paso 2 — calle dentro de la ciudad
        calleQuery: '',
        // Término que produjo la lista que se está mostrando. Se usa para el
        // encabezado en vez de calleQuery, que cambia apenas el usuario tipea
        // y dejaría el título describiendo una lista que todavía no llegó.
        calleQueryAplicada: '',
        calles: [],
        totalCalles: 0,
        calleElegida: null,
        altura: '',

        /*
         * La dirección geocodificada: {lat, lon, etiqueta}. Es el resultado que
         * el sitio existe para dar, y lo que mueve el marcador del mapa.
         */
        resultado: null,
        buscandoDireccion: false,
        sinResultado: false,

        /*
         * El botón de compartir.
         *
         * `enlaceALaVista` es el último recurso: si no hay `navigator.share`
         * NI portapapeles —navegador viejo, o la página servida por http, que
         * es donde la API de portapapeles no existe—, el enlace se muestra
         * para copiarlo a mano. Un botón que no hace nada es peor.
         */
        compartiendo: false,
        enlaceCopiado: false,
        direccionCopiada: false,
        enlaceALaVista: '',

        /*
         * La dirección que viene de un enlace compartido, `/r/{codigo}`. La
         * página la busca sola al montarse: quien abre el mensaje tiene que
         * ver la dirección en el mapa, no un formulario vacío que le pide
         * escribir lo que ya venía en el enlace.
         */
        direccionInicial,

        cargando: false,
        error: null,

        // Descarta respuestas que llegan fuera de orden: si el usuario sigue
        // tipeando, solo vale la última petición disparada.
        _token: 0,
        _debounce: null,

        /*
         * Alpine llama a init() después del x-init que carga `ciudad`, así
         * que acá ya está la ciudad resuelta y se puede geocodificar.
         */
        init() {
            if (!this.direccionInicial) return;

            this.calleQuery = this.direccionInicial.calle;
            this.calleElegida = this.direccionInicial.calle;
            this.altura = this.direccionInicial.altura;

            this.$nextTick(() => this.buscar());
        },

        get hayCiudad() {
            return Boolean(this.ciudad);
        },

        /**
         * La dirección armada, en el orden que le corresponde al idioma.
         *
         * En inglés la altura va ADELANTE — "6455 Macleod Trail SE"—; en
         * español va detrás — "Calle 23 456". El sitio viejo ya hacía esta
         * vuelta: número primero para `eng` y `fra`, calle primero para `esp`,
         * `por` e `ita` (`Ciudades/plano.ctp`, líneas 86-101). La migración se
         * había quedado con un solo orden, y cuando apareció la versión en
         * español volvió a hacer falta.
         *
         * El idioma sale de `<html lang>` y no de una variable propia: es el
         * mismo atributo que ya necesitan Google y los lectores de pantalla,
         * así que no hay dos fuentes que puedan discrepar. Lo pone el layout
         * desde `app()->getLocale()`.
         *
         * Ojo: esto es solo la cadena que se MUESTRA. El `street` que se le
         * manda a Nominatim va siempre con el número adelante, que es lo que
         * documenta su API, y lo arma el servidor.
         */
        get direccionCompleta() {
            if (!this.calleElegida || !this.ciudad) return '';

            const altura = this.altura.trim();
            const enEspanol = document.documentElement.lang.startsWith('es');
            const calle = altura
                ? (enEspanol ? `${this.calleElegida} ${altura}` : `${altura} ${this.calleElegida}`)
                : this.calleElegida;

            return this.ubicacion(calle, this.ciudad.nombre, this.ciudad.provincia, this.ciudad.pais);
        },

        /**
         * Une las partes de una ubicación salteando las que no existen.
         *
         * 56.721 ciudades del dataset global no tienen provincia — su admin1 es
         * el centinela `XX.00` de GeoNames, como Mónaco o Koforidua — y la API
         * devuelve null. Interpolarlo derecho escribía "null" en pantalla, en
         * la dirección que se copia y en la consulta que va a OpenStreetMap.
         */
        ubicacion(...partes) {
            return partes.filter(Boolean).join(', ');
        },

        /*
         * El mapa lo dibuja `pintarMapa`, que vive fuera del componente y se
         * llama desde un x-effect en el contenedor. Acá solo se expone para
         * que la vista pueda invocarla.
         */
        pintarMapa,

        /**
         * Enlace secundario a OSM. El mapa de la página ya muestra la
         * dirección; esto es para quien quiera rutas o la ficha del lugar.
         */
        /**
         * Compartir la dirección encontrada.
         *
         * Pide una URL corta al servidor y la entrega por donde se pueda: la
         * hoja de compartir del sistema en el teléfono —que es donde está
         * WhatsApp, y es el caso real: el 3,4% de CTR del sitio está en
         * móvil—, el portapapeles en el escritorio.
         *
         * Si el servidor no contesta no se aborta: se comparte la URL de la
         * ficha de la ciudad, que al menos lleva al lugar correcto. Un botón
         * que falla en silencio es peor que uno que comparte de menos.
         */
        async compartir() {
            if (!this.resultado || this.compartiendo) return;

            this.compartiendo = true;
            this.enlaceALaVista = '';

            let enlace = window.location.href;

            try {
                const respuesta = await fetch('/api/compartir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({
                        ciudad_id: this.ciudad.id,
                        calle: this.calleElegida,
                        altura: this.altura.trim(),
                    }),
                });

                if (respuesta.ok) {
                    enlace = (await respuesta.json()).url || enlace;
                }
            } catch (e) {
                // Se comparte la URL de la ficha; ver el docblock.
            }

            this.compartiendo = false;

            const texto = this.direccionCompleta;

            if (navigator.share) {
                try {
                    await navigator.share({ title: texto, text: texto, url: enlace });

                    return;
                } catch (e) {
                    // Cancelar la hoja de compartir lanza AbortError. No es un
                    // error: el usuario cambió de idea y no hay nada que decir.
                    if (e && e.name === 'AbortError') return;
                }
            }

            if (await this.alPortapapeles(enlace)) {
                this.avisar('enlaceCopiado');

                return;
            }

            this.enlaceALaVista = enlace;
        },

        /** La dirección en texto, para pegarla en cualquier lado. */
        async copiarDireccion() {
            if (!this.resultado) return;

            if (await this.alPortapapeles(this.direccionCompleta)) {
                this.avisar('direccionCopiada');
            }
        },

        async alPortapapeles(texto) {
            if (!navigator.clipboard) return false;

            try {
                await navigator.clipboard.writeText(texto);

                return true;
            } catch (e) {
                return false;
            }
        },

        /** Confirma en el propio botón y se borra sola. */
        avisar(bandera) {
            this[bandera] = true;
            setTimeout(() => { this[bandera] = false; }, 2500);
        },

        get osmUrl() {
            if (this.resultado) {
                const { lat, lon } = this.resultado;

                return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=18/${lat}/${lon}`;
            }

            if (!this.ciudad) return '';

            const consulta = encodeURIComponent(this.direccionCompleta || this.ciudad.nombre);

            return `https://www.openstreetmap.org/search?query=${consulta}`;
        },

        _debounced(fn) {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(fn, 250);
        },

        async _json(url, token) {
            const respuesta = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!respuesta.ok) {
                throw new Error(`HTTP ${respuesta.status}`);
            }

            const datos = await respuesta.json();

            // Llegó tarde: ya hay una petición más nueva en curso.
            if (token !== this._token) return null;

            return datos;
        },

        buscarCiudad() {
            const q = this.ciudadQuery.trim();
            this.error = null;

            if (q.length < 2) {
                this.sugerencias = [];
                this.mostrarSugerencias = false;
                this.sinCiudades = false;

                return;
            }

            this._debounced(async () => {
                const token = ++this._token;
                this.cargando = true;

                try {
                    const datos = await this._json(`/api/ciudades?q=${encodeURIComponent(q)}`, token);
                    if (datos === null) return;

                    this.sugerencias = datos;
                    this.mostrarSugerencias = datos.length > 0;
                    this.sinCiudades = datos.length === 0;
                } catch (e) {
                    this.error = 'No pudimos buscar la ciudad. Probá de nuevo.';
                    this.sugerencias = [];
                } finally {
                    if (token === this._token) this.cargando = false;
                }
            });
        },

        async elegirCiudad(ciudad) {
            this.ciudad = ciudad;
            this.resultado = null;
            this.sinResultado = false;
            this.ciudadQuery = this.ubicacion(ciudad.nombre, ciudad.provincia);
            this.mostrarSugerencias = false;
            this.sinCiudades = false;
            this.sugerencias = [];
            this.calleQuery = '';
            this.calleElegida = null;
            this.altura = '';

            await this.cargarCalles();

            enfocarLaDireccion();
        },

        async cargarCalles() {
            if (!this.ciudad) return;

            const token = ++this._token;
            const q = this.calleQuery.trim();
            this.cargando = true;
            this.error = null;

            try {
                const url = `/api/ciudades/${this.ciudad.id}/calles${q ? `?q=${encodeURIComponent(q)}` : ''}`;
                const datos = await this._json(url, token);
                if (datos === null) return;

                this.calles = datos.calles;
                this.calleQueryAplicada = q;
                this.totalCalles = datos.ciudad.total_calles;
            } catch (e) {
                this.error = 'No pudimos traer las calles de esta ciudad.';
                this.calles = [];
            } finally {
                if (token === this._token) this.cargando = false;
            }
        },

        buscarCalle() {
            this._debounced(() => this.cargarCalles());
        },

        /**
         * Enter en el campo de la calle o en el de la altura: toma la primera
         * coincidencia.
         *
         * No hay <form> en el bloque de la calle, así que hasta ahora tipear
         * "macleod" + "1234" y apretar Enter no hacía absolutamente nada: la
         * dirección aparecía solo si el visitante adivinaba que tenía que
         * clickear un ítem de la lista.
         *
         * Se corta el debounce y se espera la respuesta antes de elegir: si no,
         * el Enter que llega enseguida del último tecleo elegiría la primera
         * calle de la lista anterior, que es de otro término.
         */
        async elegirPrimera() {
            clearTimeout(this._debounce);

            await this.cargarCalles();

            if (this.calles.length) {
                this.elegirCalle(this.calles[0].nombre);
            }
        },

        /**
         * La función principal del sitio: manda calle + altura al servidor, que
         * la geocodifica contra Nominatim y —si existe— la suma a la lista de
         * calles de la ciudad.
         *
         * El servidor hace las dos cosas en la misma petición a propósito. El
         * sitio viejo dispara el guardado y la geocodificación en paralelo, así
         * que guarda también las que no existen: de ahí salieron las entradas
         * "st", "ave" y "2" que hoy tiene la lista de Calgary.
         */
        async buscarDireccion() {
            const calle = this.calleQuery.trim();
            const altura = this.altura.trim();

            if (!this.ciudad || calle === '' || altura === '') return;

            this.resultado = null;
            this.sinResultado = false;
            this.error = null;
            this.buscandoDireccion = true;

            // El token solo existe donde hay sesión. La ficha de la ciudad
            // corre sin sesión para ser cacheable, y el endpoint está fuera de
            // CSRF justamente por eso.
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            try {
                const respuesta = await fetch('/api/direcciones', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify({
                        ciudad_id: this.ciudad.id,
                        calle,
                        altura,
                    }),
                });

                if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);

                const datos = await respuesta.json();

                if (!datos.encontrada) {
                    this.sinResultado = true;

                    return;
                }

                this.calleElegida = calle;
                this.resultado = {
                    lat: datos.lat,
                    lon: datos.lon,
                    etiqueta: datos.etiqueta,
                };

                // La ciudad puede haber estrenado ficha con este guardado: su
                // página existe desde que alguien encuentra una calle ahí.
                if (datos.ruta && this.ciudad) {
                    this.ciudad.ruta = datos.ruta;
                }

                // Y la calle acaba de entrar en la lista, así que la lista
                // cambió.
                this.cargarCalles();
            } catch (e) {
                this.error = 'No pudimos buscar esa dirección. Probá de nuevo.';
            } finally {
                this.buscandoDireccion = false;
            }
        },

        /**
         * Lo que dispara el botón Search y el Enter en cualquiera de los dos
         * campos.
         *
         * Con altura busca la dirección en el mapa, que es a lo que el
         * visitante vino. Sin altura resuelve solo el nombre de la calle contra
         * la lista, que es lo único que se puede hacer sin altura: el sitio
         * viejo acá tiraba un alert "Complete all fields".
         */
        buscar() {
            if (this.altura.trim() !== '' && this.calleQuery.trim() !== '') {
                return this.buscarDireccion();
            }

            return this.elegirPrimera();
        },

        elegirCalle(nombre) {
            this.calleElegida = nombre;
            this.calleQuery = nombre;
            this.resultado = null;
            this.sinResultado = false;

            /*
             * Con la altura ya tipeada, elegir la calle completa una dirección:
             * se va derecho al mapa, que es a lo que el visitante vino.
             * `buscarDireccion()` guarda la calle por su cuenta —y solo si
             * existe—, así que el registro de acá sobra.
             */
            if (this.altura.trim() !== '') {
                return this.buscarDireccion();
            }

            this._registrar(nombre);

            // Sin esto la lista queda mostrando el top de la ciudad mientras
            // el encabezado ya dice que son las coincidencias del término.
            this.cargarCalles();
        },

        /**
         * Le avisa al backend que se buscó esta calle, para que sume al
         * ranking. Va suelto a propósito: no se espera la respuesta y un fallo
         * no se le muestra al usuario, porque no afecta lo que está viendo.
         *
         * El backend deduplica por sesión, así que repetir la misma calle en
         * la misma visita no cuenta de nuevo.
         */
        _registrar(nombre) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrf || !this.ciudad) return;

            fetch('/api/busquedas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    ciudad_id: this.ciudad.id,
                    nombre_calle: nombre,
                }),
            }).catch(() => {});
        },

        limpiar() {
            this.ciudad = null;
            this.ciudadQuery = '';
            this.sugerencias = [];
            this.mostrarSugerencias = false;
            this.sinCiudades = false;
            this.calleQuery = '';
            this.calles = [];
            this.calleElegida = null;
            this.altura = '';
            this.resultado = null;
            this.sinResultado = false;
            limpiarMarcador();
            this.error = null;
        },
    }));

    Alpine.data('buscadorDePais', buscadorDePais);
});

Alpine.start();
