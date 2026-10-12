@component('mail::message')
# Ticket Completed

Hi {{ $clientName }},

Your support ticket has been **completed**.

**Reference No:** `{{ $refNo }}`

**Problem:** `{{ $problem }}`

@php($ratingUrl = 'http://ticketing.stii.local/client/rate/' . rawurlencode($refNo))
@component('mail::button', ['url' => $ratingUrl])
Rate this Ticket
@endcomponent

If you have follow-up concerns, just reply to this email.

Thanks, IT Unit
@endcomponent
