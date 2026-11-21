<?php
declare(strict_types=1);

/** Module 6: JSON API for the secure thread. Only the survivor (owner or code+PIN) and the assigned companion. */
final class MessageController
{
    /** @return array{0:array, 1:string} report and viewer side */
    private static function context(string $code): array
    {
        $code = ReportCode::normalize($code);
        $r = ReportCode::valid($code) ? Report::findByCode($code) : null;
        if (!$r) {
            json_response(['error' => 'Laporan tidak ditemukan.'], 404);
        }
        $side = CaseAccess::side($r);
        if ($side === null) {
            Audit::log('access.denied', 'report', $r['code'], 'Membuka pesan tanpa izin');
            json_response(['error' => 'Kamu tidak punya akses ke percakapan ini.'], 403);
        }
        return [$r, $side];
    }

    private static function dayLabel(string $dt): string
    {
        $d = date('Y-m-d', strtotime($dt));
        return match ($d) {
            date('Y-m-d') => 'HARI INI',
            date('Y-m-d', time() - 86400) => 'KEMARIN',
            default => mb_strtoupper(id_date($dt)),
        };
    }

    public function list(array $p): void
    {
        [$r, $side] = self::context($p['code']);
        $after = max(0, (int) ($_GET['after'] ?? 0));
        $rows = Message::since((int) $r['id'], $after);
        Message::markReadFor((int) $r['id'], $side);

        // Opening the thread also clears the matching "new message" notification for this viewer.
        $uid = $side === 'companion' ? Auth::id() : ($r['user_id'] !== null ? (int) $r['user_id'] : null);
        Database::run('UPDATE notifications SET read_at = ? WHERE report_id = ? AND user_id <=> ? AND type = ? AND read_at IS NULL', [now(), (int) $r['id'], $uid, 'pesan']);

        $out = array_map(static function (array $m) use ($side) {
            $system = $m['sender_role'] === 'system';
            $mine = $m['sender_role'] === $side;
            $sender = $system ? 'Sistem' : ($m['sender_role'] === 'companion' ? short_name((string) ($m['sender_name'] ?? 'Pendamping')) : 'Penyintas');
            return [
                'id' => (int) $m['id'],
                'mine' => $mine,
                'system' => $system,
                'body' => $m['body'],
                'time' => date('H.i', strtotime($m['created_at'])),
                'day' => self::dayLabel($m['created_at']),
                'sender' => $sender,
                'read' => $mine && $m['read_at'] !== null,
            ];
        }, $rows);

        json_response(['messages' => $out, 'read_upto' => Message::readUpto((int) $r['id'], $side)]);
    }

    public function send(array $p): void
    {
        [$r, $side] = self::context($p['code']);
        if (!in_array($r['status'], CaseWorkflow::ACTIVE, true)) {
            json_response(['error' => CaseWorkflow::isClosed($r['status'])
                ? 'Percakapan ini sudah ditutup.'
                : 'Pesan bisa dikirim setelah pendamping ditugaskan.'], 409);
        }

        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $body = trim((string) (is_array($json) ? ($json['body'] ?? '') : ($_POST['body'] ?? '')));
        if ($body === '') {
            json_response(['error' => 'Tulis pesan dulu.'], 422);
        }
        if (mb_strlen($body) > Message::MAX_LEN) {
            json_response(['error' => 'Pesan maksimal ' . Message::MAX_LEN . ' karakter.'], 422);
        }

        $limiter = RateLimiter::default();
        $key = "msg:$side:" . $r['id'];
        if ($limiter->tooManyAttempts($key, 20)) {
            json_response(['error' => 'Terlalu banyak pesan dalam satu menit. Tunggu sebentar.'], 429);
        }
        $limiter->hit($key, 60);

        $id = Message::create((int) $r['id'], $side, $side === 'companion' ? Auth::id() : ($r['user_id'] !== null ? (int) $r['user_id'] : null), $body);

        if ($side === 'companion') {
            $uid = $r['user_id'] !== null ? (int) $r['user_id'] : null;
            if (!Notifier::hasUnread($uid, (int) $r['id'], 'pesan')) {
                Notifier::survivor($r, 'pesan', 'Pesan baru dari pendamping', '#pesan');
            }
        } elseif (!Notifier::hasUnread((int) $r['assigned_to'], (int) $r['id'], 'pesan')) {
            Notifier::user((int) $r['assigned_to'], (int) $r['id'], 'pesan', 'Pesan baru di ' . $r['code'], '/pendamping/kasus/' . $r['code'] . '#pesan');
        }
        json_response(['ok' => true, 'id' => $id], 201);
    }
}
