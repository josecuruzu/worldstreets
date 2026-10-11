{{--
    La marca: un mapa plegado con un pin.

    Es la silueta del favicon del sitio viejo —lo único que funcionaba como
    identidad ahí— redibujada en la paleta del sistema. El sitio viene de una
    caída algorítmica y va a cambiar de dominio: conviene que el visitante que
    ya lo conocía lo reconozca.

    Los colores del original (verde, azul y naranja de mapa, pin rojo) no
    existen en pampam.city, así que los paneles van en los grises del sistema
    y el pin en periwinkle, que es el único acento con contraste real sobre el
    crema. Va inline y no como <img> porque hereda `currentColor` en los
    trazos y pesa menos que una petición.
--}}
<svg viewBox="0 0 32 32" fill="none" class="{{ $clase ?? 'h-8 w-8' }}" aria-hidden="true">
    {{-- Los tres paneles del mapa plegado, con los pliegues alternados. --}}
    <path d="M1.5 12.4 11 9.2v18.6l-9.5 3.2z" fill="#e5e5e5"/>
    <path d="M11 9.2 21 12.4V31l-10-3.2z" fill="#ffffff"/>
    <path d="M21 12.4 30.5 9.2v18.6L21 31z" fill="#e5e5e5"/>
    <path d="M1.5 12.4 11 9.2l10 3.2 9.5-3.2v18.6L21 31l-10-3.2-9.5 3.2z"
          stroke="#9894a8" stroke-width="1.1" stroke-linejoin="round"/>
    <path d="M11 9.2v18.6M21 12.4V31" stroke="#9894a8" stroke-width="1.1" stroke-linejoin="round"/>

    {{-- El pin, que es la parte que se reconoce. --}}
    <path d="M16 1.2c-3.5 0-6.4 2.8-6.4 6.3 0 4.6 6.4 11.3 6.4 11.3s6.4-6.7 6.4-11.3c0-3.5-2.9-6.3-6.4-6.3z"
          fill="#4b72f3" stroke="#faf2ec" stroke-width="1.3" stroke-linejoin="round"/>
    <circle cx="16" cy="7.5" r="2.3" fill="#faf2ec"/>
</svg>
