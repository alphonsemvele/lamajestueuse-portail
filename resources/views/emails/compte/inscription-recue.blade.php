@component('mail::message')
# Bonjour {{ $nom }},

Votre demande d'inscription au portail La Majestueuse nous est bien parvenue.

@if ($instituts)
Vous avez déclaré travailler pour : **{{ implode(', ', $instituts) }}**.
@endif

Un administrateur va la vérifier. Vous recevrez un message dès que votre compte
sera ouvert — comptez un jour ouvré. D'ici là, il n'y a rien à faire de votre part.

Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : sans
validation, le compte ne s'ouvrira pas.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
