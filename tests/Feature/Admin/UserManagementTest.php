<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_dun_employe_avec_ses_acces(): void
    {
        $admin = User::factory()->admin()->create();
        $app = Application::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sandrine',
            'lastname' => 'ABENA',
            'email' => 'sandrine.abena@lamajestueuse.cm',
            'matricule' => 'LM-0003',
            'entite' => 'Direction des ressources humaines',
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
            'applications' => [$app->id],
            'roles' => [$app->id => 'admin'],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'sandrine.abena@lamajestueuse.cm')->firstOrFail();

        $this->assertTrue(Hash::check('MotDePasse123', $user->password));
        $this->assertTrue($user->applications->contains($app));
        $this->assertSame('admin', $user->applications->first()->pivot->role_in_app);
    }

    public function test_un_employe_peut_etre_cree_sans_adresse_email(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Joseph',
            'lastname' => 'NKOLO',
            'matricule' => 'LM-0900',
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::where('matricule', 'LM-0900')->firstOrFail()->email);
    }

    public function test_ladresse_email_doit_etre_unique(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'doublon@lamajestueuse.cm']);

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Test', 'email' => 'doublon@lamajestueuse.cm',
                'role' => 'employee', 'status' => 'active', 'locale' => 'fr',
                'password' => 'MotDePasse123', 'password_confirmation' => 'MotDePasse123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_modification_sans_mot_de_passe_conserve_lancien(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $ancien = $user->password;

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'employee',
            'status' => 'suspended',
            'locale' => 'fr',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame($ancien, $user->password);
        $this->assertSame('suspended', $user->status);
    }

    public function test_un_administrateur_ne_peut_pas_supprimer_son_propre_compte(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_suspendre_un_employe_lui_coupe_la_reconnexion(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->create());

        $user->update(['status' => 'suspended']);

        $this->post(route('login'), ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
