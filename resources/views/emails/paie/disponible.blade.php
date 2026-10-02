@component('mail::message')
# Bonjour {{ $nom }},

Votre bulletin de **{{ $periode }}** est disponible.
{{ $employeur ? 'Il a été établi par '.$employeur.'.' : '' }}

@component('mail::button', ['url' => url('/mes-bulletins')])
Voir mon bulletin
@endcomponent

Vous y retrouverez le détail de votre rémunération, ainsi que vos bulletins
des mois précédents.

Si un montant vous semble inexact, signalez-le au service du personnel avant
la fin du mois.

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
