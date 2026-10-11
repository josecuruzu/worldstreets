<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\EnlaceCompartido;
use App\Support\Idioma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Compartir una dirección encontrada.
 *
 * El problema que resuelve: alguien busca "Calle 23 456" en Marianao, lo
 * encuentra, y lo quiere mandar por WhatsApp. No hay nada que pegar. Una
 * dirección no tiene URL propia en este sitio y **no la va a tener**: la
 * altura es infinita y una página por altura es exactamente lo que dejó al
 * sitio con 18,25 M de URLs descubiertas y 26.335 indexadas.
 *
 * El enlace corto le da URL a algo que no es una página. `/r/aB3xK9pQ2m` es
 * opaca, no entra en ningún sitemap, sale con `noindex` y su canonical apunta
 * a la ficha de la ciudad. El corpus indexable no crece ni en una URL.
 *
 * ── Por qué renderiza en vez de redirigir ────────────────────────────────
 *
 * `/r/{codigo}` devuelve la ficha de la ciudad con la dirección ya cargada, no
 * un 302 hacia ella. Dos razones: la dirección nunca aparece en la barra del
 * navegador —que es la mitad del punto de un enlace corto— y se ahorra un
 * salto de red en el móvil de quien abre el mensaje.
 */
class CompartirController extends Controller
{
    /**
     * POST /api/compartir — devuelve la URL corta de una dirección.
     *
     * Valida contra la base igual que el endpoint que geocodifica: un enlace a
     * una ciudad que no existe es una fila basura y una página rota.
     */
    public function crear(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'ciudad_id' => ['required', 'integer', 'exists:ciudades,id'],
            'calle' => ['required', 'string', 'max:255'],
            'altura' => ['required', 'string', 'max:16'],
        ]);

        $enlace = EnlaceCompartido::paraLaDireccion(
            (int) $datos['ciudad_id'],
            trim($datos['calle']),
            trim($datos['altura']),
            Idioma::actual(),
        );

        return response()->json([
            'url' => url('/r/'.$enlace->codigo),
        ]);
    }

    /**
     * GET /r/{codigo} — la ficha de la ciudad con la dirección puesta.
     *
     * Un código vencido o inventado manda a la home en vez de dar 404: quien
     * abre esto viene de un mensaje, no de una búsqueda, y un 404 no le dice
     * nada que pueda hacer.
     */
    public function abrir(string $codigo): View|RedirectResponse
    {
        $enlace = EnlaceCompartido::where('codigo', $codigo)->first();

        if ($enlace === null || $enlace->caducado()) {
            return redirect('/', 302);
        }

        $ciudad = Ciudad::with(['pais', 'provincia'])->find($enlace->ciudad_id);

        // La ciudad pudo perder su ficha entre que se compartió y ahora: la
        // regla de ciudad-con-contenido la saca del sitio si se queda sin
        // calles, y `rutaDeLaFicha()` devuelve null justo en ese caso.
        if ($ciudad === null || $ciudad->rutaDeLaFicha() === null) {
            return redirect('/', 302);
        }

        // El idioma en el que se compartió, no el del que abre: el enlace es
        // de quien lo mandó y la página tiene que verse como la vio él.
        if ($enlace->idioma === Idioma::ESPANOL && Idioma::cubre($ciudad->pais?->id)) {
            App::setLocale(Idioma::ESPANOL);
        }

        EnlaceCompartido::where('id', $enlace->id)->increment('visitas');

        return app(LugaresController::class)->ficha($ciudad, [
            'calle' => $enlace->calle,
            'altura' => $enlace->altura,
        ]);
    }
}
