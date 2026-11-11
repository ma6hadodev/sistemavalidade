<form method="post" action="<?= escapar($acaoFormulario) ?>" class="card card-body shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
    <div class="row g-3">
        <div class="col-12 col-md-8">
            <label for="descricao" class="form-label">Descrição do produto</label>
            <input type="text" class="form-control" id="descricao" name="descricao"
                   maxlength="255" required value="<?= escapar($valores['descricao']) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label for="lote" class="form-label">Lote</label>
            <input type="text" class="form-control" id="lote" name="lote"
                   maxlength="100" required value="<?= escapar($valores['lote']) ?>">
        </div>
        <div class="col-12 col-md-6">
            <label for="categoria" class="form-label">Categoria</label>
            <select class="form-select" id="categoria" name="categoria" required>
                <option value="">Selecione</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= escapar($categoria) ?>" <?= $valores['categoria'] === $categoria ? 'selected' : '' ?>>
                        <?= escapar($categoria) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="temperatura_armazenamento" class="form-label">Temperatura/armazenamento</label>
            <input type="text" class="form-control" id="temperatura_armazenamento"
                   name="temperatura_armazenamento" maxlength="100" required
                   placeholder="Ex.: Refrigerado entre 0 °C e 5 °C"
                   value="<?= escapar($valores['temperatura_armazenamento']) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label for="quantidade" class="form-label">Quantidade</label>
            <input type="number" class="form-control" id="quantidade" name="quantidade"
                   min="1" max="4294967295" step="1" required value="<?= escapar($valores['quantidade']) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label for="data_entrada" class="form-label">Data de entrada</label>
            <input type="date" class="form-control" id="data_entrada" name="data_entrada"
                   required value="<?= escapar($valores['data_entrada']) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label for="data_validade" class="form-label">Data de validade</label>
            <input type="date" class="form-control" id="data_validade" name="data_validade"
                   required value="<?= escapar($valores['data_validade']) ?>">
        </div>
        <div class="col-12 d-flex flex-column flex-sm-row gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><?= escapar($rotuloBotao) ?></button>
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>