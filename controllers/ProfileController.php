<?php

/**
 * ProfileController – Customer & Admin Profile Management
 * =========================================================
 * index          – GET: view/edit profile form
 * update         – POST: save profile changes
 * changePassword – POST: update password
 * uploadAvatar   – POST: update profile photo
 * downloads      – customer: PDF download history
 */

declare(strict_types=1);

class ProfileController extends BaseController
{
    private UserModel           $userModel;
    private AdminModel          $adminModel;
    private RecipeDownloadModel $downloadModel;
    private SubscriptionModel   $subModel;

    public function __construct()
    {
        $this->userModel     = new UserModel();
        $this->adminModel    = new AdminModel();
        $this->downloadModel = new RecipeDownloadModel();
        $this->subModel      = new SubscriptionModel();
    }

    /** GET /profile/index */
    public function index(): void
    {
        $this->requireLogin();

        if (Session::isAdmin()) {
            $profile = $this->adminModel->findById($this->userId());
        } else {
            $profile     = $this->userModel->findById($this->userId());
            $permissions = $this->subModel->getUserPermissions($this->userId());
        }

        $this->view('customer/profile/index', [
            'pageTitle'   => 'My Profile',
            'profile'     => $profile,
            'permissions' => $permissions ?? null,
            'isAdmin'     => Session::isAdmin(),
        ]);
    }

    /** POST /profile/update */
    public function update(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $userId    = $this->userId();
        $firstName = Security::cleanString($this->post('first_name'));
        $lastName  = Security::cleanString($this->post('last_name'));

        if (empty($firstName) || empty($lastName)) {
            $this->error('First name and last name are required.');
            $this->redirectTo(url('profile/index'));
        }

        if (Session::isAdmin()) {
            $this->adminModel->updateProfile($userId, [
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
        } else {
            $this->userModel->updateProfile($userId, [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'phone'      => $this->post('phone'),
                'bio'        => $this->post('bio'),
            ]);
        }

        // Refresh session name fields
        Session::updateAuthField('first_name', $firstName);
        Session::updateAuthField('last_name',  $lastName);

        $this->success('Profile updated successfully.');
        $this->redirectTo(url('profile/index'));
    }

    /** POST /profile/changePassword */
    public function changePassword(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $userId = $this->userId();

        // Verify current password
        if (Session::isAdmin()) {
            $record = $this->adminModel->findById($userId);
        } else {
            $record = $this->userModel->findById($userId);
        }

        if (!$record || !Security::verifyPassword($currentPw, $record['password'])) {
            $this->error('Current password is incorrect.');
            $this->redirectTo(url('profile/index'));
        }

        $pwCheck = Security::validatePasswordStrength($newPw);
        if (!$pwCheck['valid']) {
            foreach ($pwCheck['errors'] as $err) { $this->error($err); }
            $this->redirectTo(url('profile/index'));
        }

        if ($newPw !== $confirmPw) {
            $this->error('New passwords do not match.');
            $this->redirectTo(url('profile/index'));
        }

        if (Session::isAdmin()) {
            $this->adminModel->updatePassword($userId, $newPw);
        } else {
            $this->userModel->updatePassword($userId, $newPw);
        }

        $this->success('Password changed successfully.');
        $this->redirectTo(url('profile/index'));
    }

    /** POST /profile/uploadAvatar */
    public function uploadAvatar(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        if (empty($_FILES['avatar']['name'])) {
            $this->error('Please select an image file.');
            $this->redirectTo(url('profile/index'));
        }

        $userId = $this->userId();

        if (Session::isAdmin()) {
            $current = $this->adminModel->findById($userId);
        } else {
            $current = $this->userModel->findById($userId);
        }

        $upload = upload_image($_FILES['avatar'], PROFILE_IMG_PATH, $current['avatar'] ?? null);

        if (!$upload['success']) {
            $this->error($upload['error']);
            $this->redirectTo(url('profile/index'));
        }

        if (Session::isAdmin()) {
            $this->adminModel->updateAvatar($userId, $upload['filename']);
        } else {
            $this->userModel->updateAvatar($userId, $upload['filename']);
        }

        Session::updateAuthField('avatar', $upload['filename']);
        $this->success('Profile photo updated.');
        $this->redirectTo(url('profile/index'));
    }

    /** GET /profile/downloads */
    public function downloads(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $result = $this->downloadModel->getByUser($userId, $this->currentPage());

        $this->view('customer/profile/downloads', [
            'pageTitle'   => 'Download History',
            'rows'        => $result['rows'],
            'pager'       => $result['pager'],
            'permissions' => $this->subModel->getUserPermissions($userId),
        ]);
    }
}
