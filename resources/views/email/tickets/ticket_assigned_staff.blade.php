@component('mail::message')
@php
    $ticketCount = count($tickets);
@endphp

@if ($ticketCount === 1)
# New Support Ticket Assigned
@else
# {{ $ticketCount }} Support Tickets Assigned
@endif

Hi **{{ $staffName }}**,

@if ($ticketCount === 1)
You have been assigned a new support ticket by **{{ $assignedBy }}**.
@else
You have been assigned **{{ $ticketCount }} support tickets** by **{{ $assignedBy }}**.
@endif

@foreach ($tickets as $ticket)
@component('mail::panel')
@if ($ticketCount === 1)
**TICKET SUMMARY**
@else
**TICKET {{ $loop->iteration }} OF {{ $ticketCount }}**
@endif

**Reference Number:** {{ $ticket['refNo'] }}

**Client:** {{ $ticket['clientName'] }}

@if (!empty($ticket['division']))
**Division:** {{ $ticket['division'] }}
@endif

**Request Type:** {{ $ticket['ticketType'] ?? 'Technical Support' }}

@if (!empty($ticket['dateRequested']))
**Date Requested:** {{ $ticket['dateRequested'] }}
@endif
@endcomponent

@if (!empty($ticket['problem']))
### Concern / Problem

{{ $ticket['problem'] }}
@endif

@if (!$loop->last)
---
@endif
@endforeach

## Next Steps

Please review the assigned ticket details and begin attending to the client's request. Keep the ticket status and progress notes updated in the workspace.

@component('mail::button', ['url' => $viewUrl, 'color' => 'blue'])
@if ($ticketCount === 1)
Open Ticket in Workspace
@else
View Assigned Tickets
@endif
@endcomponent

Best regards,
**DOST-STII IT Support Desk Management**

@component('mail::subcopy')
If the button does not work, copy and paste the following URL into your browser:
[{{ $viewUrl }}]({{ $viewUrl }})
@endcomponent
@endcomponent
