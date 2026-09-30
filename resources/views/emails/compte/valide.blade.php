@component('mail::message')
# Bonjour {{ $nom }},

Votre compte au portail La Majestueuse est ouvert. Vous pouvez vous y connecter
dès maintenant, avec l'adresse que vous avez indiquée à l'inscription.

@if ($matricule)
Votre matricule est **{{ $matricule }}**. Il vous suit dans tout le groupe : gardez-le.
@endif

@component('mail::button', ['url' => url('/connexion')])
Se connecter au portail
@endcomponent

@if ($applications)
Vous y trouverez : {{ implode(', ', $applications) }}.
@endif

Le portail est le point d'entrée unique : une seule connexion vous ouvre les
applications auxquelles vous avez droit, sans mot de passe supplémentaire.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
