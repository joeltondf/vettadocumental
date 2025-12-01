<?php
/** @var array $filters */
/** @var array $vendedores */
/** @var array $sdrs */
?>
<form method="get" class="card mb-3 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="start_date" class="form-label">Data inicial</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label for="end_date" class="form-label">Data final</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
        </div>
        <div class="col-md-2">
            <label for="vendedor" class="form-label">Vendedor</label>
            <select name="vendedor" id="vendedor" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($vendedores as $v): ?>
                    <option value="<?php echo $v['id']; ?>" <?php echo ($filters['vendedor'] ?? '') == $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['nome_completo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="sdr" class="form-label">SDR</label>
            <select name="sdr" id="sdr" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($sdrs as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($filters['sdr'] ?? '') == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nome_completo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">Status</label>
            <input type="text" name="status" id="status" class="form-control" value="<?php echo htmlspecialchars($filters['status'] ?? ''); ?>">
        </div>
        <div class="col-md-12 d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Aplicar filtros</button>
            <a href="?" class="btn btn-secondary">Limpar</a>
        </div>
    </div>
</form>
