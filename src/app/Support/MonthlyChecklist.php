<?php

namespace App\Support;

/**
 * 月次点検（月初め又は保全担当者停止時点検）のチェックリスト定義。
 */
class MonthlyChecklist extends Checklist
{
    /**
     * 点検画面に表示する順序で、グループおよび項目一覧を返す。
     */
    public static function groups(): array
    {
        $groups = [];

        // ミキサーと真空ポンプは1〜3号機それぞれの項目を作成する。
        foreach ([1, 2, 3] as $no) {
            $groups[] = [
                'name' => "ミキサー（{$no}）",
                'items' => [
                    ['id' => "m-mixer{$no}-belt-high", 'location' => '高速軸ベルト', 'item' => 'たるみ・損傷', 'method' => '目視・手'],
                    ['id' => "m-mixer{$no}-belt-low", 'location' => '低速軸ベルト', 'item' => 'たるみ・損傷', 'method' => '目視・手'],
                    ['id' => "m-mixer{$no}-reducer-oil", 'location' => '減速部、油圧部', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                ],
            ];
            $groups[] = [
                'name' => "真空ポンプ（{$no}）",
                'items' => [
                    ['id' => "m-pump{$no}-belt", 'location' => 'ベルト', 'item' => 'たるみ・損傷', 'method' => '目視・手'],
                ],
            ];
        }

        $groups[] = [
            'name' => '暖房室',
            'items' => [
                ['id' => 'm-heater-temp', 'location' => '保温（ヒーター）', 'item' => '温度', 'method' => '読取', 'type' => 'numeric', 'unit' => '℃'],
            ],
        ];

        // 集塵機は2台分の項目を作成する。
        foreach ([1, 2] as $no) {
            $groups[] = [
                'name' => "集塵機（{$no}）",
                'items' => [
                    ['id' => "m-dust{$no}-duct-joint", 'location' => '吸引ダクト', 'item' => '接合部の緩み', 'method' => '増締'],
                    ['id' => "m-dust{$no}-duct-damage", 'location' => '吸引ダクト', 'item' => '腐食・破損', 'method' => '目視'],
                    ['id' => "m-dust{$no}-duct-dust", 'location' => '吸引ダクト', 'item' => 'ダスト堆積状態', 'method' => '目視'],
                ],
            ];
        }

        return $groups;
    }
}
