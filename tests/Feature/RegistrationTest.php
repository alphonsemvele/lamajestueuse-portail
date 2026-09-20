<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'photo' => UploadedFile::fake()->image('portrait.jpg', 600, 600),
            'name' => 'Sandrine',
            'lastname' => 'ABENA',
            'sexe' => 'F',
            'matricule' => 'LM-0500',
            'email' => 'sandrine.abena@lamajestueuse.cm',
            'phone' => '+237 6 99 00 00 05',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
        ], $overrides);
    }

    public function test_les_messages_de_validation_sont_en_francais(): void
    {
        User::factory()->create(['matricule' => 'LM-0001']);

        $reponse = $this->from(route('register'))->post(route('register'), [
            'name' => 'Aïcha', 'lastname' => 'NGONO', 'sexe' => 'F',
            'matricule' => 'LM-0001', 'phone' => '+237 600000000',
            'password' => 'MotDePasse2026!', 'password_confirmation' => 'MotDePasse2026!',
        ]);

        $erreurs = session('errors')->getBag('default');

        // Sans traduction française, Laravel renverrait la clé « validation.unique ».
        $this->assertStringNotContainsString('validation.', $erreurs->first('matricule'));
        $this->assertStringContainsString('matricule', mb_strtolower($erreurs->first('matricule')));
        $this->assertStringNotContainsString('validation.', $erreurs->first('photo'));
        $reponse->assertSessionHasErrors(['matricule', 'photo']);
    }

    public function test_le_formulaire_dinscription_est_public(): void
    {
        $ifpm = Application::factory()->create(['name' => 'IFPM']);
        Application::factory()->quickLink()->create(['name' => 'Webmail']);
        Application::factory()->inactive()->create(['name' => 'Projet archivé']);

        // Seuls les projets metier actifs sont proposes comme instituts.
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->has('instituts', 1)
                ->where('instituts.0.name', 'IFPM'));
    }

    public function test_inscription_avec_plusieurs_instituts_et_un_poste_par_institut(): void
    {
        $ifpm = Application::factory()->create(['name' => 'IFPM']);
        $ndazoa = Application::factory()->create(['name' => 'ERP NDAZOA']);

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id, $ndazoa->id],
            'postes' => [$ifpm->id => 'Enseignante', $ndazoa->id => 'Comptable'],
        ]))->assertRedirect(route('register'))->assertSessionHas('registered');

        $user = User::where('email', 'sandrine.abena@lamajestueuse.cm')->firstOrFail();

        $this->assertSame('F', $user->sexe);
        $this->assertSame('LM-0500', $user->matricule);
        $this->assertTrue($user->self_registered);
        $this->assertTrue(Hash::check('MotDePasse123', $user->password));

        $this->assertCount(2, $user->applications);
        $this->assertSame('Enseignante', $user->applications->firstWhere('id', $ifpm->id)->pivot->poste);
        $this->assertSame('Comptable', $user->applications->firstWhere('id', $ndazoa->id)->pivot->poste);
    }

    public function test_le_compte_cree_est_en_attente_et_ne_peut_pas_se_connecter(): void
    {
        $ifpm = Application::factory()->create();

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]));

        $user = User::firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->approved_at);

        $this->post(route('login'), ['username' => $user->email, 'password' => 'MotDePasse123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_linscription_est_possible_sans_adresse_email(): void
    {
        $ifpm = Application::factory()->create();

        $payload = $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]);
        unset($payload['email']);

        $this->post(route('register'), $payload)->assertSessionHasNoErrors();

        $user = User::where('matricule', 'LM-0500')->firstOrFail();

        $this->assertNull($user->email);

        // Sans adresse, la connexion se fait au matricule.
        $user->approve();
        $this->post(route('login'), ['username' => 'LM-0500', 'password' => 'MotDePasse123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_plusieurs_comptes_peuvent_rester_sans_adresse(): void
    {
        // L'index unique sur email tolere plusieurs NULL : deux employes sans
        // adresse ne doivent pas entrer en conflit.
        $ifpm = Application::factory()->create();

        foreach (['LM-0601', 'LM-0602'] as $matricule) {
            $payload = $this->payload([
                'matricule' => $matricule,
                'instituts' => [$ifpm->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]);
            unset($payload['email']);

            $this->post(route('register'), $payload)->assertSessionHasNoErrors();
        }

        $this->assertSame(2, User::whereNull('email')->count());
    }

    public function test_une_adresse_fournie_reste_valide_et_unique(): void
    {
        $ifpm = Application::factory()->create();
        User::factory()->create(['email' => 'doublon@lamajestueuse.cm']);

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'email' => 'doublon@lamajestueuse.cm',
                'instituts' => [$ifpm->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors('email');

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'email' => 'pas-une-adresse',
                'instituts' => [$ifpm->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors('email');
    }

    public function test_la_photo_de_profil_est_obligatoire(): void
    {
        $ifpm = Application::factory()->create();

        $payload = $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]);
        unset($payload['photo']);

        $this->from(route('register'))
            ->post(route('register'), $payload)
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_photo_est_enregistree_et_servie(): void
    {
        $ifpm = Application::factory()->create();

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]))->assertSessionHasNoErrors();

        $user = User::firstOrFail();

        $this->assertStringStartsWith('utilisateurs/photos/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
        $this->assertStringContainsString('/storage/', $user->avatarUrl());
    }

    public function test_un_fichier_qui_nest_pas_une_image_est_refuse_comme_photo(): void
    {
        $ifpm = Application::factory()->create();

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'photo' => UploadedFile::fake()->create('cv.pdf', 40, 'application/pdf'),
                'instituts' => [$ifpm->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors('photo');
    }

    public function test_linscription_est_possible_sans_choisir_dinstitut(): void
    {
        // L'employe peut ne pas savoir ou il sera affecte : c'est
        // l'administrateur qui attribue les acces a la validation.
        Application::factory()->create();

        $this->post(route('register'), $this->payload())->assertSessionHasNoErrors();

        $user = User::where('matricule', 'LM-0500')->firstOrFail();

        $this->assertCount(0, $user->applications);
        $this->assertNull($user->poste);
        $this->assertNull($user->entite);
        $this->assertSame('pending', $user->status);
    }

    public function test_un_poste_est_exige_pour_chaque_institut_coche(): void
    {
        $ifpm = Application::factory()->create();
        $ndazoa = Application::factory()->create();

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'instituts' => [$ifpm->id, $ndazoa->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors("postes.{$ndazoa->id}");

        $this->assertDatabaseCount('users', 0);
    }

    public function test_un_institut_inactif_ne_peut_pas_etre_choisi(): void
    {
        $archive = Application::factory()->inactive()->create();

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'instituts' => [$archive->id],
                'postes' => [$archive->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors('instituts.0');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_le_matricule_et_lemail_doivent_etre_uniques(): void
    {
        $ifpm = Application::factory()->create();
        User::factory()->create(['email' => 'sandrine.abena@lamajestueuse.cm', 'matricule' => 'LM-0500']);

        $this->from(route('register'))
            ->post(route('register'), $this->payload([
                'instituts' => [$ifpm->id],
                'postes' => [$ifpm->id => 'Enseignante'],
            ]))
            ->assertSessionHasErrors(['email', 'matricule']);
    }

    public function test_un_administrateur_valide_la_demande(): void
    {
        $admin = User::factory()->admin()->create();
        $ifpm = Application::factory()->create();

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]));

        $candidate = User::where('status', 'pending')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.approve', $candidate))
            ->assertRedirect(route('admin.users.index'));

        $candidate->refresh();
        $this->assertSame('active', $candidate->status);
        $this->assertNotNull($candidate->approved_at);

        // Le compte valide peut se connecter (l'administrateur se retire
        // d'abord : la route de connexion est reservee aux visiteurs).
        $this->post(route('logout'));

        $this->post(route('login'), ['username' => $candidate->email, 'password' => 'MotDePasse123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($candidate);
    }

    public function test_un_compte_remis_en_attente_perd_sa_session_immediatement(): void
    {
        // La verification ne doit pas se limiter a la connexion : un compte
        // repasse en attente doit etre ejecte des la requete suivante, sans
        // attendre l'expiration de sa session.
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->create());

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'pending']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_compte_suspendu_perd_sa_session_immediatement(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->create();
        $user->applications()->attach($app);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'suspended']);

        // Y compris pour ouvrir une application a laquelle il avait acces.
        $this->get(route('applications.open', $app))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_administrateur_refuse_la_demande(): void
    {
        $admin = User::factory()->admin()->create();
        $ifpm = Application::factory()->create();

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]));

        $candidate = User::where('status', 'pending')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.reject', $candidate));

        $candidate->refresh();
        $this->assertSame('suspended', $candidate->status);
        $this->assertCount(0, $candidate->applications);
    }

    public function test_un_employe_connecte_ne_voit_pas_le_formulaire(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_modifier_les_acces_ne_supprime_pas_le_poste_declare(): void
    {
        $admin = User::factory()->admin()->create();
        $ifpm = Application::factory()->create();

        $this->post(route('register'), $this->payload([
            'instituts' => [$ifpm->id],
            'postes' => [$ifpm->id => 'Enseignante'],
        ]));

        $candidate = User::where('status', 'pending')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $candidate), [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
            'applications' => [$ifpm->id],
            'roles' => [$ifpm->id => 'enseignant'],
        ]);

        $pivot = $candidate->fresh()->applications->first()->pivot;

        $this->assertSame('enseignant', $pivot->role_in_app);
        $this->assertSame('Enseignante', $pivot->poste);
    }
}
