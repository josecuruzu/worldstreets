<?php

namespace Database\Seeders;

use App\Models\Continente;
use Illuminate\Database\Seeder;

/*
 * Datos de referencia. Los códigos de dos letras y los geonameid son los
 * oficiales de GeoNames (mismo criterio que el resto del esquema, que está
 * calcado de sus dumps). Los centros y zooms son aproximaciones pensadas
 * para encuadrar el continente en un mapa, no coordenadas de un dataset.
 */
class ContinentesSeeder extends Seeder
{
    public function run(): void
    {
        $continentes = [
            ['id' => 'AF', 'nombre' => 'África',            'geonameid' => 6255146, 'url' => 'africa',            'centro_x' => 1.65000, 'centro_y' => 17.60000, 'zoom' => 3, 'descripcion' => 'Segundo continente más extenso y más poblado del planeta, con 54 países soberanos.'],
            ['id' => 'AS', 'nombre' => 'Asia',              'geonameid' => 6255147, 'url' => 'asia',              'centro_x' => 29.84000, 'centro_y' => 89.30000, 'zoom' => 3, 'descripcion' => 'El continente más extenso y más poblado, ocupa la porción oriental de Eurasia.'],
            ['id' => 'EU', 'nombre' => 'Europa',            'geonameid' => 6255148, 'url' => 'europa',            'centro_x' => 48.69000, 'centro_y' => 9.14000, 'zoom' => 4, 'descripcion' => 'Continente que ocupa la porción occidental de Eurasia, densamente urbanizado.'],
            ['id' => 'NA', 'nombre' => 'América del Norte', 'geonameid' => 6255149, 'url' => 'america-del-norte', 'centro_x' => 46.07000, 'centro_y' => -100.55000, 'zoom' => 3, 'descripcion' => 'Continente que se extiende desde el Ártico hasta el istmo de Panamá.'],
            ['id' => 'SA', 'nombre' => 'América del Sur',   'geonameid' => 6255150, 'url' => 'america-del-sur',   'centro_x' => -14.60000, 'centro_y' => -57.66000, 'zoom' => 3, 'descripcion' => 'Continente que se extiende desde el Caribe hasta el cabo de Hornos.'],
            ['id' => 'OC', 'nombre' => 'Oceanía',           'geonameid' => 6255151, 'url' => 'oceania',           'centro_x' => -18.31000, 'centro_y' => 138.51000, 'zoom' => 3, 'descripcion' => 'Región insular del Pacífico integrada por Australasia, Melanesia, Micronesia y Polinesia.'],
            ['id' => 'AN', 'nombre' => 'Antártida',         'geonameid' => 6255152, 'url' => 'antartida',         'centro_x' => -78.00000, 'centro_y' => 20.00000, 'zoom' => 2, 'descripcion' => 'Continente que rodea el polo sur, sin población permanente.'],
        ];

        foreach ($continentes as $continente) {
            Continente::updateOrCreate(['id' => $continente['id']], $continente);
        }
    }
}
