import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Los tokens de pampam.city (design2/), que es el sistema elegido.
 *
 * Van como extensión del tema y no como reemplazo: las utilidades que ya usan
 * las vistas siguen existiendo mientras se migra pantalla por pantalla.
 *
 * Tres cosas que DESIGN.md deja ambiguas y que acá quedan resueltas — están
 * anotadas en el HANDOFF, sección 9:
 *
 * 1. Periwinkle SÍ es el color de la acción primaria, una por pantalla. La
 *    tabla de colores dice lo contrario que la ficha del componente; se sigue
 *    a la ficha del componente.
 * 2. Slate Lavender (#9894a8) NO puede ser color de enlace: da ~2,6:1 sobre el
 *    crema. Se queda con bordes y hairlines, y los enlaces usan Deep Violet
 *    (#2b3ea7), que da ~8,5:1.
 * 3. Ash (#8f8f8f, ~2,9:1) es color de pista —placeholders, textos de ayuda—,
 *    no de dato. Las poblaciones y las cantidades van en Warm Ink.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Superficies
                cream: '#faf2ec',   // el canvas; las tarjetas flotan sobre esto
                paper: '#ffffff',   // tarjetas e inputs
                mist: '#e5e5e5',    // divisores y bordes suaves
                carbon: '#000000',  // el botón oscuro

                // Texto
                ink: '#1b1917',     // el negro de base cálida, nunca #000 para leer
                /*
                 * Ash era #8f8f8f y no pasaba WCAG AA: 2,92:1 sobre cream y
                 * 3,23:1 sobre paper, cuando el mínimo para texto normal es
                 * 4,5:1. Lighthouse lo marcaba en la ficha de ciudad sobre
                 * cuatro elementos, y no es un tecnicismo: Ash es el color de
                 * las cifras de cobertura y de los subtítulos, o sea de datos
                 * que hay que poder leer.
                 *
                 * #6e6e6e es el gris MÁS CLARO que llega a 4,5:1 sobre los dos
                 * fondos (4,61 y 5,10). Se eligió el mínimo a propósito: el
                 * sistema usa Ash para bajar la jerarquía, y oscurecerlo de
                 * más lo convierte en tinta.
                 */
                ash: '#6e6e6e',     // pistas: placeholders y ayudas
                iron: '#222222',    // texto dentro de los campos

                // Cromáticos
                lavender: '#9894a8',   // bordes y hairlines, nunca texto
                /*
                 * Era #4b72f3 y el texto blanco encima daba 4,20:1, por
                 * debajo del 4,5 que pide WCAG AA. Es el color del botón
                 * primario, o sea del único llamado a la acción de cada
                 * pantalla: si hay un texto que tiene que leerse, es ese.
                 *
                 * #476ce7 es el mismo tono y la misma saturación con el valor
                 * un 5% más bajo — el ajuste más chico que llega a 4,60:1. A
                 * ojo no se distingue del anterior.
                 */
                periwinkle: '#476ce7', // la única acción primaria por pantalla
                violet: '#2b3ea7',     // enlaces
            },

            fontFamily: {
                // El sustituto documentado de Nineties, que es una fuente propia.
                display: ['"DM Serif Display"', 'Georgia', '"Times New Roman"', 'serif'],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },

            fontSize: {
                // La escala del sistema con su interlineado: 1.08 para el serif
                // —el ritmo de página impresa— y 1.43–1.56 para el cuerpo.
                cap: ['14px', '1.43'],
                body: ['16px', '1.5'],
                sub: ['19px', '1.56'],
                'head-sm': ['28px', '1.08'],
                head: ['48px', '1.08'],
                display: ['80px', '1.08'],
            },

            fontWeight: {
                // Inter 550: más pesado que el cuerpo, más liviano que un 600.
                label: '550',
            },

            borderRadius: {
                card: '12px',
                field: '12px',
                pill: '16px',
            },

            boxShadow: {
                // Borde de 1px más una caída suave. Nunca apilar sombras: la
                // metáfora de papel se rompe apenas una tarjeta flota.
                card: 'rgba(0, 0, 0, 0.08) 0px 0px 0px 1px, rgba(0, 0, 0, 0.1) 0px 1px 4px 0px',
            },

            maxWidth: {
                page: '1200px',
            },
        },
    },

    plugins: [forms],
};
