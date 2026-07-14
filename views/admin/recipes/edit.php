<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
    <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Recipe</h5>
  </div>
  <div class="card-body px-4 pb-4">
    <form method="POST" action="<?= url('recipes/update/'.$recipe['id']) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <h6 class="fw-bold text-success mb-3 mt-2 border-bottom pb-2">📝 Basic Information</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <label class="form-label fw-semibold">Recipe Title *</label>
          <input type="text" name="title" class="form-control" value="<?= e($recipe['title']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Category *</label>
          <select name="category_id" class="form-select" required>
            <?php foreach($categories as $id=>$name): ?>
              <option value="<?= $id ?>" <?= $recipe['category_id']==$id?'selected':'' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Description *</label>
        <textarea name="description" class="form-control" rows="3" required><?= e($recipe['description']) ?></textarea>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-2"><label class="form-label fw-semibold">Prep (min)</label>
          <input type="number" name="prep_time" class="form-control" value="<?= e($recipe['prep_time']) ?>" min="0"></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Cook (min)</label>
          <input type="number" name="cook_time" class="form-control" value="<?= e($recipe['cook_time']) ?>" min="0"></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Servings *</label>
          <input type="number" name="servings" class="form-control" value="<?= e($recipe['servings']) ?>" min="1" required></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Energy content</label>
          <input type="number" name="calories" class="form-control" value="<?= e($recipe['calories']) ?>" min="0"></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Difficulty *</label>
          <select name="difficulty" class="form-select" required>
            <?php foreach(['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $recipe['difficulty']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <?php foreach(['published'=>'Published','draft'=>'Draft','archived'=>'Archived'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $recipe['status']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Cover Image</label>
          <?php if($recipe['image']): ?>
            <div class="mb-2"><img src="<?= recipe_img_url($recipe['image']) ?>" style="height:80px;object-fit:cover;border-radius:8px;" alt="current"></div>
          <?php endif; ?>
          <input type="file" name="image" class="form-control" accept="image/*">
          <small class="text-muted">Leave blank to keep current image</small>
        </div>
        <div class="col-md-6 d-flex align-items-end gap-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_premium" value="1" <?= $recipe['is_premium']?'checked':'' ?>>
            <label class="form-check-label fw-semibold">⭐ Premium</label>
          </div>
        </div>
      </div>

      <!-- Ingredients -->
      <h6 class="fw-bold text-success mb-3 border-bottom pb-2">🛒 Ingredients</h6>
      <div id="ingredientList">
        <?php foreach($recipe['ingredients'] as $i=>$ing): ?>
        <div class="row g-2 mb-2 ingredient-row">
          <div class="col-md-5"><input type="text" name="ingredients[<?= $i ?>][name]" class="form-control" value="<?= e($ing['name']) ?>" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[<?= $i ?>][quantity]" class="form-control" value="<?= e($ing['quantity']) ?>" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[<?= $i ?>][unit]" class="form-control" value="<?= e($ing['unit']) ?>"></div>
          <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($recipe['ingredients'])): ?>
        <div class="row g-2 mb-2 ingredient-row">
          <div class="col-md-5"><input type="text" name="ingredients[0][name]" class="form-control" placeholder="Ingredient" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[0][quantity]" class="form-control" placeholder="Qty" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[0][unit]" class="form-control" placeholder="Unit"></div>
          <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div>
        </div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addIngredient()">
        <i class="bi bi-plus-circle me-1"></i>Add Ingredient
      </button>

      <!-- Procedures -->
      <h6 class="fw-bold text-success mt-4 mb-3 border-bottom pb-2">👨‍🍳 Cooking Procedure</h6>
      <div id="procedureList">
        <?php foreach($recipe['procedures'] as $i=>$proc): ?>
        <div class="card mb-3 procedure-row border-0 bg-light" style="border-radius:10px;">
          <div class="card-body pb-2">
            <div class="d-flex justify-content-between mb-2">
              <span class="badge bg-success step-num">Step <?= (int)$proc['step_number'] ?></span>
              <button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button>
            </div>
            <textarea name="procedures[<?= $i ?>][instruction]" class="form-control mb-2" rows="2" required><?= e($proc['instruction']) ?></textarea>
            <input type="text" name="procedures[<?= $i ?>][tip]" class="form-control form-control-sm" value="<?= e($proc['tip']) ?>" placeholder="💡 Optional tip">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-success btn-sm" onclick="addProcedure()">
        <i class="bi bi-plus-circle me-1"></i>Add Step
      </button>

      <div class="d-flex gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary px-4 fw-semibold">
          <i class="bi bi-check-lg me-2"></i>Update Recipe
        </button>
        <a href="<?= url('recipes/adminIndex') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = '<script>
let ingIdx='.count($recipe['ingredients']).', procIdx='.count($recipe['procedures']).';
function addIngredient(){const div=document.createElement("div");div.className="row g-2 mb-2 ingredient-row";div.innerHTML=`<div class="col-md-5"><input type="text" name="ingredients[${ingIdx}][name]" class="form-control" placeholder="Ingredient" required></div><div class="col-md-3"><input type="text" name="ingredients[${ingIdx}][quantity]" class="form-control" placeholder="Qty" required></div><div class="col-md-3"><input type="text" name="ingredients[${ingIdx}][unit]" class="form-control" placeholder="Unit"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div>`;document.getElementById("ingredientList").appendChild(div);ingIdx++;attachRemove();}
function addProcedure(){const div=document.createElement("div");div.className="card mb-3 procedure-row border-0 bg-light";div.style.borderRadius="10px";div.innerHTML=`<div class="card-body pb-2"><div class="d-flex justify-content-between mb-2"><span class="badge bg-success step-num">Step ${procIdx+1}</span><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button></div><textarea name="procedures[${procIdx}][instruction]" class="form-control mb-2" rows="2" required></textarea><input type="text" name="procedures[${procIdx}][tip]" class="form-control form-control-sm" placeholder="💡 Optional tip"></div>`;document.getElementById("procedureList").appendChild(div);procIdx++;attachRemove();}
function attachRemove(){document.querySelectorAll(".remove-row").forEach(b=>{b.onclick=function(){this.closest(".ingredient-row,.procedure-row").remove();renumber();};});}
function renumber(){document.querySelectorAll(".step-num").forEach((s,i)=>s.textContent="Step "+(i+1));}
attachRemove();
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
