<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_centre_daide_souvre_sans_compte(): void
    {
        $this->get(route('support'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('support')
                ->has('rubriques')
                ->has('contact.email')
                ->where('rubriques.0.cle', 'creer-compte'));
    }

    public function test_le_centre_daide_reste_accessible_une_fois_connecte(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('support'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('support'));
    }

    public function test_le_tutoriel_dinscription_detaille_ses_etapes_avec_des_captures(): void
    {
        $this->get(route('support'))->assertInertia(function ($page) {
            $etapes = collect($page->toArray()['props']['rubriques'][0]['etapes']);

            $this->assertGreaterThanOrEqual(5, $etapes->count());
            $this->assertNotEmpty($etapes->first()['titre']);
            // Les captures livrées avec le projet doivent bien être servies.
            $this->assertNotNull($etapes->first()['capture']);
        });
    }

    public function test_une_capture_annoncee_mais_absente_nest_pas_transmise(): void
    {
        config(['support.rubriques' => [[
            'cle' => 'essai',
            'question' => 'Question d’essai',
            'etapes' => [['titre' => 'Étape', 'texte' => 'Texte', 'capture' => 'images/support/inexistante.jpg']],
        ]]]);

        $this->get(route('support'))->assertInertia(
            fn ($page) => $page->where('rubriques.0.etapes.0.capture', null)
        );
    }
}
