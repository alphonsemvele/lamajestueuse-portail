@component('mail::message')
# Bonjour {{ $nom }},

Votre demande d'inscription au portail La Majestueuse n'a pas été retenue.

@if ($motif)
Motif indiqué : *{{ $motif }}*
@endif

S'il s'agit d'une erreur, rapprochez-vous du service des ressources humaines de
votre institut : c'est lui qui pourra faire ouvrir votre compte.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
