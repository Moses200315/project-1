<?php

/**
 * NotificationController – In-App Notifications
 * ===============================================
 * index        – notification inbox
 * markRead     – AJAX/POST: mark one as read
 * markAllRead  – POST: mark all as read
 * delete/{id}  – POST: delete one notification
 */

declare(strict_types=1);

class NotificationController extends BaseController
{
    private NotificationModel $notifModel;

    public function __construct()
    {
        $this->notifModel = new NotificationModel();
    }

    /** GET /notifications/index */
    public function index(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $this->view('customer/notifications/index', [
            'pageTitle'     => 'Notifications',
            'notifications' => $this->notifModel->getForUser($userId),
            'unreadCount'   => $this->notifModel->countUnread($userId),
        ]);
    }

    /** POST /notifications/markRead */
    public function markRead(): never
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $id     = $this->intInput('id');
        $marked = $this->notifModel->markRead($id, $this->userId());

        if ($this->isAjax()) {
            $this->json([
                'success'     => $marked,
                'unread_count'=> $this->notifModel->countUnread($this->userId()),
            ]);
        }

        $this->redirectTo(url('notifications/index'));
    }

    /** POST /notifications/markAllRead */
    public function markAllRead(): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $this->notifModel->markAllRead($this->userId());
        $this->success('All notifications marked as read.');
        $this->redirectTo(url('notifications/index'));
    }

    /** POST /notifications/delete/{id} */
    public function delete(string $id = '0'): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $notifId = $this->resolveId($id);

        $deleted = $this->notifModel->deleteForUser($notifId, $this->userId());

        if ($this->isAjax()) {
            $this->json(['success' => $deleted]);
        }

        $deleted
            ? $this->success('Notification deleted.')
            : $this->error('Notification not found.');

        $this->redirectTo(url('notifications/index'));
    }

    /** GET /notifications/unreadCount  (AJAX) */
    public function unreadCount(): never
    {
        $this->requireCustomer();
        $this->json(['count' => $this->notifModel->countUnread($this->userId())]);
    }
}
