<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutTest extends TestCase
{
    public function test_la_pagina_about_responde_y_cita_las_fuentes(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('GeoNames')
            ->assertSee('OpenStreetMap')
            ->assertDontSee('mailto:');
    }

    public function test_muestra_el_correo_solo_si_esta_configurado(): void
    {
        config(['services.contacto.email' => 'contacto@example.test']);

        $this->get('/about')->assertSee('mailto:contacto@example.test', false);
    }

    public function test_nada_en_el_sitio_afirma_que_las_calles_las_buscaron_los_visitantes(): void
    {
        foreach (['en', 'es'] as $idioma) {
            $texto = strtolower(json_encode(require base_path("lang/$idioma/lugares.php"), JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('looked up here', $texto);
            $this->assertStringNotContainsString('la gente buscó', $texto);
        }
    }
}
