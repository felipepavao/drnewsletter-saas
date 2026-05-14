<?php
/** @var ?array $client */
/** @var array $errors */
/** @var array $formData */
$isEdit = $client !== null;
$action = $isEdit ? url('/clientes/' . (int) $client['id']) : url('/clientes');
?>
<div class="container container--narrow">
    <p class="breadcrumb">
        <a href="<?= url('/clientes') ?>">← Clientes</a>
    </p>
    <h1><?= $isEdit ? 'Editar cliente' : 'Novo cliente' ?></h1>

    <form method="post" action="<?= $action ?>" class="card stack" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="name">Nome do negócio *</label>
            <input type="text" id="name" name="name" class="input"
                   value="<?= e($formData['name']) ?>" required maxlength="120"
                   autofocus autocomplete="off">
            <?php if (!empty($errors['name'])): ?>
                <p class="field-error"><?= e($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="email">Email de contato</label>
            <input type="email" id="email" name="email" class="input"
                   value="<?= e($formData['email']) ?>" autocomplete="off">
            <p class="help">Opcional. Não enviamos emails para este endereço.</p>
            <?php if (!empty($errors['email'])): ?>
                <p class="field-error"><?= e($errors['email']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="segment">Segmento</label>
            <select id="segment" name="segment" class="select">
                <option value="">—</option>
                <?php foreach (Client::SEGMENTS as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= selected($formData['segment'] === $k) ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['segment'])): ?>
                <p class="field-error"><?= e($errors['segment']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="notes">Anotações internas</label>
            <textarea id="notes" name="notes" class="textarea" maxlength="2000"
                      placeholder="Contexto sobre o cliente, contatos, particularidades…"><?= e($formData['notes']) ?></textarea>
            <p class="help">Só você vê. Máx 2000 caracteres.</p>
            <?php if (!empty($errors['notes'])): ?>
                <p class="field-error"><?= e($errors['notes']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">
                <?= $isEdit ? 'Salvar alterações' : 'Criar cliente' ?>
            </button>
            <a class="btn" href="<?= $isEdit ? url('/clientes/' . (int) $client['id']) : url('/clientes') ?>">
                Cancelar
            </a>
        </div>
    </form>
</div>
