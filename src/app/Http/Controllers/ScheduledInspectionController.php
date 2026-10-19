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
 * defaults() の値はURIパラメーターの後ろに渡されるため、記録、種別の順で受け取る。
 */
class ScheduledInspectionController extends Controller
{
    /**
     * 新しい点検を入力するためのチェックリストを表示する。
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
     * 点検データを保存し、記録確認画面へ移動する。
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
     * 点検記録を一覧表示する。
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
     * 点検記録を閲覧専用で表示する。
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
     * 点検記録の編集フォームを表示する。
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
     * 編集された点検記録を更新し、変更履歴を記録する。
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
     * 保存済みの点検結果をCSV形式でダウンロードする。
     */
    public function export(string $type, ScheduledInspectionCsvExporter $exporter): StreamedResponse
    {
        return $exporter->download($type);
    }

    /**
     * 点検種別に対応するチェックリストのクラス名を返す。
     *
     * @return class-string<\App\Support\Checklist>
     */
    private function checklist(string $type): string
    {
        return InspectionTypes::find($type)['checklist'];
    }

    /**
     * 他の点検種別の記録が、この種別の画面から開かれないようにする。
     */
    private function ensureType(InspectionRecord $inspection, string $type): void
    {
        if ($inspection->inspection_type !== $type) {
            abort(404);
        }
    }
}
