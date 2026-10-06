<?php $dim = ['small' => [38, 25, 1.0, 22], 'medium' => [50, 30, 1.25, 28], 'large' => [70, 40, 1.7, 36]][$size]; ?>
<!doctype html><html><head><meta charset="utf-8"><title>Labels</title><style>
@page{margin:6mm}body{margin:0;font-family:Arial,sans-serif}.bar{padding:6px 10px;background:#f1f1f1;font-size:13px}
.sheet{display:flex;flex-wrap:wrap;gap:2mm;padding:2mm}.label{width:<?= $dim[0] ?>mm;height:<?= $dim[1] ?>mm;border:1px dashed #bbb;box-sizing:border-box;overflow:hidden;text-align:center;page-break-inside:avoid;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:1mm}
.label svg{max-width:100%;height:<?= $dim[3] ?>%;width:auto}.label .n{font-size:<?= 7 * $dim[2] ?>px;line-height:1.1;max-height:2.2em;overflow:hidden}.label .p{font-size:<?= 8 * $dim[2] ?>px;font-weight:bold}
@media print{.bar{display:none}.label{border:0}}</style></head><body>
<div class="bar"><button onclick="window.print()">Print</button> &nbsp; Tip: set paper size to match your label stock and disable headers/footers.</div>
<div class="sheet"><?php foreach ($items as [$p, $n]): for ($i = 0; $i < $n; $i++): ?><div class="label"><?php if ($showName): ?><div class="n"><?= e(mb_strimwidth($p['name'], 0, 40, '…')) ?></div><?php endif; ?><?= Barcode::svg($p['barcode'], 40, 1.6) ?><?php if ($showPrice): ?><div class="p"><?= e(money($p['selling_price'])) ?></div><?php endif; ?></div><?php endfor; endforeach; ?></div></body></html>
