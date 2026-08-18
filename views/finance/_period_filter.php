<?php
/**
 * @var string $formAction
 * @var array  $range   result of Finance::resolveRange()
 * @var array  $stores
 */
use App\Helpers\Response;

$currentPeriod = $_GET['period'] ?? 'month';
?>
<div class="azr-filter-bar azr-no-print">
    <form method="get" action="<?= Response::e($formAction) ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;" id="azrPeriodForm">
        <div class="azr-form-group">
            <label class="azr-label">Periode</label>
            <select class="azr-select" name="period" id="azrPeriodSelect">
                <option value="day" <?= $currentPeriod === 'day' ? 'selected' : '' ?>>Hari Ini</option>
                <option value="week" <?= $currentPeriod === 'week' ? 'selected' : '' ?>>Minggu Ini</option>
                <option value="month" <?= $currentPeriod === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="year" <?= $currentPeriod === 'year' ? 'selected' : '' ?>>Tahun Ini</option>
                <option value="custom" <?= $currentPeriod === 'custom' ? 'selected' : '' ?>>Rentang Kustom</option>
            </select>
        </div>
        <div class="azr-form-group" id="azrDateFromGroup" style="<?= $currentPeriod === 'custom' ? '' : 'display:none;' ?>">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? $range['start_date']) ?>">
        </div>
        <div class="azr-form-group" id="azrDateToGroup" style="<?= $currentPeriod === 'custom' ? '' : 'display:none;' ?>">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? $range['end_date']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Toko/Cabang</label>
            <select class="azr-select" name="store_id">
                <option value="">Semua Toko</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Terapkan</button>
        <button type="button" class="azr-btn azr-btn-outline" data-azr-print>Cetak</button>
    </form>
</div>
<p class="azr-help-text azr-no-print" style="margin:-6px 0 14px;">Menampilkan periode: <strong><?= Response::e($range['label']) ?></strong> (<?= Response::e($range['start_date']) ?> s/d <?= Response::e($range['end_date']) ?>)</p>
