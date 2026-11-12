<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExcelImportBatch;
use App\Rules\ValidXlsmWorkbook;
use App\Services\AuditLogger;
use App\Services\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExcelImportController extends Controller
{
    public function index(): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        return response()->json(ExcelImportBatch::query()->with('importer:id,name')->latest()->paginate(20));
    }

    public function upload(Request $request, ExcelImportService $imports, AuditLogger $auditLogger): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        if (! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'The PHP ZIP extension is required for XLSM uploads. Enable ext-zip for the web PHP runtime and restart the server.',
                'code' => 'IMPORT_ZIP_EXTENSION_MISSING',
            ], 503);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', new ValidXlsmWorkbook],
        ]);
        $batch = $imports->upload($validated['file'], $request->user());

        $auditLogger->record('excel_import.uploaded', $batch, null, ['source_name' => $batch->source_name, 'sha256' => $batch->source_sha256]);

        return response()->json(['batch' => $this->batchPayload($batch)], 201);
    }

    public function analyze(string $batchId, ExcelImportService $imports, AuditLogger $auditLogger): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        if (! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'The PHP ZIP extension is required to analyze XLSM workbooks. Enable ext-zip for the web PHP runtime and restart the server.',
                'code' => 'IMPORT_ZIP_EXTENSION_MISSING',
            ], 503);
        }

        $batch = ExcelImportBatch::query()->findOrFail($batchId);

        try {
            $batch = $imports->analyze($batch);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => ['KhĂ´ng thá»ƒ phĂ¢n tĂ­ch workbook. Kiá»ƒm tra XLSM vĂ  báº­t PHP extension zip trĂªn mĂ¡y chá»§.']]);
        }

        $auditLogger->record('excel_import.analyzed', $batch, null, $batch->summary);

        return response()->json(['batch' => $this->batchPayload($batch)]);
    }

    public function preview(Request $request, string $batchId): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        $batch = ExcelImportBatch::query()->findOrFail($batchId);

        return response()->json([
            'batch' => $this->batchPayload($batch),
            'rows' => $batch->rows()->orderBy('source_row')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function confirm(Request $request, string $batchId, ExcelImportService $imports, AuditLogger $auditLogger): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        $batch = ExcelImportBatch::query()->findOrFail($batchId);

        $result = $imports->confirm($batch, $request->user());
        $auditLogger->record('excel_import.confirmed', $batch, null, $result);

        return response()->json(['batch' => $this->batchPayload($batch->refresh()), 'result' => $result]);
    }

    public function includeInDashboard(Request $request, string $batchId, ExcelImportService $imports, AuditLogger $auditLogger): JsonResponse
    {
        if (($readiness = $this->schemaReadinessResponse()) !== null) {
            return $readiness;
        }

        $batch = ExcelImportBatch::query()->findOrFail($batchId);

        $count = $imports->includeInDashboard($batch, $request->user());
        $auditLogger->record('excel_import.dashboard_enabled', $batch, null, ['tour_count' => $count]);

        return response()->json(['batch' => $this->batchPayload($batch->refresh()), 'result' => ['included_tours' => $count]]);
    }

    /** @return array<string, mixed> */
    private function batchPayload(ExcelImportBatch $batch): array
    {
        return [
            'id' => $batch->id, 'source_name' => $batch->source_name, 'sha256' => $batch->source_sha256,
            'status' => $batch->status, 'source_sheet' => $batch->source_sheet,
            'summary' => $batch->summary, 'analyzed_at' => $batch->analyzed_at,
            'confirmed_at' => $batch->confirmed_at, 'created_at' => $batch->created_at,
            'dashboard_pending_tours' => $batch->rows()->where('status', 'imported')->whereNotNull('tour_group_id')
                ->whereHas('tour', fn ($query) => $query->where('counts_in_dashboard', false))->count(),
        ];
    }

    private function schemaReadinessResponse(): ?JsonResponse
    {
        foreach (['excel_import_batches', 'excel_import_rows', 'excel_import_archives'] as $table) {
            if (! Schema::hasTable($table)) {
                return response()->json([
                    'message' => 'The Excel import database schema is not installed. Apply the pending additive Laravel migration before retrying.',
                    'code' => 'IMPORT_SCHEMA_NOT_READY',
                ], 503);
            }
        }

        return null;
    }
}
