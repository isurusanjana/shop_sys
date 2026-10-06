<?php $slug = $e['slug']; $id = $row['id'] ?? 0; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4 class="mb-0"><?= e($title) ?></h4><a class="btn btn-outline-secondary" href="<?= url('crud/list', ['e' => $slug]) ?>"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?><div class="alert alert-danger"><b>Please fix the highlighted fields.</b></div><?php endif; ?>
<form method="post" action="<?= url('crud/save', ['e' => $slug]) ?>" enctype="multipart/form-data" class="card card-body needs-validation" novalidate autocomplete="off">
  <?= csrf_field() ?><?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
  <div class="row g-3">
  <?php foreach ($fields as $f): $n = $f['name']; if ($id && !empty($f['readonly_edit'])) { ?>
    <div class="col-md-6"><label class="form-label"><?= e($f['label']) ?></label><input class="form-control" value="<?= e($row[$n] ?? '') ?>" disabled></div>
  <?php continue; }
    $val = $_SESSION['_old'][$n] ?? ($row[$n] ?? ($f['default'] ?? '')); if ($f['type'] === 'checkbox' && isset($_SESSION['_old']) && !isset($_SESSION['_old'][$n])) $val = 0;
    $req = str_contains($f['rules'] ?? '', 'required'); $err = $errors[$n] ?? null; $cls = 'form-control' . ($err ? ' is-invalid' : '');
    $wide = in_array($f['type'], ['textarea']) ? 'col-12' : 'col-md-6'; ?>
    <div class="<?= $wide ?>">
    <?php if ($f['type'] === 'checkbox'): ?>
      <div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="<?= e($n) ?>" id="f_<?= e($n) ?>" value="1" <?= $val ? 'checked' : '' ?>><label class="form-check-label" for="f_<?= e($n) ?>"><?= e($f['label']) ?></label></div>
    <?php else: ?>
      <label class="form-label <?= $req ? 'required-star' : '' ?>" for="f_<?= e($n) ?>"><?= e($f['label']) ?></label>
      <?php if ($f['type'] === 'select'): ?>
        <select class="form-select <?= $err ? 'is-invalid' : '' ?>" name="<?= e($n) ?>" id="f_<?= e($n) ?>" <?= $req ? 'required' : '' ?>><option value="">-- Select --</option>
          <?php foreach ($f['_options'] as $k => $l): ?><option value="<?= e($k) ?>" <?= (string)$val === (string)$k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <?php elseif ($f['type'] === 'textarea'): ?><textarea class="<?= $cls ?>" name="<?= e($n) ?>" id="f_<?= e($n) ?>" rows="3" maxlength="2000"><?= e($val) ?></textarea>
      <?php elseif ($f['type'] === 'image'): ?>
        <?php if (!empty($row['image'])): ?><div class="mb-1"><img src="<?= e(base_url() . '/uploads/products/' . $row['image']) ?>" height="60" class="rounded" alt=""></div><?php endif; ?>
        <input type="file" class="<?= $cls ?>" name="image" id="f_image" accept="image/jpeg,image/png,image/webp,image/gif">
      <?php else:
        $type = ['number' => 'number', 'decimal' => 'number', 'email' => 'email', 'date' => 'date'][$f['type']] ?? 'text';
        $max = preg_match('/max:(\d+)/', $f['rules'] ?? '', $m) ? 'maxlength="' . (int)$m[1] . '"' : ''; $step = $f['type'] === 'decimal' ? 'step="0.01" min="0"' : ($f['type'] === 'number' ? 'step="1"' : ''); ?>
        <?php if ($n === 'barcode'): ?><div class="input-group"><?php endif; ?>
        <input type="<?= $type ?>" class="<?= $cls ?>" name="<?= e($n) ?>" id="f_<?= e($n) ?>" value="<?= e($val) ?>" <?= $max ?> <?= $step ?> <?= $req ? 'required' : '' ?> <?= isset($f['list']) ? 'list="dl_' . e($n) . '"' : '' ?>>
        <?php if ($n === 'barcode'): ?><button type="button" class="btn btn-outline-secondary" id="genBarcode"><i class="bi bi-upc-scan"></i> Generate</button></div><?php endif; ?>
        <?php if (isset($f['list'])): ?><datalist id="dl_<?= e($n) ?>"><?php foreach ($f['list'] as $o): ?><option value="<?= e($o) ?>"><?php endforeach; ?></datalist><?php endif; ?>
      <?php endif; ?>
      <div class="invalid-feedback"><?= e($err ?? 'Please provide a valid value.') ?></div>
      <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
    <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="mt-4"><button class="btn btn-primary once" type="submit"><i class="bi bi-check-lg"></i> Save</button> <a class="btn btn-link" href="<?= url('crud/list', ['e' => $slug]) ?>">Cancel</a></div>
</form>
<?php if (isset($e['table']) && $e['table'] === 'products'): ob_start(); ?><script>
document.getElementById('genBarcode')?.addEventListener('click',function(){var b=this;b.disabled=true;getJSON('products/generate').then(function(r){if(r.ok){document.getElementById('f_barcode').value=r.barcode;}else{alert(r.error||'Could not generate');}}).finally(function(){b.disabled=false;});});
</script><?php $scripts = ob_get_clean(); endif; ?>
