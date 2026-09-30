@component('mail::message')
# Bonjour {{ $nom }},

Votre badge **{{ $numero }}** est imprimé et vous attend{{ $institut ? " à l'accueil de ".$institut : '' }}.

Présentez-vous avec une pièce d'identité pour le retirer.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
