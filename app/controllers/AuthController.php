<?php
declare(strict_types=1);

/** Module 1: sign in, sign up, sign out, password recovery. */
final class AuthController
{
    private const LOGIN_MAX = 5;
    private const LOGIN_WINDOW = 15 * 60;

    public function showLogin(): void
    {
        View::show('pages/auth/login', ['title' => 'Masuk | Haven', 'noFooter' => true, 'formError' => Session::getFlash('form_error')]);
    }

    public function login(): void
    {
        $email = User::normalizeEmail(input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $old = ['email' => $email];

        $errors = Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email', 'password' => 'required'], [
            'email.required' => 'Masukkan email akunmu.',
            'password.required' => 'Masukkan kata sandimu.',
        ]);
        if ($errors) {
            back_with('/masuk', $errors, $old);
        }

        // Limit per IP and per account, so one attacker cannot lock out every user and cannot spray one account.
        $limiter = RateLimiter::default();
        $keys = ['login:ip:' . client_ip(), 'login:email:' . $email];
        foreach ($keys as $k) {
            if ($limiter->tooManyAttempts($k, self::LOGIN_MAX)) {
                Session::flash('form_error', 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . RateLimiter::waitText($limiter->availableIn($k)) . '.');
                back_with('/masuk', [], $old);
            }
        }

        $user = User::findByEmail($email);
        // Verify against a real bcrypt hash even for unknown emails, so response time does not reveal which accounts exist.
        $ok = password_verify($password, $user['password_hash'] ?? '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW');

        if (!$user || !$ok) {
            foreach ($keys as $k) {
                $limiter->hit($k, self::LOGIN_WINDOW);
            }
            Audit::log('auth.failed', 'user', $user ? (string) $user['id'] : null, 'Gagal masuk', $user, $user['name'] ?? 'Tidak dikenal');
            $left = self::LOGIN_MAX - $limiter->attempts($keys[1]);
            Session::flash('form_error', 'Email atau kata sandi salah.' . ($left > 0 && $left <= 2 ? " Sisa $left percobaan sebelum dikunci 15 menit." : ''));
            back_with('/masuk', [], $old);
        }
        if (!(int) $user['is_active']) {
            Session::flash('form_error', 'Akun ini dinonaktifkan. Hubungi admin Haven jika menurutmu ini keliru.');
            back_with('/masuk', [], $old);
        }

        foreach ($keys as $k) {
            $limiter->clear($k);
        }
        User::rehashIfNeeded($user, $password);
        $cancelledDeletion = $user['delete_requested_at'] !== null;
        User::recordLogin((int) $user['id']);
        $user = User::find((int) $user['id']);

        Auth::login($user);
        Audit::log('auth.login', 'user', (string) $user['id'], 'Masuk');

        Session::flash('success', $cancelledDeletion
            ? 'Permintaan hapus akun dibatalkan karena kamu masuk kembali.'
            : 'Selamat datang kembali, ' . $user['name'] . '.');
        redirect(Auth::intendedPath($user));
    }

    public function logout(): void
    {
        if (Auth::check()) {
            Audit::log('auth.logout', 'user', (string) Auth::id(), 'Keluar');
        }
        Auth::logout();
        Session::flash('success', 'Kamu sudah keluar dari akun.');
        redirect('/masuk');
    }

    public function showRegister(): void
    {
        View::show('pages/auth/register', ['title' => 'Daftar | Haven', 'noFooter' => true]);
    }

    public function register(): void
    {
        $in = [
            'name' => input('name'),
            'email' => User::normalizeEmail(input('email')),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
            'agree' => input('agree'),
        ];
        $old = ['name' => $in['name'], 'email' => $in['email'], 'agree' => $in['agree']];

        $limiter = RateLimiter::default();
        $key = 'register:ip:' . client_ip();
        if ($limiter->tooManyAttempts($key, 10)) {
            throw new HttpException(429, 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi dalam ' . RateLimiter::waitText($limiter->availableIn($key)) . '.');
        }

        $errors = User::registrationErrors($in);
        if (!isset($errors['email']) && User::emailTaken($in['email'])) {
            $errors['email'] = 'Email ini sudah terdaftar. Masuk, atau gunakan "Lupa kata sandi".';
        }
        if ($errors) {
            back_with('/daftar', $errors, $old);
        }

        $limiter->hit($key, 3600);
        $id = User::create($in['name'], $in['email'], $in['password'], 'survivor');
        User::recordLogin($id);
        $user = User::find($id);
        Auth::login($user);
        Audit::log('auth.register', 'user', (string) $id, 'Akun penyintas dibuat');
        Audit::log('auth.login', 'user', (string) $id, 'Masuk');

        Session::flash('success', 'Akunmu sudah dibuat. Selamat datang, ' . $user['name'] . '.');
        redirect('/penyintas');
    }

    public function showForgot(): void
    {
        View::show('pages/auth/forgot', ['title' => 'Lupa kata sandi | Haven', 'noFooter' => true, 'sent' => (bool) Session::getFlash('reset_sent')]);
    }

    public function forgot(): void
    {
        $email = User::normalizeEmail(input('email'));
        $errors = Validator::make(['email' => $email], ['email' => 'required|email'], ['email.required' => 'Masukkan email akunmu.']);
        if ($errors) {
            back_with('/lupa-password', $errors, ['email' => $email]);
        }

        $limiter = RateLimiter::default();
        $key = 'forgot:ip:' . client_ip();
        if ($limiter->tooManyAttempts($key, 5)) {
            throw new HttpException(429, 'Terlalu banyak permintaan tautan. Coba lagi dalam ' . RateLimiter::waitText($limiter->availableIn($key)) . '.');
        }
        $limiter->hit($key, 15 * 60);

        $user = User::findByEmail($email);
        if ($user && (int) $user['is_active']) {
            $token = PasswordReset::issue((int) $user['id']);
            self::sendResetMail($user, $token, false);
            Audit::log('auth.reset_request', 'user', (string) $user['id'], 'Minta tautan reset', $user);
        }
        // Same response whether or not the email exists, so the form cannot be used to discover accounts.
        Session::flash('reset_sent', true);
        redirect('/lupa-password');
    }

    public function showReset(): void
    {
        $token = input('token');
        $look = PasswordReset::lookup($token);
        View::show('pages/auth/reset', [
            'title' => 'Buat kata sandi baru | Haven',
            'noFooter' => true,
            'state' => $look['status'],
            'token' => $token,
            'email' => $look['reset']['email'] ?? '',
            'invite' => array_key_exists('last_login_at', $look['reset'] ?? []) && $look['reset']['last_login_at'] === null,
        ]);
    }

    public function reset(): void
    {
        $token = input('token');
        $look = PasswordReset::lookup($token);
        if ($look['status'] !== 'ok') {
            redirect('/reset-password?token=' . rawurlencode($token));
        }
        $in = ['password' => (string) ($_POST['password'] ?? ''), 'password_confirm' => (string) ($_POST['password_confirm'] ?? '')];
        $errors = Validator::make($in, ['password' => 'required|min:8|max:200', 'password_confirm' => 'required|same:password'], [
            'password.required' => 'Buat kata sandi minimal 8 karakter.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password_confirm.required' => 'Ketik ulang kata sandi barumu.',
            'password_confirm.same' => 'Kata sandi tidak sama dengan yang di atas.',
        ]);
        if ($errors) {
            back_with('/reset-password?token=' . rawurlencode($token), $errors);
        }

        $reset = $look['reset'];
        User::updatePassword((int) $reset['user_id'], $in['password']);
        PasswordReset::markUsed((int) $reset['id']);
        Audit::log('auth.reset', 'user', (string) $reset['user_id'], 'Kata sandi diatur ulang', ['id' => $reset['user_id']], $reset['name']);

        Auth::logout();
        Session::flash('success', 'Kata sandi diperbarui. Silakan masuk dengan kata sandi barumu.');
        redirect('/masuk');
    }

    /** Also used by admin invites. */
    public static function sendResetMail(array $user, string $token, bool $invite): void
    {
        $link = Mailer::absoluteUrl('/reset-password', ['token' => $token]);
        // Tipe email menentukan apakah surat tetap dikirim ke pengguna yang
        // mematikan email. Keduanya satu-satunya jalan masuk ke akun sendiri,
        // jadi keduanya ada di MailPolicy::ALWAYS (Req 6.14).
        if ($invite) {
            Mailer::send($user['email'], 'Undangan bergabung di Haven', "Halo {$user['name']},\n\nAdmin Haven membuatkan akun untukmu. Buat kata sandi lewat tautan berikut (berlaku 3 hari):\n$link\n\nJika kamu tidak merasa diundang, abaikan email ini.", 'invitation');
        } else {
            Mailer::send($user['email'], 'Atur ulang kata sandi Haven', "Halo {$user['name']},\n\nKami menerima permintaan untuk mengatur ulang kata sandimu. Buka tautan berikut dalam " . PasswordReset::RESET_MINUTES . " menit:\n$link\n\nJika bukan kamu yang meminta, abaikan email ini. Kata sandimu tidak berubah.", 'password_reset');
        }
    }
}
