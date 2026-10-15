@component('mail::message')
# Reminder: Please Rate Your IT Support Experience

Hi **{{ $clientName }}**,

Your recent support ticket was completed by **{{ $staffName ?: 'the DOST-STII IT Unit' }}**. We would greatly appreciate a few moments of your time to share your feedback.

@component('mail::panel')
**TICKET SUMMARY**

**Reference Number:** {{ $refNo }}

**Status:** Completed (Awaiting Feedback)

@if (!empty($ticketType))
**Request Type:** {{ $ticketType }}
@endif

@if (!empty($staffName))
**Attended By:** {{ $staffName }}
@endif
@endcomponent

@if (!empty($problem))
## Concern Reported

{{ $problem }}

---
@endif

## Your Feedback Matters

Your rating directly helps the DOST-STII IT Unit maintain high service quality standards and improve technical support for everyone.

@component('mail::button', ['url' => $rateUrl, 'color' => 'green'])
Rate This Ticket Now
@endcomponent

Thank you for your valuable feedback!

Best regards,
**DOST-STII IT Support Desk**

@component('mail::subcopy')
If you're having trouble clicking the button, copy and paste the following URL into your browser:
[{{ $rateUrl }}]({{ $rateUrl }})
@endcomponent
@endcomponent

