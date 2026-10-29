<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduledInspectionRecordRequest;
use App\Models\InspectionRecord;
use App\Services\InspectionRecordService;
use App\Services\ScheduledInspectionCsvExporter;
use App\Support\InspectionTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 週次・月次など、点検種別ごとに項目が異なる点検の画面を共通処理で提供する。
 * 点検種別はルートの type パラメーターで決まる。
 * ルートURLのパラメーターが先に渡され、defaults()の値が後に続くため、記録、種別の順で受け取る。
 */
class ScheduledInspectionController extends Controller
{
    /**
     * 点検種別に対応する新しいチェックリストを表示する。
     */
    public function create(string $type): View
    {
        return view('scheduled.create', [
            'type' => InspectionTypes::find($type),
            'groups' => $this->checklist($type)::groups(),
            'record' => null,
            'editing' => false,
        ]);
    }

    /**
     * 入力を検証・保存し、該当種別の記録確認画面へ移動する。
     */
    public function store(
        ScheduledInspectionRecordRequest $request,
        string $type,
        InspectionRecordService $records
    ): RedirectResponse {
        $record = $records->create($request->inspectionData());

        return redirect()
            ->route("inspections.{$type}.show", $record)
            ->with('status', InspectionTypes::find($type)['name'] . '記録を保存しました。');
    }

    /**
     * 指定した種別の点検記録だけを一覧表示する。
     */
    public function index(string $type): View
    {
        return view('scheduled.index', [
            'type' => InspectionTypes::find($type),
            'records' => InspectionRecord::ofType($type)->orderByDesc('inspection_date')->paginate(30),
            'itemCount' => count($this->checklist($type)::items()),
        ]);
    }

    /**
     * 種別が一致する点検記録を閲覧専用で表示する。
     */
    public function show(InspectionRecord $inspection, string $type): View
    {
        $this->ensureType($inspection, $type);

        return view('scheduled.show', [
            'type' => InspectionTypes::find($type),
            'record' => $inspection->load('editLogs'),
            'items' => $this->checklist($type)::items(),
        ]);
    }

    /**
     * 種別が一致する点検記録の編集フォームを表示する。
     */
    public function edit(InspectionRecord $inspection, string $type): View
    {
        $this->ensureType($inspection, $type);

        return view('scheduled.create', [
            'type' => InspectionTypes::find($type),
            'groups' => $this->checklist($type)::groups(),
            'record' => $inspection,
            'editing' => true,
        ]);
    }

    /**
     * 編集内容を保存し、編集者と変更内容を履歴に残す。
     */
    public function update(
        ScheduledInspectionRecordRequest $request,
        InspectionRecord $inspection,
        string $type,
        InspectionRecordService $records
    ): RedirectResponse {
        $this->ensureType($inspection, $type);

        $records->update($inspection, $request->inspectionData());

        return redirect()
            ->route("inspections.{$type}.show", $inspection)
            ->with('status', '変更内容と編集者を記録しました。');
    }

    /**
     * 保存済みの点検結果をCSV（表計算ソフトで扱える表形式）でダウンロードする。
     */
    public function export(string $type, ScheduledInspectionCsvExporter $exporter): StreamedResponse
    {
        return $exporter->download($type);
    }

    /**
     * 点検種別に対応するチェックリストのクラス名を返す。
     * チェックリストは画面に出す項目と、その入力形式をまとめた定義。
     *
     * @return class-string<\App\Support\Checklist>
     */
    private function checklist(string $type): string
    {
        return InspectionTypes::find($type)['checklist'];
    }

    /**
     * 他の種別の記録が、この種別の画面から開かれないよう404にする。
     */
    private function ensureType(InspectionRecord $inspection, string $type): void
    {
        if ($inspection->inspection_type !== $type) {
            abort(404);
        }
    }
}
