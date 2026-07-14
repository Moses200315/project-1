<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
    <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-success"></i>Add New Recipe</h5>
  </div>
  <div class="card-body px-4 pb-4">
    <form method="POST" action="<?= url('recipes/store') ?>" enctype="multipart/form-data" id="recipeForm">
      <?= csrf_field() ?>

      <!-- Basic Info -->
      <h6 class="fw-bold text-success mb-3 mt-2 border-bottom pb-2">📝 Basic Information</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <label class="form-label fw-semibold">Recipe Title *</label>
          <input type="text" name="title" class="form-control" value="<?= old('title') ?>" required
                 placeholder="e.g. Ghanaian Jollof Rice">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Category *</label>
          <select name="category_id" class="form-select" required>
            <option value="">Select Category</option>
            <?php foreach($categories as $id=>$name): ?>
              <option value="<?= $id ?>" <?= old('category_id')==$id?'selected':'' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Description *</label>
        <textarea name="description" class="form-control" rows="3" required
                  placeholder="Briefly describe this recipe…"><?= old('description') ?></textarea>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-2">
          <label class="form-label fw-semibold">Prep Time (min)</label>
          <input type="number" name="prep_time" class="form-control" value="<?= old('prep_time',15) ?>" min="0">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Cook Time (min)</label>
          <input type="number" name="cook_time" class="form-control" value="<?= old('cook_time',30) ?>" min="0">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Servings *</label>
          <input type="number" name="servings" class="form-control" value="<?= old('servings',4) ?>" min="1" max="50" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Energy content / serving</label>
          <input type="number" name="calories" class="form-control" value="<?= old('calories') ?>" min="0" placeholder="kcal">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Difficulty *</label>
          <select name="difficulty" class="form-select" required>
            <?php foreach(['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= old('difficulty','medium')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <option value="published" <?= old('status','published')==='published'?'selected':'' ?>>Published</option>
            <option value="draft" <?= old('status')==='draft'?'selected':'' ?>>Draft</option>
          </select>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Cover Image</label>
          <input type="file" name="image" class="form-control" accept="image/*" id="imgInput" onchange="previewImg(this)">
          <img id="imgPreview" class="mt-2 rounded" style="max-height:120px;display:none;" alt="Preview">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_premium" id="isPremium" value="1"
                   <?= old('is_premium')?'checked':'' ?>>
            <label class="form-check-label fw-semibold" for="isPremium">⭐ Premium Recipe</label>
            <div class="text-muted small">Requires subscription (Monthly/Yearly)</div>
          </div>
        </div>
      </div>

      <!-- Ingredients -->
      <h6 class="fw-bold text-success mb-3 border-bottom pb-2">🛒 Ingredients</h6>
      <div id="ingredientList">
        <div class="row g-2 mb-2 ingredient-row">
          <div class="col-md-5"><input type="text" name="ingredients[0][name]" class="form-control" placeholder="Ingredient name *" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[0][quantity]" class="form-control" placeholder="Qty (e.g. 2, 1/2)" required></div>
          <div class="col-md-3"><input type="text" name="ingredients[0][unit]" class="form-control" placeholder="Unit (cups, g, tbsp…)"></div>
          <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-row" title="Remove"><i class="bi bi-trash"></i></button></div>
        </div>
      </div>
      <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addRow('ingredient')">
        <i class="bi bi-plus-circle me-1"></i>Add Ingredient
      </button>

      <!-- Procedures -->
      <h6 class="fw-bold text-success mt-4 mb-3 border-bottom pb-2">👨‍🍳 Cooking Procedure</h6>
      <div id="procedureList">
        <div class="card mb-3 procedure-row border-0 bg-light" style="border-radius:10px;">
          <div class="card-body pb-2">
            <div class="d-flex justify-content-between mb-2">
              <span class="badge bg-success step-num">Step 1</span>
              <button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button>
            </div>
            <textarea name="procedures[0][instruction]" class="form-control mb-2" rows="2"
                      placeholder="Describe this step clearly…" required></textarea>
            <input type="text" name="procedures[0][tip]" class="form-control form-control-sm"
                   placeholder="💡 Optional chef tip for this step">
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-outline-success btn-sm" onclick="addRow('procedure')">
        <i class="bi bi-plus-circle me-1"></i>Add Step
      </button>

      <!-- Submit -->
      <div class="d-flex gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-success px-4 fw-semibold">
          <i class="bi bi-check-lg me-2"></i>Save Recipe
        </button>
        <a href="<?= url('recipes/adminIndex') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = '<script>
let ingIdx=1, procIdx=1;
function addRow(type){
  if(type==="ingredient"){
    const div=document.createElement("div");
    div.className="row g-2 mb-2 ingredient-row";
    div.innerHTML=`<div class="col-md-5"><input type="text" name="ingredients[${ingIdx}][name]" class="form-control" placeholder="Ingredient name *" required></div><div class="col-md-3"><input type="text" name="ingredients[${ingIdx}][quantity]" class="form-control" placeholder="Qty" required></div><div class="col-md-3"><input type="text" name="ingredients[${ingIdx}][unit]" class="form-control" placeholder="Unit"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div>`;
    document.getElementById("ingredientList").appendChild(div);
    ingIdx++;
  } else {
    const div=document.createElement("div");
    div.className="card mb-3 procedure-row border-0 bg-light";
    div.style.borderRadius="10px";
    div.innerHTML=`<div class="card-body pb-2"><div class="d-flex justify-content-between mb-2"><span class="badge bg-success step-num">Step ${procIdx+1}</span><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button></div><textarea name="procedures[${procIdx}][instruction]" class="form-control mb-2" rows="2" placeholder="Describe this step…" required></textarea><input type="text" name="procedures[${procIdx}][tip]" class="form-control form-control-sm" placeholder="💡 Optional chef tip"></div>`;
    document.getElementById("procedureList").appendChild(div);
    procIdx++;
  }
  attachRemove();
}
function attachRemove(){
  document.querySelectorAll(".remove-row").forEach(b=>{
    b.onclick=function(){
      const row=this.closest(".ingredient-row,.procedure-row");
      if(row)row.remove();
      renumberSteps();
    };
  });
}
function renumberSteps(){
  document.querySelectorAll(".step-num").forEach((s,i)=>s.textContent="Step "+(i+1));
}
function previewImg(input){
  const preview=document.getElementById("imgPreview");
  if(input.files&&input.files[0]){
    const reader=new FileReader();
    reader.onload=e=>{preview.src=e.target.result;preview.style.display="block";};
    reader.readAsDataURL(input.files[0]);
  }
}
attachRemove();
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
