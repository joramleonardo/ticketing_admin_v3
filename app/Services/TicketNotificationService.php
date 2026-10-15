<?php

namespace App\Services;

use App\Mail\StaffMessageNotificationMail;
use App\Mail\TicketAssignedClientNotificationMail;
use App\Mail\TicketAttendedNotificationMail;
use App\Mail\TicketCompletedMail;
use App\Mail\TicketCreatedClientNotificationMail;
use App\Mail\TicketRatingReminderMail;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketNotificationService
{
    /**
     * Resolve the client email address from client details or ticket_employees.
     */
    public function resolveClientEmail(?TicketClientDetail $client, ?string $ref = null): ?string
    {
        if (! $client && $ref) {
            $client = TicketClientDetail::where('client_referenceNo', $ref)->first();
        }

        if (! $client) {
            return null;
        }

        $email = trim((string) ($client->client_email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        // 1. Fallback by employee ID in ticket_employees
        if (! empty($client->client_empID)) {
            $emp = DB::table('ticket_employees')
                ->where('employee_code', $client->client_empID)
                ->first();

            if (! empty($emp?->email) && filter_var(trim($emp->email), FILTER_VALIDATE_EMAIL)) {
                return trim($emp->email);
            }
        }

        // 2. Fallback by client name in ticket_employees
        if (! empty($client->client_name)) {
            $parts = preg_split('/\s+/', trim($client->client_name));
            $first = $parts[0] ?? '';
            $last = count($parts) > 1 ? end($parts) : '';

            if ($last !== '') {
                $emp = DB::table('ticket_employees')
                    ->where('lname', $last)
                    ->where('fname', 'like', "%{$first}%")
                    ->first();

                if (! empty($emp?->email) && filter_var(trim($emp->email), FILTER_VALIDATE_EMAIL)) {
                    return trim($emp->email);
                }
            }
        }

        return null;
    }

    /**
     * Resolve the client app base URL.
     */
    public function resolveClientBaseUrl(): string
    {
        $clientAppUrl = config('app.client_url');
        if (! $clientAppUrl && request()->getHost()) {
            $host = request()->getHost();
            if (str_contains($host, 'ticketing-admin.')) {
                $clientHost = str_replace('ticketing-admin.', 'ticketing.', $host);
                $scheme = request()->getScheme();
                $port = request()->getPort();
                $portStr = ($port && ! in_array($port, [80, 443])) ? ':'.$port : '';
                $clientAppUrl = "{$scheme}://{$clientHost}{$portStr}";
            }
        }

        $baseUrl = $clientAppUrl ?: (request()->getHost() ? request()->getSchemeAndHttpHost() : config('app.url'));

        return rtrim($baseUrl ?: 'http://ticketing.stii.local', '/');
    }

    public function resolveClientTrackUrl(string $ref): string
    {
        return $this->resolveClientBaseUrl().'/client?track='.rawurlencode($ref).'&open=1';
    }

    public function resolveClientRateUrl(string $ref): string
    {
        return $this->resolveClientBaseUrl().'/client/rate/'.rawurlencode($ref);
    }

    /**
     * Send initial ticket created acknowledgment email (Root of email thread).
     */
    public function sendTicketCreatedNotification(string $refNo): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $trackUrl = $this->resolveClientTrackUrl($refNo);
        $dateRequested = $detail?->date_requested
            ? Carbon::parse($detail->date_requested)->format('M d, Y h:i A')
            : now()->format('M d, Y h:i A');

        try {
            $mailable = new TicketCreatedClientNotificationMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                trackUrl: $trackUrl,
                problem: $detail?->ticket_problem_that_needed_support,
                ticketType: $detail?->ticket_type ?? 'Technical Support',
                dateRequested: $dateRequested,
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('TicketCreatedClientNotificationMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send ticket assigned notification email to client (Thread reply).
     */
    public function sendTicketAssignedClientNotification(string $refNo, string $staffName): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $trackUrl = $this->resolveClientTrackUrl($refNo);

        try {
            $mailable = new TicketAssignedClientNotificationMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                staffName: $staffName,
                trackUrl: $trackUrl,
                problem: $detail?->ticket_problem_that_needed_support,
                ticketType: $detail?->ticket_type,
                status: $detail?->ticket_status ?: 'Pending / Assigned',
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('TicketAssignedClientNotificationMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send ticket attended notification email (Thread reply).
     */
    public function sendTicketAttendedNotification(string $refNo, ?string $staffName = null): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $resolvedStaffName = $staffName ?: ($detail?->ticket_attended_by ?: ($detail?->ticket_assigned_staff ?: (auth()->user()?->name ?? 'IT Support Specialist')));
        $trackUrl = $this->resolveClientTrackUrl($refNo);

        try {
            $mailable = new TicketAttendedNotificationMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                staffName: $resolvedStaffName,
                trackUrl: $trackUrl,
                problem: $detail?->ticket_problem_that_needed_support,
                ticketType: $detail?->ticket_type,
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('TicketAttendedNotificationMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send ticket completed notification email (Thread reply).
     */
    public function sendTicketCompletedNotification(string $refNo, ?string $staffName = null): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $resolvedStaffName = $staffName ?: ($detail?->ticket_attended_by ?: ($detail?->ticket_assigned_staff ?: (auth()->user()?->name ?? 'IT Support Specialist')));
        $rateUrl = $this->resolveClientRateUrl($refNo);
        $trackUrl = $this->resolveClientTrackUrl($refNo);

        $dateCompletedFormatted = $detail?->date_completed
            ? Carbon::parse($detail->date_completed)->format('M d, Y h:i A')
            : Carbon::now()->format('M d, Y h:i A');

        try {
            $mailable = new TicketCompletedMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                staffName: $resolvedStaffName,
                rateUrl: $rateUrl,
                trackUrl: $trackUrl,
                problem: $detail?->ticket_problem_that_needed_support,
                actionsTaken: $detail?->ticket_actions_taken,
                ticketType: $detail?->ticket_type,
                dateCompleted: $dateCompletedFormatted,
                employeeCode: $client?->client_empID,
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('TicketCompletedMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send staff message notification email (Thread reply).
     */
    public function sendTicketMessageNotification(string $refNo, string $staffName, string $messageText): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $trackUrl = $this->resolveClientTrackUrl($refNo);

        try {
            $mailable = new StaffMessageNotificationMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                staffName: $staffName,
                messageText: $messageText,
                trackUrl: $trackUrl,
                problem: $detail?->ticket_problem_that_needed_support,
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('StaffMessageNotificationMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send rating reminder notification email (Thread reply).
     */
    public function sendRatingReminderNotification(string $refNo): bool
    {
        $client = TicketClientDetail::where('client_referenceNo', $refNo)->first();
        $email = $this->resolveClientEmail($client, $refNo);

        if (! $email) {
            return false;
        }

        $detail = TicketDetail::where('client_referenceNo', $refNo)->first();
        $rateUrl = $this->resolveClientRateUrl($refNo);
        $staffName = $detail?->ticket_attended_by ?: ($detail?->ticket_assigned_staff ?: null);

        try {
            $mailable = new TicketRatingReminderMail(
                refNo: $refNo,
                clientName: $client?->client_name ?? 'Client',
                rateUrl: $rateUrl,
                staffName: $staffName,
                problem: $detail?->ticket_problem_that_needed_support,
                ticketType: $detail?->ticket_type,
            );

            $this->dispatchMail($email, $mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('TicketRatingReminderMail failed', [
                'ref' => $refNo,
                'to' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Dispatch mail via send (sync) or queue.
     */
    protected function dispatchMail(string $to, $mailable): void
    {
        if (config('queue.default') === 'sync') {
            Mail::to($to)->send($mailable);
        } else {
            Mail::to($to)->queue($mailable);
        }
    }
}
