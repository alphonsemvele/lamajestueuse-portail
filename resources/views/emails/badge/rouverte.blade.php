@component('mail::message')
# Bonjour {{ $nom }},

Votre demande de badge **{{ $numero }}** a été rouverte : le refus qui vous avait été annoncé est levé, et elle attend de nouveau d'être traitée.

Vous n'avez rien à refaire — si vous aviez déjà redéposé une demande, celle-ci reste la seule à suivre.

@component('mail::button', ['url' => url('/mon-profil?onglet=badge')])
Voir ma demande
@endcomponent

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
