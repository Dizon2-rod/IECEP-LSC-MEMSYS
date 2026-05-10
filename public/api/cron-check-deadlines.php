<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';
require_once __DIR__ . '/../../includes/notification-helpers.php';

header('Content-Type: application/json');

$key = $_GET['key'] ?? null;
if (!defined('CRON_SECRET') || empty($key) || $key !== CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$supabase = new \App\Lib\Supabase();
$emailService = new \App\Lib\EmailService();

$summary = [
    'notifications_created' => 0,
    'emails_sent' => 0,
    'pending_affiliation_reminders' => 0,
    'event_deadline_reminders' => 0,
    'compliance_reminders' => 0,
    'errors' => [],
];

function notifyUser(array $user, string $title, string $message, ?string $link, \App\Lib\EmailService $emailService, array &$summary): void
{
    if (empty($user['id'])) {
        return;
    }

    $result = createNotification($user['id'], $title, $message, 'reminder', $link);
    if (!empty($result['success'])) {
        $summary['notifications_created']++;
    } else {
        $summary['errors'][] = 'Notification failed for user ' . ($user['id'] ?? 'unknown') . ': ' . ($result['error'] ?? 'unknown');
    }

    if (!empty($user['email'])) {
        try {
            $sent = $emailService->sendNotification($user['email'], $title, $message);
            if ($sent) {
                $summary['emails_sent']++;
            }
        } catch (\Exception $e) {
            $summary['errors'][] = 'Email send failed for user ' . ($user['id'] ?? 'unknown') . ': ' . $e->getMessage();
        }
    }
}

function notifyUsers(array $users, string $title, string $message, ?string $link, \App\Lib\EmailService $emailService, array &$summary): void
{
    $seen = [];
    foreach ($users as $user) {
        if (empty($user['id']) || in_array($user['id'], $seen, true)) {
            continue;
        }
        $seen[] = $user['id'];
        notifyUser($user, $title, $message, $link, $emailService, $summary);
    }
}

try {
    // 1) Pending affiliations older than 7 days without approval
    $sevenDaysAgo = date('c', strtotime('-7 days'));
    $pendingAffiliations = $supabase->from('pending_affiliations')
        ->select('id,institution_id,submitted_at')
        ->eq('status', 'pending')
        ->lt('submitted_at', $sevenDaysAgo)
        ->get(true);

    if (empty($pendingAffiliations['error']) && !empty($pendingAffiliations['data'])) {
        $reviewersResult = $supabase->from('user_profiles')
            ->select('id,email,role')
            ->in('role', ['committee_registration', 'admin'])
            ->get(true);

        $reviewers = empty($reviewersResult['error']) ? ($reviewersResult['data'] ?? []) : [];

        foreach ($pendingAffiliations['data'] as $affiliation) {
            $institutionName = 'the applicant';
            if (!empty($affiliation['institution_id'])) {
                $institutionResult = $supabase->from('institutions')
                    ->select('name')
                    ->eq('id', $affiliation['institution_id'])
                    ->get(true);

                if (empty($institutionResult['error']) && !empty($institutionResult['data'][0]['name'])) {
                    $institutionName = $institutionResult['data'][0]['name'];
                }
            }

            $title = 'Pending Affiliation Awaiting Review';
            $message = "A pending affiliation application for {$institutionName} has been waiting more than 7 days without approval.";
            notifyUsers($reviewers, $title, $message, '/portal/registration/pending-affiliations.php', $emailService, $summary);
            $summary['pending_affiliation_reminders']++;
        }
    }

    // 2) Upcoming events whose registration deadline is within 3 days
    $now = date('c');
    $inThreeDays = date('c', strtotime('+3 days'));

    $upcomingEvents = $supabase->from('events')
        ->select('id,title,registration_deadline,institution_id')
        ->gte('registration_deadline', $now)
        ->lte('registration_deadline', $inThreeDays)
        ->eq('status', 'upcoming')
        ->get(true);

    if (empty($upcomingEvents['error']) && !empty($upcomingEvents['data'])) {
        foreach ($upcomingEvents['data'] as $event) {
            $deadline = !empty($event['registration_deadline']) ? date('M d, Y g:i A', strtotime($event['registration_deadline'])) : 'soon';
            $title = 'Event Registration Deadline Approaching';
            $message = "Registration for \"{$event['title']}\" closes on {$deadline}. Please register before the deadline.";

            if (empty($event['institution_id'])) {
                $membersResult = $supabase->from('members')
                    ->select('user_id,email')
                    ->get(true);
            } else {
                $membersResult = $supabase->from('members')
                    ->select('user_id,email')
                    ->eq('institution_id', $event['institution_id'])
                    ->get(true);
            }

            $members = empty($membersResult['error']) ? ($membersResult['data'] ?? []) : [];
            foreach ($members as $member) {
                if (empty($member['user_id'])) {
                    continue;
                }
                notifyUser([
                    'id' => $member['user_id'],
                    'email' => $member['email'] ?? null,
                ], $title, $message, '/portal/events.php', $emailService, $summary);
            }

            $summary['event_deadline_reminders']++;
        }
    }

    // 3) Institutions with compliance_status = at_risk or non_compliant
    $complianceInstitutions = $supabase->from('institutions')
        ->select('id,name,compliance_status')
        ->in('compliance_status', ['at_risk', 'non_compliant'])
        ->get(true);

    if (empty($complianceInstitutions['error']) && !empty($complianceInstitutions['data'])) {
        foreach ($complianceInstitutions['data'] as $institution) {
            $statusLabel = str_replace('_', ' ', strtoupper($institution['compliance_status'] ?? 'at_risk'));
            $title = 'Compliance Risk Notice';
            $message = "Institution \"{$institution['name']}\" is marked {$statusLabel}. Please address compliance requirements immediately.";

            $schoolOfficersResult = $supabase->from('user_profiles')
                ->select('id,email,role')
                ->eq('institution_id', $institution['id'])
                ->eq('role', 'school_officer')
                ->get(true);

            $execBoardResult = $supabase->from('user_profiles')
                ->select('id,email,role')
                ->in('role', ['eb_president', 'eb_vp_internal'])
                ->get(true);

            $recipients = [];
            if (empty($schoolOfficersResult['error'])) {
                $recipients = array_merge($recipients, $schoolOfficersResult['data'] ?? []);
            }
            if (empty($execBoardResult['error'])) {
                $recipients = array_merge($recipients, $execBoardResult['data'] ?? []);
            }

            notifyUsers($recipients, $title, $message, '/portal/compliance.php', $emailService, $summary);
            $summary['compliance_reminders']++;
        }
    }

    error_log('[CRON] Deadline reminder executed at ' . date('c') . ' with ' . $summary['notifications_created'] . ' notifications');

    echo json_encode(['success' => true, 'timestamp' => date('c'), 'summary' => $summary]);
    exit;
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'timestamp' => date('c')]);
    exit;
}
