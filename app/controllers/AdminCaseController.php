<?php
declare(strict_types=1);

/** Module 4 (admin): triage queue, assignment, and closing. Admin never sees chronology or contact. */
final class AdminCaseController
{
    private static function filters(): array
    {
        return [
            'q' => mb_substr(input('q'), 0, 60),
            'status' => input('status'),
            'urgensi' => input('urgensi'),
            'jenis' => input('jenis'),
            'anak' => input('anak'),
            'pendamping' => input('pendamping'),
        ];
    }

    public function queue(): void
    {
        $f = self::filters();
        $pg = paginate(Report::queueCount($f), 20);
        View::show('pages/admin/queue', [
            'title' => 'Antrean kasus | Admin Haven',
            'active' => 'antrean',
            'rows' => Report::queue($f, $pg['per'], $pg['offset']),
            'pg' => $pg,
            'f' => $f,
            'companions' => Database::all("SELECT id, name FROM users WHERE role = 'companion' ORDER BY name"),
        ], 'app');
    }

    private static function load(string $code): array
    {
        $code = ReportCode::normalize($code);
        $r = ReportCode::valid($code) ? Report::findByCode($code) : null;
        if (!$r) {
            throw new HttpException(404);
        }
        return $r;
    }

    public function show(array $p): void
    {
        $r = self::load($p['code']);
        Audit::log('report.triage', 'report', $r['code'], 'Membuka data pemilahan');
        // Req 4.9, 4.10: daftar pendamping yang ditawarkan ditentukan Assignment,
        // bukan hasil query mentah. Kasus anak hanya menawarkan pendamping
        // terlatih di wilayah laporan; bila tidak ada, Admin melihat peringatan
        // dan harus mengonfirmasi penugasannya.
        $assignment = Assignment::candidates(Report::companionsByLoad($r['city']), $r);
        View::show('pages/admin/case', [
            'title' => 'Tinjau ' . $r['code'] . ' | Admin Haven',
            'active' => 'antrean',
            // Req 10.1: halaman admin tidak pernah menampilkan kronologi atau
            // kontak, jadi kolom terenkripsinya dibuang sebelum ke view.
            'report' => Report::withoutCiphertext($r),
            'companion' => $r['assigned_to'] ? User::find((int) $r['assigned_to']) : null,
            'companions' => $assignment['list'],
            'assignment' => $assignment,
            'logs' => Report::statusLogs((int) $r['id']),
            'access' => AuditLog::forObject('report', $r['code'], 12),
            'attachCount' => Report::attachmentCount((int) $r['id']),
        ], 'app');
    }

    public function assign(array $p): void
    {
        $r = self::load($p['code']);
        $back = '/admin/kasus/' . $r['code'];
        $urgency = input('urgensi');
        $companionId = (int) input('pendamping');
        $note = mb_substr(input('catatan'), 0, 500);
        $companion = $companionId ? User::find($companionId) : null;
        $assignment = Assignment::candidates(Report::companionsByLoad($r['city']), $r);
        $confirmed = input('confirm') === '1';

        $errors = [];
        if (!isset(Report::URGENCY[$urgency])) {
            $errors['urgensi'] = 'Pilih urgensi.';
        }
        if (!$companion || $companion['role'] !== 'companion' || !(int) $companion['is_active']) {
            $errors['pendamping'] = 'Pilih pendamping aktif yang akan menangani.';
        } else {
            // Req 4.9, 4.10: pendamping di luar daftar kandidat ditolak, dan
            // jalur peringatan kasus anak butuh konfirmasi Admin.
            $assignError = CaseWorkflow::assignmentError($assignment, $companionId, $confirmed);
            if ($assignError !== null) {
                $errors['pendamping'] = $assignError;
            }
        }
        if ($errors) {
            back_with($back, $errors, ['urgensi' => $urgency, 'pendamping' => (string) $companionId, 'catatan' => $note]);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($urgency !== $r['urgency']) {
                Report::setUrgency((int) $r['id'], $urgency);
                Audit::log('report.urgency', 'report', $r['code'], Report::URGENCY[$r['urgency']] . ' → ' . Report::URGENCY[$urgency]);
                $r['urgency'] = $urgency;
            }
            if ($r['status'] === 'diterima') {
                $r = CaseWorkflow::transition($r, 'ditinjau');
            }
            if ($r['status'] === 'ditinjau') {
                $r = CaseWorkflow::transition($r, 'didampingi', [
                    'companion_id' => $companionId,
                    'candidates' => $assignment,
                    'confirm' => $confirmed,
                ]);
                $msg = $r['code'] . ' ditugaskan ke ' . $companion['name'] . '. Notifikasi dikirim.';
            } elseif (in_array($r['status'], CaseWorkflow::ACTIVE, true)) {
                if ((int) $r['assigned_to'] === $companionId) {
                    $msg = 'Urgensi disimpan. Pendamping tidak berubah.';
                } else {
                    Report::reassign((int) $r['id'], $companionId);
                    Message::system((int) $r['id'], short_name($companion['name']) . ' kini menjadi pendampingmu.');
                    Notifier::user($companionId, (int) $r['id'], 'tugas', 'Kasus dialihkan kepadamu: ' . $r['code'], '/pendamping/kasus/' . $r['code']);
                    Notifier::survivor($r, 'status', 'Pendampingmu sekarang ' . short_name($companion['name']), '#pesan');
                    Audit::log('report.assign', 'report', $r['code'], 'Dialihkan ke ' . $companion['name']);
                    $msg = $r['code'] . ' dialihkan ke ' . $companion['name'] . '.';
                }
            } else {
                throw new DomainException('Kasus yang sudah ' . mb_strtolower(Report::STATUS[$r['status']]) . ' tidak bisa ditugaskan.');
            }
            if ($note !== '') {
                CaseNote::add((int) $r['id'], (int) Auth::id(), 'Catatan dari admin', $note);
            }
            $pdo->commit();
        } catch (DomainException $e) {
            $pdo->rollBack();
            Session::flash('error', $e->getMessage());
            redirect($back);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Session::flash('success', $msg);
        redirect($back);
    }

    public function close(array $p): void
    {
        $r = self::load($p['code']);
        $reason = mb_substr(input('alasan'), 0, 500);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($r['status'] === 'diterima') {
                $err = CaseWorkflow::check('ditinjau', 'ditutup', ['reason' => $reason]);
                if ($err) {
                    throw new DomainException($err);
                }
                $r = CaseWorkflow::transition($r, 'ditinjau');
            }
            CaseWorkflow::transition($r, 'ditutup', ['reason' => $reason]);
            $pdo->commit();
        } catch (DomainException $e) {
            $pdo->rollBack();
            Session::flash('error', $e->getMessage());
            redirect('/admin/kasus/' . $r['code']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Session::flash('success', 'Laporan ' . $r['code'] . ' ditutup. Alasan tersimpan di riwayat status.');
        redirect('/admin/antrean');
    }
}
