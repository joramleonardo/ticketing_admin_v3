@component('mail::message')
# New Message from IT Support

Hi **{{ $clientName }}**,

**{{ $staffName }}** from the IT Support team has posted a new message regarding your support ticket.

**Ticket Reference:** `{{ $refNo }}`  
@if (!empty($problem))
**Concern:** {{ $problem }}
@endif

---

### Message from {{ $staffName }}:
> {!! nl2br(e($messageText)) !!}

---

To reply or view the complete conversation history with the IT Support team, click the button below:

@component('mail::button', ['url' => $trackUrl, 'color' => 'blue'])
View Conversation & Reply
@endcomponent

*If the button above does not work, copy and paste this link into your browser:*  
[{{ $trackUrl }}]({{ $trackUrl }})

Best regards,  
**DOST-STII IT Support Desk**
@endcomponent

