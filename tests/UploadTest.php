<?php
declare(strict_types=1);

function tmp_file(string $bytes): string
{
    $p = tempnam(sys_get_temp_dir(), 'hvt');
    file_put_contents($p, $bytes);
    register_shutdown_function(fn () => @unlink($p));
    return $p;
}

const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

test('accepts a real PNG, JPEG, and PDF by content', function () {
    $png = tmp_file(base64_decode(PNG_1PX));
    assert_same(null, Upload::check($png, filesize($png), UPLOAD_ERR_OK));
    $jpg = tmp_file("\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" . str_repeat("\x00", 64) . "\xFF\xD9");
    assert_same('image/jpeg', Upload::mime($jpg));
    $pdf = tmp_file("%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
    assert_same(null, Upload::check($pdf, filesize($pdf), UPLOAD_ERR_OK));
});

test('rejects a script renamed to .png (type comes from content, not name)', function () {
    $fake = tmp_file('<?php echo "hi";');
    assert_same('Bukti bukan JPG, PNG, atau PDF.', Upload::check($fake, filesize($fake), UPLOAD_ERR_OK, 'Bukti'));
});

test('rejects HTML and SVG', function () {
    $svg = tmp_file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    assert_true(Upload::check($svg, filesize($svg), UPLOAD_ERR_OK) !== null);
});

test('rejects files over 5 MB, also when PHP reports the size error', function () {
    $png = tmp_file(base64_decode(PNG_1PX));
    assert_same('File lebih dari 5 MB.', Upload::check($png, Upload::MAX_BYTES + 1, UPLOAD_ERR_OK));
    assert_same('File lebih dari 5 MB.', Upload::check('', 0, UPLOAD_ERR_INI_SIZE));
});

test('rejects empty and failed uploads', function () {
    $empty = tmp_file('');
    assert_same('File kosong.', Upload::check($empty, 0, UPLOAD_ERR_OK));
    assert_true(str_contains((string) Upload::check('', 10, UPLOAD_ERR_PARTIAL), 'gagal diunggah'));
});

test('normalize turns $_FILES multi-arrays into a list and skips empty slots', function () {
    $files = ['name' => ['a.png', ''], 'type' => ['image/png', ''], 'tmp_name' => ['/tmp/a', ''], 'error' => [0, UPLOAD_ERR_NO_FILE], 'size' => [10, 0]];
    $out = Upload::normalize($files);
    assert_same(1, count($out));
    assert_same('a.png', $out[0]['name']);
    assert_same([], Upload::normalize(null));
});

test('stored names must be 64 hex characters (no path traversal)', function () {
    assert_throws(InvalidArgumentException::class, fn () => Upload::path('../../.env'));
    assert_true(str_ends_with(Upload::path(str_repeat('a', 64)), str_repeat('a', 64)));
});
