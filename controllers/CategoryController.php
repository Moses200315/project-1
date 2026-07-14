<?php

/**
 * CategoryController – Admin Category Management
 * ================================================
 * Routes under /categories/*
 *   index   – paginated admin list
 *   create  – GET: creation form
 *   store   – POST: save new category
 *   edit    – GET: edit form
 *   update  – POST: save changes
 *   delete  – POST: safe delete
 */

declare(strict_types=1);

class CategoryController extends BaseController
{
    private CategoryModel $categoryModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
    }

    /** GET /categories/index */
    public function index(): void
    {
        $this->requireAdmin();

        $this->view('admin/categories/index', [
            'pageTitle'  => 'Category Management',
            'categories' => $this->categoryModel->getAllForAdmin(),
        ]);
    }

    /** GET /categories/create */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('admin/categories/create', [
            'pageTitle' => 'Add Category',
        ]);
    }

    /** POST /categories/store */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = Security::cleanString($this->post('name'));
        $slug = Security::cleanString($this->post('slug') ?: $name);
        $desc = Security::cleanTextarea($this->post('description'));
        $stat = $this->post('status', 'active');

        if (empty($name)) {
            $this->error('Category name is required.');
            Session::setOldInput($_POST);
            $this->redirectTo(url('categories/create'));
        }

        if ($this->categoryModel->isSlugTaken($slug)) {
            $slug = $slug . '-' . time(); // auto-make unique
        }

        // Handle optional image
        $imageName = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = upload_image($_FILES['image'], RECIPE_IMG_PATH);
            if (!$upload['success']) {
                $this->error($upload['error']);
                $this->redirectTo(url('categories/create'));
            }
            $imageName = $upload['filename'];
        }

        $this->categoryModel->createCategory([
            'admin_id'    => $this->userId(),
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'image'       => $imageName,
            'status'      => in_array($stat, ['active','inactive'], true) ? $stat : 'active',
        ]);

        $this->success("Category \"{$name}\" created successfully.");
        $this->redirectTo(url('categories/index'));
    }

    /** GET /categories/edit/{id} */
    public function edit(string $id = '0'): void
    {
        $this->requireAdmin();
        $catId    = $this->resolveId($id);
        $category = $this->categoryModel->findById($catId);

        if (!$category) { $this->abort404('Category not found.'); }

        $this->view('admin/categories/edit', [
            'pageTitle' => 'Edit: ' . e($category['name']),
            'category'  => $category,
        ]);
    }

    /** POST /categories/update/{id} */
    public function update(string $id = '0'): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $catId    = $this->resolveId($id);
        $category = $this->categoryModel->findById($catId);

        if (!$category) { $this->abort404(); }

        $name = Security::cleanString($this->post('name'));
        $slug = Security::cleanString($this->post('slug') ?: $name);

        if (empty($name)) {
            $this->error('Category name is required.');
            $this->redirectTo(url('categories/edit/' . $catId));
        }

        if ($this->categoryModel->isSlugTaken($slug, $catId)) {
            $this->error("The slug \"{$slug}\" is already in use. Please choose another.");
            $this->redirectTo(url('categories/edit/' . $catId));
        }

        $updateData = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => Security::cleanTextarea($this->post('description')),
            'status'      => $this->post('status', 'active'),
        ];

        if (!empty($_FILES['image']['name'])) {
            $upload = upload_image($_FILES['image'], RECIPE_IMG_PATH, $category['image']);
            if (!$upload['success']) {
                $this->error($upload['error']);
                $this->redirectTo(url('categories/edit/' . $catId));
            }
            $updateData['image'] = $upload['filename'];
        }

        $this->categoryModel->updateCategory($catId, $updateData);
        $this->success('Category updated successfully.');
        $this->redirectTo(url('categories/index'));
    }

    /** POST /categories/delete/{id} */
    public function delete(string $id = '0'): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $catId  = $this->resolveId($id);
        $result = $this->categoryModel->safeDelete($catId);

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }
        $this->redirectTo(url('categories/index'));
    }
}
