@component('mail::message')
# Bonjour {{ $nom }},

Votre demande de badge **{{ $numero }}** est enregistrée.

Le nom qui figurera sur le badge est **{{ $nomAffiche }}**@if ($institut), avec le logo de {{ $institut }}@endif.
Si ce n'est pas ce que vous vouliez, annulez la demande depuis le portail tant
qu'elle n'est pas traitée, et refaites-en une.

Vous serez prévenu dès que le badge sera prêt à être retiré.

@component('mail::button', ['url' => url('/badges')])
Voir ma demande
@endcomponent

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
