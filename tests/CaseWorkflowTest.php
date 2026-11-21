<?php
declare(strict_types=1);

test('allowed transitions follow the documented flow', function () {
    foreach ([['diterima', 'ditinjau'], ['ditinjau', 'didampingi'], ['ditinjau', 'ditutup'], ['didampingi', 'dirujuk'], ['didampingi', 'selesai'], ['dirujuk', 'selesai']] as [$a, $b]) {
        assert_true(CaseWorkflow::canTransition($a, $b), "$a → $b should be allowed");
    }
});

test('skipping steps, going back, and reopening are refused', function () {
    foreach ([['diterima', 'didampingi'], ['diterima', 'selesai'], ['diterima', 'ditutup'], ['didampingi', 'ditinjau'], ['dirujuk', 'didampingi'],
              ['didampingi', 'ditutup'], ['selesai', 'didampingi'], ['ditutup', 'ditinjau'], ['selesai', 'selesai']] as [$a, $b]) {
        assert_false(CaseWorkflow::canTransition($a, $b), "$a → $b should be refused");
    }
});

test('final states have no next status', function () {
    assert_same([], CaseWorkflow::allowedNext('selesai'));
    assert_same([], CaseWorkflow::allowedNext('ditutup'));
    assert_true(CaseWorkflow::isClosed('ditutup'));
    assert_false(CaseWorkflow::isClosed('dirujuk'));
});

test('check explains why a transition is refused', function () {
    assert_same('Status Diterima tidak bisa diubah menjadi Selesai.', CaseWorkflow::check('diterima', 'selesai'));
    assert_same('Status tidak dikenal.', CaseWorkflow::check('diterima', 'dihapus'));
});

test('assignment requires a companion', function () {
    assert_same('Pilih pendamping yang akan menangani.', CaseWorkflow::check('ditinjau', 'didampingi'));
    assert_same(null, CaseWorkflow::check('ditinjau', 'didampingi', ['companion_id' => 2]));
});

test('referral requires a service', function () {
    assert_same('Pilih layanan tujuan rujukan.', CaseWorkflow::check('didampingi', 'dirujuk'));
    assert_same(null, CaseWorkflow::check('didampingi', 'dirujuk', ['service_id' => 3]));
});

test('closing requires a reason of at least 10 characters', function () {
    assert_true(CaseWorkflow::check('ditinjau', 'ditutup', ['reason' => 'spam']) !== null);
    assert_same(null, CaseWorkflow::check('ditinjau', 'ditutup', ['reason' => 'Laporan duplikat dari HV-ABC234']));
});

test('transition() refuses an invalid move before touching the database', function () {
    assert_throws(DomainException::class, fn () => CaseWorkflow::transition(['id' => 1, 'status' => 'diterima'], 'selesai'));
});
