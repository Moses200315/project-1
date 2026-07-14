<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><?= e($plan['name']) ?> <?= status_badge($plan['status']) ?></h4>
    <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= format_date($plan['week_start'],'d M') ?> – <?= format_date($plan['week_end'],'d M Y') ?></div>
  </div>
  <div class="d-flex gap-2">
    <?php if($plan['status']!=='active'): ?>
    <form method="POST" action="<?= url('mealplans/activate/'.$plan['id']) ?>">
      <?= csrf_field() ?><button class="btn btn-success btn-sm">Activate Plan</button>
    </form>
    <?php endif; ?>
    <a href="<?= url('mealplans/edit/'.$plan['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRecipeModal">
      <i class="bi bi-plus-lg me-1"></i>Add Recipe
    </button>
  </div>
</div>

<!-- Weekly Grid -->
<div class="row g-2 mb-4">
  <?php foreach($days as $day): ?>
  <div class="col">
    <div class="card border-0 h-100" style="border-radius:12px;box-shadow:0 1px 8px rgba(0,0,0,.06);min-width:120px;">
      <div class="card-header text-center fw-bold small py-2" style="background:#f0faf5;border-radius:12px 12px 0 0;color:#2d6a4f;"><?= $day ?></div>
      <div class="card-body p-2">
        <?php $daySlots = $plan['by_day'][$day] ?? [];
        if(empty($daySlots)): ?>
          <div class="text-center text-muted py-3" style="font-size:.72rem;">No meals</div>
        <?php else: foreach($daySlots as $slot): ?>
          <div class="mb-1 p-1 rounded" style="background:#f8fffe;border:1px solid #e2ece7;">
            <div style="font-size:.65rem;text-transform:uppercase;color:#2d6a4f;font-weight:600;"><?= $slot['meal_type'] ?></div>
            <div class="fw-semibold" style="font-size:.75rem;"><?= e(truncate($slot['recipe_title'],22)) ?></div>
            <div class="text-muted" style="font-size:.65rem;"><?= $slot['servings'] ?> serving<?= $slot['servings']>1?'s':'' ?></div>
            <?php if(!empty($slot['ingredients'])): ?>
            <div class="mt-1" style="font-size:.6rem;color:#666;">
              <?php foreach(array_slice($slot['ingredients'],0,3) as $ing): ?>
                <?= e($ing['quantity']) ?> <?= e($ing['unit']) ?> <?= e($ing['name']) ?><?= $ing !== end($slot['ingredients']) ? ', ' : '' ?>
              <?php endforeach; ?>
              <?php if(count($slot['ingredients'])>3): ?>…<?php endif; ?>
            </div>
            <?php endif; ?>
            <button class="btn btn-sm p-0 remove-slot" data-slot-id="<?= $slot['id'] ?>" style="font-size:.65rem;color:#dc3545;background:none;border:none;">✕ Remove</button>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Add Recipe Modal -->
<div class="modal fade" id="addRecipeModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold">Add Recipe to Plan</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Day</label>
          <select id="selectDay" class="form-select">
            <?php foreach($days as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Meal</label>
          <select id="selectMeal" class="form-select">
            <?php foreach($mealTypes as $m): ?><option value="<?= $m ?>"><?= ucfirst($m) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Servings</label>
          <input type="number" id="selectServings" class="form-control" value="2" min="1" max="20">
        </div>
      </div>
      <input type="text" id="recipeSearch" class="form-control mb-2" placeholder="🔍 Filter recipes…">
      <div style="max-height:300px;overflow-y:auto;" id="recipeListContainer">
        <?php foreach($recipes as $r): ?>
        <div class="d-flex align-items-center gap-2 p-2 border-bottom recipe-item" data-title="<?= strtolower(e($r['title'])) ?>">
          <img src="<?= recipe_img_url($r['image']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" alt="">
          <div class="flex-grow-1">
            <div class="fw-semibold small"><?= e(truncate($r['title'],40)) ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= e($r['category_name']) ?> · <?= format_duration((int)$r['prep_time']+(int)$r['cook_time']) ?></div>
          </div>
          <button class="btn btn-sm btn-success add-recipe-btn" data-recipe-id="<?= $r['id'] ?>" data-title="<?= e($r['title']) ?>">Add</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div></div>
</div>

<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;"></div>

<?php $extraScripts = '<script>
const planId='.$plan['id'].';
const csrfToken="'.csrf_token().'";
const addSlotUrl="'.url('mealplans/addSlot').'";
const removeSlotUrl="'.url('mealplans/removeSlot').'";

// Recipe search filter
document.getElementById("recipeSearch").addEventListener("input",function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll(".recipe-item").forEach(el=>{
    el.style.display=el.dataset.title.includes(q)?"":"none";
  });
});

// Add recipe to slot
document.querySelectorAll(".add-recipe-btn").forEach(btn=>{
  btn.addEventListener("click",function(){
    const fd=new FormData();
    fd.append("csrf_token",csrfToken);
    fd.append("meal_plan_id",planId);
    fd.append("recipe_id",this.dataset.recipeId);
    fd.append("day_of_week",document.getElementById("selectDay").value);
    fd.append("meal_type",document.getElementById("selectMeal").value);
    fd.append("servings",document.getElementById("selectServings").value);
    fetch(addSlotUrl,{method:"POST",body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?d.message:"Error adding recipe",d.success?"success":"danger");
      if(d.success)setTimeout(()=>location.reload(),1000);
    });
  });
});

// Remove slot
document.querySelectorAll(".remove-slot").forEach(btn=>{
  btn.addEventListener("click",function(){
    if(!confirm("Remove this recipe from the plan?"))return;
    const fd=new FormData();
    fd.append("csrf_token",csrfToken);
    fd.append("slot_id",this.dataset.slotId);
    fetch(removeSlotUrl,{method:"POST",body:fd}).then(r=>r.json()).then(d=>{
      if(d.success)this.closest("[class]").remove();
      else showToast("Could not remove","danger");
    });
  });
});

function showToast(msg,type){
  const t=document.createElement("div");
  t.className=`toast align-items-center text-white bg-${type} border-0 show`;
  t.setAttribute("role","alert");
  t.innerHTML=`<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white ms-auto me-2" onclick="this.closest(\'.toast\').remove()"></button></div>`;
  document.getElementById("toast-container").appendChild(t);
  setTimeout(()=>t.remove(),3000);
}
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
