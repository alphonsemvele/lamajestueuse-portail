@component('mail::message')
# Bonjour {{ $nom }},

Votre demande de badge **{{ $numero }}** a été validée{{ $institut ? ', avec le logo de '.$institut : '' }}. Elle part à la fabrication.

Vous pouvez dès maintenant voir votre badge, recto et verso, tel qu'il sera imprimé.

@component('mail::button', ['url' => url('/mon-profil?onglet=badge')])
Voir mon badge
@endcomponent

Vous serez prévenu dès qu'il sera prêt à être retiré.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
