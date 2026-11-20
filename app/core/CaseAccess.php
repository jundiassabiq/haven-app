<?php
declare(strict_types=1);

/**
 * Who may open a report.
 *  - survivor: the account owner, or a browser that proved code + PIN (session "case_report_id", one report only)
 *  - companion: only the companion assigned to it
 *  - admin: triage data only (never chronology or contact); handled in AdminCaseController
 */
final class CaseAccess
{
    public static function grant(int $reportId): void
    {
        Session::regenerate();
        Session::put('case_report_id', $reportId);
    }

    public static function revoke(): void
    {
        Session::forget('case_report_id');
    }

    public static function isOwner(array $report, ?array $user = null): bool
    {
        $user ??= Auth::user();
        return $user !== null && $user['role'] === 'survivor' && $report['user_id'] !== null && (int) $report['user_id'] === (int) $user['id'];
    }

    public static function survivorCan(array $report): bool
    {
        return self::isOwner($report) || (int) Session::get('case_report_id', 0) === (int) $report['id'];
    }

    public static function companionCan(array $report, ?array $user = null): bool
    {
        $user ??= Auth::user();
        return $user !== null && $user['role'] === 'companion' && $report['assigned_to'] !== null && (int) $report['assigned_to'] === (int) $user['id'];
    }

    /** 'survivor' | 'companion' | null */
    public static function side(array $report): ?string
    {
        if (self::companionCan($report)) {
            return 'companion';
        }
        return self::survivorCan($report) ? 'survivor' : null;
    }

    /** Loads a report by code for the given side, or throws 404/403 and records the denial. */
    public static function require(string $code, string $side): array
    {
        $code = ReportCode::normalize($code);
        $report = ReportCode::valid($code) ? Report::findByCode($code) : null;
        if (!$report) {
            throw new HttpException(404);
        }
        $ok = $side === 'companion' ? self::companionCan($report) : self::survivorCan($report);
        if (!$ok) {
            Audit::log('access.denied', 'report', $report['code'], $side === 'companion' ? 'Kasus tidak ditugaskan' : 'Tanpa kode/PIN yang valid');
            if ($side === 'survivor' && !Auth::check()) {
                Session::flash('info', 'Masukkan kode laporan dan PIN untuk membuka laporan itu.');
                redirect('/lacak');
            }
            throw new HttpException(403);
        }
        return $report;
    }
}
