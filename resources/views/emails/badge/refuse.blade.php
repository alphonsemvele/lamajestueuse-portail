@component('mail::message')
# Bonjour {{ $nom }},

Votre demande de badge **{{ $numero }}** n'a pas pu être traitée.

@if ($motif)
Motif indiqué : *{{ $motif }}*
@endif

Vous pouvez refaire une demande depuis le portail en corrigeant ce point.

@component('mail::button', ['url' => url('/badges')])
Refaire une demande
@endcomponent

Bien à vous,<br>
{{ config('app.name') }}
@endcomponent
