<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectionRecordRequest;
use App\Models\InspectionRecord;
use App\Services\InspectionCsvExporter;
use App\Services\InspectionRecordService;
use App\Support\EquipmentChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 日常点検の入力、履歴表示、保存済み記録の確認・編集を受け持つ。
 */
class InspectionController extends Controller
{
    /**
     * 新しい点検を入力するための空のチェックリストを表示する。
     */
    public function create(): View
    {
        return view('equipment-check', [
            'groups' => EquipmentChecklist::groups(),
            'record' => null,
            'editing' => false,
        ]);
    }

    /**
     * 検証済みの点検データを保存し、登録直後に内容を確認できる画面へ移動する。
     */
    public function store(
        InspectionRecordRequest $request,
        InspectionRecordService $records
    ): RedirectResponse {
        $record = $records->create($request->inspectionData());

        return redirect()
            ->route('inspections.show', $record)
            ->with('status', '点検記録を保存しました。');
    }

    /**
     * 編集操作を表示せず、保存済みの点検記録を一覧表示する。
     */
    public function history(): View
    {
        return view('inspections.index', [
            'records' => InspectionRecord::ofType('daily')->orderByDesc('inspection_date')->paginate(30),
            'itemCount' => count(EquipmentChecklist::items()),
        ]);
    }

    /**
     * 点検記録を閲覧専用で表示し、編集履歴を読み込む。
     */
    public function show(InspectionRecord $inspection): View
    {
        return view('inspections.show', [
            'record' => $inspection->load('editLogs'),
            'items' => EquipmentChecklist::items(),
        ]);
    }

    /**
     * 明示的な編集操作の後に、編集フォームを表示する。
     */
    public function edit(InspectionRecord $inspection): View
    {
        return view('equipment-check', [
            'groups' => EquipmentChecklist::groups(),
            'record' => $inspection,
            'editing' => true,
        ]);
    }

    /**
     * 編集済み記録を保存し、編集者と変更内容を監査履歴に残す。
     */
    public function update(
        InspectionRecordRequest $request,
        InspectionRecord $inspection,
        InspectionRecordService $records
    ): RedirectResponse {
        $records->update($inspection, $request->inspectionData());

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('status', '変更内容と編集者を記録しました。');
    }

    /**
     * 保存済みの点検結果をCSV形式でダウンロードする。
     */
    public function export(InspectionCsvExporter $exporter): StreamedResponse
    {
        return $exporter->download();
    }
}
