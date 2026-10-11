{{-- El campo del nombre de la calle. Ver `campo-numero` para por qué están
     partidos en dos archivos. --}}
<label for="calle" class="sr-only">{{ __('lugares.ciudad.calle_label', ['ciudad' => $ciudad->display_name]) }}</label>
<input type="text" id="calle" x-model="calleQuery" x-on:input="buscarCalle()"
       x-on:keydown.enter.prevent="buscar()"
       class="field grow bg-cream"
       placeholder="{{ __('lugares.ciudad.calle_placeholder', ['ciudad' => $ciudad->display_name]) }}"
       autocomplete="off">
