<?php
declare(strict_types=1);

/** Module 11: notifications for signed-in users. Anonymous survivors see theirs on the case page. */
final class NotificationController
{
    public function index(): void
    {
        View::show('pages/notifications', [
            'title' => 'Notifikasi | Haven',
            'active' => 'notifikasi',
            'items' => Notifier::forUser((int) Auth::id()),
        ], 'app');
    }

    /** Marks one as read, then follows its link. */
    public function open(array $p): void
    {
        $n = Notifier::find((int) $p['id']);
        if (!$n || (int) $n['user_id'] !== Auth::id()) {
            throw new HttpException(404);
        }
        Notifier::markRead((int) $n['id']);
        $link = (string) $n['link'];
        redirect(preg_match('#^/[^/\\\\]#', $link) ? $link : '/notifikasi');
    }

    public function readAll(): void
    {
        Notifier::markAllRead((int) Auth::id());
        Session::flash('success', 'Semua notifikasi ditandai sudah dibaca.');
        redirect('/notifikasi');
    }

    public function apiList(): void
    {
        $uid = (int) Auth::id();
        $items = array_map(static fn ($n) => [
            'title' => $n['title'],
            'meta' => Notifier::ago($n['created_at']) . ' · ' . (Notifier::TYPES[$n['type']] ?? $n['type']),
            'url' => url('/notifikasi/' . $n['id']),
            'unread' => $n['read_at'] === null,
        ], Notifier::forUser($uid, 5));
        json_response(['unread' => Notifier::unreadCount($uid), 'items' => $items]);
    }

    public function apiReadAll(): void
    {
        Notifier::markAllRead((int) Auth::id());
        json_response(['ok' => true]);
    }
}
