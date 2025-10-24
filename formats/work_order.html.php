<div class='print-page'>
  <h2>Orden de trabajo #<?= h($po['id']) ?></h2>
  <p>Estado: <?= h($po['status']) ?></p>
  <table class='table table-sm table-striped'>
    <thead><tr><th>SKU</th><th>Producto</th><th>Cantidad</th></tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
      <tr>
        <td><?= h($it['sku']) ?></td>
        <td><?= h($it['product_name']) ?></td>
        <td><?= h($it['qty']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
