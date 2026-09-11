import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Buscador de direcciones de la home.
 *
 * Va en dos pasos porque así están los datos: primero se resuelve la ciudad
 * (tabla ciudades) y después la calle dentro de ella (tabla busquedas, que es
 * el histórico de lo que la gente buscó). Antes esto era un array de
 * direcciones hardcodeadas con un setTimeout simulando la demora.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('addressFinderApp', () => ({
        // Paso 1 — ciudad
        ciudadQuery: '',
        sugerencias: [],
        mostrarSugerencias: false,
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

        cargando: false,
        error: null,

        // Descarta respuestas que llegan fuera de orden: si el usuario sigue
        // tipeando, solo vale la última petición disparada.
        _token: 0,
        _debounce: null,

        get hayCiudad() {
            return this.ciudad !== null;
        },

        get direccionCompleta() {
            if (!this.calleElegida) return '';
            const altura = this.altura.trim();
            const calle = altura ? `${this.calleElegida} ${altura}` : this.calleElegida;
            return `${calle}, ${this.ciudad.nombre}, ${this.ciudad.provincia}, ${this.ciudad.pais}`;
        },

        /** URL del mapa embebido de OSM, encuadrado con el bbox de la ciudad. */
        get mapaUrl() {
            if (!this.ciudad) return '';
            const { lat, lon } = this.ciudad;
            // Sin bbox propio del backend usamos un margen fijo alrededor del
            // centro. El redondeo evita que la resta de floats meta ruido del
            // tipo -33.006820000000005 en la URL.
            const d = 0.06;
            const bbox = [lon - d, lat - d, lon + d, lat + d]
                .map((n) => n.toFixed(5))
                .join(',');
            return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lon}`;
        },

        get osmUrl() {
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
                return;
            }

            this._debounced(async () => {
                const token = ++this._token;
                this.cargando = true;

                try {
                    const datos = await this._json(`/api/ciudades?q=${encodeURIComponent(q)}`, token);
                    if (datos === null) return;

                    this.sugerencias = datos;
                    this.mostrarSugerencias = true;
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
            this.ciudadQuery = `${ciudad.nombre}, ${ciudad.provincia}`;
            this.mostrarSugerencias = false;
            this.sugerencias = [];
            this.calleQuery = '';
            this.calleElegida = null;
            this.altura = '';

            await this.cargarCalles();
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

        elegirCalle(nombre) {
            this.calleElegida = nombre;
            this.calleQuery = nombre;

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
            this.calleQuery = '';
            this.calles = [];
            this.calleElegida = null;
            this.altura = '';
            this.error = null;
        },
    }));
});

Alpine.start();
