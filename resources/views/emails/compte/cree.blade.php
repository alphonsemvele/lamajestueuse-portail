@component('mail::message')
# Bonjour {{ $nom }},

Un compte a été ouvert à votre nom sur le portail La Majestueuse par le service
du personnel{{ $institut ? ' de '.$institut : '' }}.

Votre identifiant de connexion est **{{ $identifiant }}**.
@if ($matricule)
Votre matricule est **{{ $matricule }}**.
@endif

Votre mot de passe provisoire ne circule pas par courriel : il vous est remis
directement par le service qui a créé votre compte. Changez-le à votre première
connexion.

@component('mail::button', ['url' => url('/connexion')])
Se connecter au portail
@endcomponent

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
