<?php

namespace App\Support;

/**
 * ホーム画面に表示する点検種別の定義。
 */
class InspectionTypes
{
    /**
     * 点検種別を表示順に返す。
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'daily' => [
                'key' => 'daily',
                'name' => '日常点検',
                'description' => '毎日行う設備点検を記録します',
                'route' => 'inspections.create',
                'checklist' => EquipmentChecklist::class,
                'available' => true,
            ],
            'weekly' => [
                'key' => 'weekly',
                'name' => '週次点検',
                'description' => '週末に行う設備点検を記録します',
                'route' => 'inspections.weekly.create',
                'subtitle' => '週末担当者又は保全担当者停止時点検',
                'checklist' => WeeklyChecklist::class,
                'available' => true,
            ],
            'monthly' => [
                'key' => 'monthly',
                'name' => '月次点検',
                'description' => '毎月第一週末に行う設備点検を記録します',
                'route' => 'inspections.monthly.create',
                'subtitle' => '月初め又は保全担当者停止時点検',
                'checklist' => MonthlyChecklist::class,
                'available' => true,
            ],
            'periodic' => [
                'key' => 'periodic',
                'name' => '定期点検',
                'description' => '6月、12月の決算時に行う設備点検を記録します',
                'route' => 'inspections.periodic.create',
                'subtitle' => '原料場　油の補充・交換（6月分・12月分）',
                'footnote' => "6月：交換、12月：補充\n※オイルポット（のぞき窓）はオイルの確認ができない場合は交換　※担当者は欄に補充量記入の事　※担当責任者は記録確認の事",
                'checklist' => PeriodicChecklist::class,
                'available' => true,
            ],
        ];
    }

    /**
     * 指定した種別の定義を返す。存在しない場合は404にする。
     *
     * @return array<string, mixed>
     */
    public static function find(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }

    /**
     * 点検種別に対応するチェックリストの項目一覧を返す。存在しない種別は日常点検として扱う。
     *
     * @return array<string, array<string, mixed>>
     */
    public static function itemsFor(string $key): array
    {
        $checklist = self::all()[$key]['checklist'] ?? EquipmentChecklist::class;

        return $checklist::items();
    }
}
