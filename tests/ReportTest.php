<?php
declare(strict_types=1);

test('report code: HV- plus 6 characters from the unambiguous alphabet', function () {
    for ($i = 0; $i < 200; $i++) {
        $c = ReportCode::generate(fn () => false);
        assert_true(ReportCode::valid($c), "bad code $c");
        assert_same(0, preg_match('/[01IOL]/', substr($c, 3)), "ambiguous character in $c");
    }
});

test('report code: retries until an unused code is found', function () {
    $tries = 0;
    $c = ReportCode::generate(function () use (&$tries) { return ++$tries < 4; });
    assert_same(4, $tries);
    assert_true(ReportCode::valid($c));
});

test('report code: gives up instead of looping forever', function () {
    assert_throws(RuntimeException::class, fn () => ReportCode::generate(fn () => true, 5));
});

test('report code: 1000 codes are unique', function () {
    $seen = [];
    for ($i = 0; $i < 1000; $i++) {
        $seen[ReportCode::generate(fn ($c) => isset($seen[$c]))] = true;
    }
    assert_same(1000, count($seen));
});

test('PIN is always 6 digits, including leading zeros', function () {
    for ($i = 0; $i < 300; $i++) {
        assert_true(preg_match('/^[0-9]{6}$/', ReportCode::pin()) === 1);
    }
});

test('code input is normalized (case, spaces, missing dash)', function () {
    assert_same('HV-9B2X6P', ReportCode::normalize(' hv-9b2x6p '));
    assert_same('HV-9B2X6P', ReportCode::normalize('HV9B2X6P'));
    assert_false(ReportCode::valid('HV-9B2X6'));
});

function report_input(array $o = []): array
{
    return $o + ['jenis' => 'nonfisik', 'bahaya' => 'tidak', 'kronologi' => str_repeat('Kejadian di kampus. ', 3), 'berlangsung' => 'tidak',
        'tanggal' => '', 'tempat' => '', 'provinsi' => '', 'kota' => '', 'pelaku' => '', 'identitas' => 'anonim',
        'email' => '', 'wa' => '', 'kontak_email' => '', 'kontak_wa' => '', 'setuju' => '1'];
}

test('report validation: minimal anonymous report is valid', function () {
    assert_same([], Report::validationErrors(report_input(), false));
});

test('report validation: required fields and allowed values', function () {
    $e = Report::validationErrors(report_input(['jenis' => 'lain', 'bahaya' => '', 'kronologi' => 'pendek', 'berlangsung' => 'x', 'setuju' => '']), false);
    assert_same(['jenis', 'bahaya', 'kronologi', 'berlangsung', 'setuju'], array_keys($e));
});

test('report validation: optional lists only accept known values', function () {
    $e = Report::validationErrors(report_input(['provinsi' => 'Atlantis', 'tempat' => '<script>', 'pelaku' => 'Musuh']), false);
    assert_same(['tempat', 'provinsi', 'pelaku'], array_keys($e));
});

test('report validation: future incident dates are rejected', function () {
    $e = Report::validationErrors(report_input(['tanggal' => date('Y-m-d', time() + 86400 * 2)]), false);
    assert_same('Tanggal tidak boleh setelah hari ini.', $e['tanggal']);
});

test('report validation: a checked contact channel needs its value', function () {
    $e = Report::validationErrors(report_input(['kontak_email' => '1', 'kontak_wa' => '1']), false);
    assert_true(isset($e['email'], $e['wa']));
    $e = Report::validationErrors(report_input(['kontak_wa' => '1', 'wa' => 'abc']), false);
    assert_true(isset($e['wa']));
});

test('report validation: "with account" only for signed-in survivors', function () {
    assert_true(isset(Report::validationErrors(report_input(['identitas' => 'akun']), false)['identitas']));
    assert_same([], Report::validationErrors(report_input(['identitas' => 'akun']), true));
});

test('initial urgency follows danger and ongoing answers', function () {
    assert_same('tinggi', Report::initialUrgency(true, 'tidak'));
    assert_same('sedang', Report::initialUrgency(false, 'ya'));
    assert_same('rendah', Report::initialUrgency(false, 'tidak'));
});
