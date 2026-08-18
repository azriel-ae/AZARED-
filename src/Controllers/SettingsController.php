<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AppSetting;
use App\Models\AuditLog;

final class SettingsController
{
    /**
     * The only keys this screen may write. Keeps AppSetting from
     * becoming a free-form bag of unused values - every key here is
     * actually read elsewhere (see the grep-able comment on each line).
     */
    private const ALLOWED_KEYS = [
        'company_legal_name',   // read by views/sales/show.php (A4 invoice header)
        'receipt_footer_note',  // read by views/sales/show.php (thermal receipt + A4 footer)
        'low_stock_alert_note', // read by views/dashboard/index.php (low-stock widget caption)
    ];

    public static function index(): void
    {
        $settings = AppSetting::allAsMap();
        require dirname(__DIR__, 2) . '/views/settings/index.php';
    }

    public static function update(): void
    {
        $data = [
            'company_legal_name'   => trim((string) ($_POST['company_legal_name'] ?? '')),
            'receipt_footer_note'  => trim((string) ($_POST['receipt_footer_note'] ?? '')),
            'low_stock_alert_note' => trim((string) ($_POST['low_stock_alert_note'] ?? '')),
        ];

        $validator = new Validator($data);
        $validator->maxLength('company_legal_name', 150, 'Nama badan usaha')
            ->maxLength('receipt_footer_note', 255, 'Catatan kaki struk')
            ->maxLength('low_stock_alert_note', 255, 'Catatan stok menipis');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $before = AppSetting::allAsMap();
        $toSave = array_intersect_key($data, array_flip(self::ALLOWED_KEYS));
        AppSetting::setMany($toSave);

        AuditLog::record(AuthService::id(), 'settings.update', 'app_settings', null, $before, $toSave);
        Response::jsonSuccess([], 'Pengaturan berhasil disimpan.');
    }
}
