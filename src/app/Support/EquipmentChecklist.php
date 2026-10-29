<?php

namespace App\Support;

/**
 * 日常点検のチェックリストを定義する。各グループはname（表示名）とitems（項目一覧）を持つ。
 */
class EquipmentChecklist
{
    /**
     * 点検画面に表示するグループと項目を返す。各項目のidは保存用キー、location/item/methodは表示情報、typeは入力方式。
     *
     * @return list<array{
     *     name: string,
     *     items: list<array{
     *         id: string,
     *         location: string,
     *         item: string,
     *         method: string,
     *         type?: 'pressure'
     *     }>
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'name' => 'プラッター（1）',
                'items' => [
                    ['id' => 'g0-i0', 'location' => 'バッテリー', 'item' => '充電', 'method' => '目視'],
                ],
            ],
            [
                'name' => 'プラッター（2）',
                'items' => [
                    ['id' => 'g1-i0', 'location' => 'バッテリー', 'item' => '充電', 'method' => '目視'],
                ],
            ],
            [
                'name' => 'ミキサー（1）',
                'items' => [
                    ['id' => 'g2-i0', 'location' => 'モータ減速部', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'g2-i1', 'location' => '真空メーター', 'item' => '圧力計', 'method' => '数値入力', 'type' => 'pressure'],
                    ['id' => 'g2-i2', 'location' => '真空ポンプ', 'item' => 'ドレン', 'method' => '水抜き'],
                ],
            ],
            [
                'name' => 'ミキサー（2）',
                'items' => [
                    ['id' => 'g3-i0', 'location' => 'モータ減速部', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'g3-i1', 'location' => '真空メーター', 'item' => '圧力計', 'method' => '数値入力', 'type' => 'pressure'],
                    ['id' => 'g3-i2', 'location' => '真空ポンプ', 'item' => 'ドレン', 'method' => '水抜き'],
                ],
            ],
            [
                'name' => 'ミキサー（3）',
                'items' => [
                    ['id' => 'g4-i0', 'location' => 'モータ減速部', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'g4-i1', 'location' => '真空メーター', 'item' => '圧力計', 'method' => '数値入力', 'type' => 'pressure'],
                    ['id' => 'g4-i2', 'location' => '真空ポンプ', 'item' => 'ドレン', 'method' => '水抜き'],
                ],
            ],
            [
                'name' => 'カンペコ機',
                'items' => [
                    ['id' => 'g5-i0', 'location' => '駆動部', 'item' => '異音', 'method' => '目視'],
                    ['id' => 'g5-i1', 'location' => '駆動部', 'item' => '汚れ状態', 'method' => '目視'],
                ],
            ],
            [
                'name' => '集塵機',
                'items' => [
                    ['id' => 'g6-i0', 'location' => 'ファンモータ', 'item' => '異音', 'method' => '音'],
                    ['id' => 'g6-i1', 'location' => '排気口', 'item' => '粉塵漏れ', 'method' => '目視'],
                    ['id' => 'g6-i2', 'location' => '吸引部', 'item' => '吸引状態', 'method' => '目視'],
                    ['id' => 'g6-i3', 'location' => 'フィルタ点検扉', 'item' => '扉パッキン部吸気音', 'method' => '音'],
                    ['id' => 'g6-i4', 'location' => 'ダストボックス点検扉', 'item' => '扉パッキン部粉塵漏れ', 'method' => '目視'],
                    ['id' => 'g6-i5', 'location' => 'ダストボックス', 'item' => 'ダスト堆積状態', 'method' => '目視'],
                ],
            ],
        ];
    }

    /**
     * 結果を保存する固定IDをキーにして一覧化し、グループ表示名も各項目に追加する。
     *
     * @return array<string, array{
     *     id: string,
     *     location: string,
     *     item: string,
     *     method: string,
     *     group: string,
     *     type?: 'pressure'
     * }>
     */
    public static function items(): array
    {
        $items = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $item['group'] = $group['name'];
                $items[$item['id']] = $item;
            }
        }

        return $items;
    }
}
