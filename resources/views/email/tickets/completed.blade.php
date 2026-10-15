@component('mail::message')
# Your Support Request Has Been Completed

Hi **{{ $clientName }}**,

Good news! Your support ticket has been marked as **Completed** by **{{ $staffName }}** from the DOST-STII IT Unit.

@component('mail::panel')
**TICKET SUMMARY**

**Reference Number:** {{ $refNo }}

**Status:** Completed

@if (!empty($ticketType))
**Request Type:** {{ $ticketType }}
@endif

**Attended By:** {{ $staffName }}

@if (!empty($dateCompleted))
**Date Completed:** {{ $dateCompleted }}
@endif
@endcomponent

@if (!empty($actionsTaken))
## Actions Taken / Resolution

{{ $actionsTaken }}

---
@endif

@if (!empty($problem))
## Concern Reported

{{ $problem }}

---
@endif

## How Was Our Service?

Your feedback helps us continuously improve our IT support services. Please take a moment to rate the service you received for this ticket.

@component('mail::button', ['url' => $rateUrl, 'color' => 'green'])
Rate This Ticket
@endcomponent

@if (!empty($trackUrl))
You can also view the complete activity logs and ticket history anytime on the [Ticket Tracking Portal]({{ $trackUrl }}).
@endif

Thank you for allowing us to serve you.

Best regards,  
**DOST-STII IT Support Desk**

@component('mail::subcopy')
If you're having trouble clicking the "Rate This Ticket" button, copy and paste the following URL into your web browser:  
[{{ $rateUrl }}]({{ $rateUrl }})
@endcomponent
@endcomponent
