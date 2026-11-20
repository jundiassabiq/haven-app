<?php
declare(strict_types=1);

/** Module 1: profile and account security for every signed-in role. */
final class AccountController
{
    public function show(): void
    {
        $user = Auth::user();
        View::show('pages/account/profile', [
            'title' => 'Profil & keamanan | Haven',
            'active' => 'profil',
            'user' => $user,
            'logins' => User::recentLogins((int) $user['id'], 2),
        ], 'app');
    }

    public function updateProfile(): void
    {
        $user = Auth::user();
        $isSurvivor = $user['role'] === 'survivor';
        $in = [
            'name' => input('name'),
            'email' => User::normalizeEmail(input('email')),
            'safe_time' => input('safe_time'),
        ];
        $errors = Validator::make($in, [
            'name' => 'required|max:' . ($isSurvivor ? 40 : 80),
            'email' => 'required|email',
            'safe_time' => 'max:120',
        ], ['name.required' => 'Nama tidak boleh kosong.']);
        if (!isset($errors['email']) && User::emailTaken($in['email'], (int) $user['id'])) {
            $errors['email'] = 'Email ini sudah dipakai akun lain.';
        }
        if ($errors) {
            back_with('/akun', $errors, $in);
        }

        User::updateProfile(
            (int) $user['id'],
            $in['name'],
            $in['email'],
            $isSurvivor ? input_bool('allow_email') : (bool) $user['allow_email'],
            $isSurvivor ? input_bool('allow_whatsapp') : (bool) $user['allow_whatsapp'],
            $isSurvivor ? $in['safe_time'] : $user['safe_time']
        );
        Audit::log('account.update', 'user', (string) $user['id'], 'Profil diperbarui');
        Session::flash('success', 'Profil disimpan.');
        redirect('/akun');
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        $in = [
            'current_password' => (string) ($_POST['current_password'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ];
        $errors = Validator::make($in, [
            'current_password' => 'required',
            'password' => 'required|min:8|max:200',
            'password_confirm' => 'required|same:password',
        ], [
            'current_password.required' => 'Masukkan kata sandi saat ini.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password_confirm.same' => 'Kata sandi baru tidak sama.',
        ]);
        if (!$errors && !password_verify($in['current_password'], $user['password_hash'])) {
            $errors['current_password'] = 'Kata sandi saat ini salah.';
        }
        if ($errors) {
            back_with('/akun#kata-sandi', $errors);
        }

        $version = User::updatePassword((int) $user['id'], $in['password']);
        Session::regenerate();
        Session::put('session_version', $version); // keep this session; every other one is now invalid
        Audit::log('account.password', 'user', (string) $user['id'], 'Kata sandi diganti');
        Session::flash('success', 'Kata sandi diperbarui. Sesi lain sudah dikeluarkan.');
        redirect('/akun');
    }

    public function logoutOthers(): void
    {
        $id = (int) Auth::id();
        $version = User::bumpSessionVersion($id);
        Session::put('session_version', $version);
        Audit::log('account.sessions', 'user', (string) $id, 'Mengeluarkan sesi lain');
        Session::flash('success', 'Semua sesi lain sudah dikeluarkan.');
        redirect('/akun');
    }

    public function requestDeletion(): void
    {
        $user = Auth::user();
        if ($user['role'] !== 'survivor') {
            throw new HttpException(403);
        }
        if (strtoupper(input('confirm')) !== 'HAPUS') {
            Session::flash('success', 'Akun tidak dihapus. Ketik HAPUS untuk mengonfirmasi.');
            redirect('/akun');
        }
        User::requestDeletion((int) $user['id']);
        Audit::log('account.delete_request', 'user', (string) $user['id'], 'Minta hapus akun');
        Auth::logout();
        Session::flash('success', 'Permintaan hapus akun tercatat. Akun dihapus permanen dalam 7 hari. Masuk kembali untuk membatalkannya.');
        redirect('/');
    }
}
