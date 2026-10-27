<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminPageTitle = 'Categories';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sort = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if (strlen($name) < 2 || strlen($name) > 120 || $slug === '' || strlen($description) > 500 || $sort === false || $sort < 0 || $sort > 1000000) {
            flash('error', 'Enter a valid category name, description and sort order.');
        } else {
            try {
                if ($id) {
                    $save = $pdo->prepare('UPDATE categories SET name=?,slug=?,description=?,sort_order=?,is_active=? WHERE id=?');
                    $save->execute([$name,$slug,$description,$sort,isset($_POST['is_active']) ? 1 : 0,$id]);
                    flash('success', 'Category updated.');
                } else {
                    $save = $pdo->prepare('INSERT INTO categories (name,slug,description,sort_order,is_active) VALUES (?,?,?,?,?)');
                    $save->execute([$name,$slug,$description,$sort,isset($_POST['is_active']) ? 1 : 0]);
                    flash('success', 'Category created.');
                }
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') throw $exception;
                flash('error', 'A category with that name or web address already exists.');
            }
        }
    } elseif ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE categories SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
        flash('success', 'Category visibility updated.');
    } elseif ($action === 'delete' && $id) {
        try {
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            flash('success', 'Category deleted.');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') throw $exception;
            flash('error', 'This category still has products. Move or delete those products before removing it.');
        }
    }
    redirect('categories.php');
}
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$edit = null;
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM categories WHERE id=?');
    $query->execute([$editId]);
    $edit = $query->fetch();
}
$categories = $pdo->query('SELECT c.*,COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order,c.name')->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Categories</h1><p>Organize and control which collections are visible in the store.</p></div></div>
<div class="admin-grid"><section class="admin-panel"><h2><?= $edit ? 'Edit category' : 'Add category' ?></h2><form method="post" class="admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="save"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?><label class="wide">Name<input name="name" maxlength="120" value="<?= h($edit['name'] ?? '') ?>" required></label><label class="wide">Description<input name="description" maxlength="500" value="<?= h($edit['description'] ?? '') ?>"></label><label>Sort order<input type="number" name="sort_order" value="<?= h($edit['sort_order'] ?? 0) ?>"></label><label class="checkbox-label"><input type="checkbox" name="is_active" value="1" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>> Visible in store</label><button class="button button-dark"><?= $edit ? 'Save category' : 'Create category' ?></button><?php if ($edit): ?><a href="categories.php" class="text-link">Cancel</a><?php endif; ?></form></section>
<section class="admin-panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Category</th><th>Products</th><th>Visibility</th><th>Manage</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><?= h($category['name']) ?><br><small><?= h($category['slug']) ?></small></td><td><?= (int) $category['product_count'] ?></td><td><?= $category['is_active'] ? 'Visible' : 'Hidden' ?></td><td><div class="admin-actions"><a class="small-button" href="categories.php?edit=<?= (int) $category['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="small-button"><?= $category['is_active'] ? 'Hide' : 'Show' ?></button></form><form method="post" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><input type="hidden" name="action" value="delete"><button class="small-button danger-button">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
