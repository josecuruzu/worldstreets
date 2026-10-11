{{-- El campo del número de la casa.

     Está en un partial suyo por el orden: en inglés la dirección se escribe
     "6455 Macleod Trail SE" y el número va PRIMERO; en español es
     "Calle 23 #456" y va después. El sitio viejo ya hacía esta vuelta según
     el idioma (`Ciudades/plano.ctp`, líneas 86-101). Sacarlos a un partial
     cada uno evita tener el mismo <input> escrito dos veces en la vista, que
     es como se desincronizan. --}}
<label for="altura" class="sr-only">{{ __('lugares.ciudad.altura_label') }}</label>
<input type="text" id="altura" x-model="altura"
       x-on:keydown.enter.prevent="buscar()"
       class="field bg-cream sm:w-28"
       placeholder="{{ __('lugares.ciudad.altura_placeholder') }}" inputmode="numeric" autocomplete="off">
